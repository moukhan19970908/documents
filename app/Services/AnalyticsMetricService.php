<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentApprovalDecision;
use App\Models\DocumentApprovalStage;
use App\Models\DocumentType;
use App\Models\Task;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Слой вычисления метрик для готовых дашбордов (ТЗ 27.1).
 * Каждый метод — группа метрик; разрез (направление/отдел/тип/период) задаётся фильтрами.
 * Позже переиспользуется конструктором дашбордов (ТЗ 27.2).
 */
class AnalyticsMetricService
{
    /**
     * Группа 1 — операционная картина: В работе / На согласовании / На ознакомлении / Просрочено,
     * в разрезе направления, отдела или типа.
     */
    public function operational(array $f): array
    {
        $dimension = in_array($f['dimension'] ?? null, ['direction', 'department', 'type'], true)
            ? $f['dimension'] : 'direction';

        $columns = [
            'on_approval' => 'На согласовании',
            'on_ack'      => 'На ознакомлении',
            'in_work'     => 'В работе',
            'overdue'     => 'Просрочено',
        ];

        $groupBy = $dimension === 'type' ? 'type' : 'dept';
        $maps = [];
        foreach (array_keys($columns) as $state) {
            $maps[$state] = $this->countMap($this->stateBase($state, $f), $groupBy);
        }

        // Для «направления» сворачиваем счётчики отделов к корню направления.
        if ($dimension === 'direction') {
            $rootOf = $this->deptRootResolver();
            foreach ($maps as $state => $map) {
                $rolled = [];
                foreach ($map as $deptId => $c) {
                    $key = $deptId ? $rootOf((int) $deptId) : 0;
                    $rolled[$key] = ($rolled[$key] ?? 0) + $c;
                }
                $maps[$state] = $rolled;
            }
        }

        $labels = $this->dimensionLabels($dimension, $maps);

        $rows = [];
        foreach ($labels as $key => $label) {
            $row = ['label' => $label];
            $sum = 0;
            foreach (array_keys($columns) as $state) {
                $row[$state] = (int) ($maps[$state][$key] ?? 0);
                $sum += $state === 'overdue' ? 0 : $row[$state];
            }
            $row['total'] = $sum;
            $rows[] = $row;
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totals = ['label' => 'Итого'];
        foreach (array_keys($columns) as $state) {
            $totals[$state] = array_sum(array_column($rows, $state));
        }

        return compact('dimension', 'columns', 'rows', 'totals');
    }

    /**
     * Группа 2 — узкие места и SLA: среднее время на каждом звене, самые «медленные»
     * согласующие, % соблюдения сроков, точки «звено × согласующий».
     */
    public function sla(array $f): array
    {
        $stages = DocumentApprovalStage::query()
            ->whereNotNull('started_at')->whereNotNull('completed_at')
            ->whereHas('documentApproval.document', fn ($d) => $this->applyDocFilters($d, $f))
            ->with('workflowStage:id,name,phase')
            ->get(['id', 'workflow_stage_id', 'started_at', 'completed_at', 'deadline_at']);

        $stageRows = $stages->groupBy(fn ($s) => $s->workflowStage?->name ?: 'Без названия')
            ->map(fn ($grp, $name) => [
                'name'        => $name,
                'avg_hours'   => round($grp->avg(fn ($s) => $s->started_at->diffInHours($s->completed_at)), 1),
                'count'       => $grp->count(),
                'on_time_pct' => $this->onTimePct($grp),
            ])->values()->sortByDesc('avg_hours')->values()->all();

        $decisions = DocumentApprovalDecision::query()
            ->whereIn('action', ['approve', 'reject'])
            ->whereNotNull('decided_at')
            ->whereHas('stage.documentApproval.document', fn ($d) => $this->applyDocFilters($d, $f))
            ->with(['user:id,name', 'stage:id,started_at,workflow_stage_id', 'stage.workflowStage:id,name'])
            ->get()
            ->filter(fn ($d) => $d->stage && $d->stage->started_at);

        $approvers = $decisions->groupBy(fn ($d) => $d->user?->name ?: '—')
            ->map(fn ($grp, $name) => [
                'name'      => $name,
                'avg_hours' => round($grp->avg(fn ($d) => $d->stage->started_at->diffInHours($d->decided_at)), 1),
                'count'     => $grp->count(),
            ])->values()->sortByDesc('avg_hours')->values()->all();

        // «Медленно на утверждении у коммерческого директора» — пары согласующий × звено.
        $hotspots = $decisions->groupBy(fn ($d) => ($d->user?->name ?: '—') . '||' . ($d->stage->workflowStage?->name ?: 'Без названия'))
            ->map(function ($grp, $key) {
                [$approver, $stage] = explode('||', $key);
                return [
                    'approver'  => $approver,
                    'stage'     => $stage,
                    'avg_hours' => round($grp->avg(fn ($d) => $d->stage->started_at->diffInHours($d->decided_at)), 1),
                    'count'     => $grp->count(),
                ];
            })->values()->sortByDesc('avg_hours')->take(10)->values()->all();

        return [
            'stages'          => $stageRows,
            'approvers'       => $approvers,
            'hotspots'        => $hotspots,
            'on_time_pct'     => $this->onTimePct($stages),
            'total_completed' => $stages->count(),
        ];
    }

    /**
     * Группа 3 — пропускная способность: создано / завершено по месяцам (динамика, сезонность).
     */
    public function throughput(array $f): array
    {
        [$start, $end] = $this->range($f);

        $months = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        // Разрез направление/отдел/тип применяем, а период задаёт ось (не фильтруем по created_at дважды).
        $docFilter = ['direction' => $f['direction'] ?? null, 'department' => $f['department'] ?? null, 'type' => $f['type'] ?? null];

        $created = $this->monthlyCounts($this->applyDocFilters(Document::query(), $docFilter), 'created_at', $start, $end);
        $completed = $this->monthlyCounts(
            DocumentApproval::whereNotNull('completed_at')->whereHas('document', fn ($d) => $this->applyDocFilters($d, $docFilter)),
            'completed_at', $start, $end
        );

        $createdSeries = array_map(fn ($m) => $created[$m] ?? 0, $months);
        $completedSeries = array_map(fn ($m) => $completed[$m] ?? 0, $months);

        return [
            'months'          => array_map(fn ($m) => Carbon::createFromFormat('Y-m', $m)->translatedFormat('M Y'), $months),
            'created'         => $createdSeries,
            'completed'       => $completedSeries,
            'total_created'   => array_sum($createdSeries),
            'total_completed' => array_sum($completedSeries),
        ];
    }

    /**
     * Группа 4 — нагрузка на людей: сколько задач висит на каждом (и сколько просрочено).
     */
    public function load(array $f): array
    {
        $pending = Task::where('status', 'pending')->get(['id', 'assignee_id', 'deadline_at']);

        $names = User::whereIn('id', $pending->pluck('assignee_id')->filter()->unique())->pluck('name', 'id');

        $rows = $pending->groupBy('assignee_id')->map(function ($grp, $uid) use ($names) {
            return [
                'name'    => $uid ? ($names[$uid] ?? 'Пользователь #' . $uid) : 'Не назначен',
                'pending' => $grp->count(),
                'overdue' => $grp->filter(fn ($t) => $t->deadline_at && $t->deadline_at->isPast())->count(),
            ];
        })->values()->sortByDesc('pending')->values()->all();

        return [
            'rows'          => $rows,
            'total_pending' => $pending->count(),
            'total_overdue' => $pending->filter(fn ($t) => $t->deadline_at && $t->deadline_at->isPast())->count(),
            'people'        => count($rows),
        ];
    }

    /**
     * Группа 5 — предметная: кредитный комитет (суммы по контрагентам),
     * командировки/отпуска (отсутствия + затраты), поручения (исполнительская дисциплина).
     */
    public function domain(array $f): array
    {
        return [
            'credit'      => $this->creditCommittee($f),
            'absences'    => $this->absences($f),
            'assignments' => $this->assignmentDiscipline($f),
        ];
    }

    /** Кредитный комитет: суммы договоров по контрагентам (значения из data JSON). */
    private function creditCommittee(array $f): array
    {
        $docs = Document::whereHas('workflow', fn ($w) => $w->where('process_type', 'credit_committee'))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->get(['id', 'data']);

        $byCp = [];
        foreach ($docs as $d) {
            $data = $d->data ?? [];
            $cp = $data['contractor'] ?? '— без контрагента';
            $amt = is_numeric($data['amount'] ?? null) ? (float) $data['amount'] : 0.0;
            $byCp[$cp]['contractor'] = $cp;
            $byCp[$cp]['total'] = ($byCp[$cp]['total'] ?? 0) + $amt;
            $byCp[$cp]['count'] = ($byCp[$cp]['count'] ?? 0) + 1;
        }

        $rows = collect($byCp)->sortByDesc('total')->values()->all();

        return [
            'rows'  => $rows,
            'total' => array_sum(array_column($rows, 'total')),
            'count' => $docs->count(),
        ];
    }

    /** Командировки/отпуска: кто сейчас отсутствует и затраты на командировки. */
    private function absences(array $f): array
    {
        $today = now()->startOfDay();
        $active = ['approved', 'in_registry'];

        $trips = TripRequest::whereIn('status', $active)->get(['user_id', 'date_start', 'date_end', 'total_amount', 'city']);
        $vacs  = VacationRequest::whereIn('status', $active)->get(['user_id', 'date_start', 'date_end', 'vacation_type']);

        $userIds = $trips->pluck('user_id')->merge($vacs->pluck('user_id'))->filter()->unique();
        $names = User::whereIn('id', $userIds)->pluck('name', 'id');

        $current = collect();
        foreach ($trips as $t) {
            if ($t->date_start && $t->date_end && $t->date_start <= $today && $today <= $t->date_end) {
                $current->push(['name' => $names[$t->user_id] ?? '—', 'type' => 'Командировка' . ($t->city ? " · {$t->city}" : ''), 'until' => $t->date_end->format('d.m.Y')]);
            }
        }
        foreach ($vacs as $v) {
            if ($v->date_start && $v->date_end && $v->date_start <= $today && $today <= $v->date_end) {
                $current->push(['name' => $names[$v->user_id] ?? '—', 'type' => 'Отпуск', 'until' => $v->date_end->format('d.m.Y')]);
            }
        }

        return [
            'current'   => $current->sortBy('name')->values()->all(),
            'trip_cost' => (float) $trips->sum('total_amount'),
            'trips'     => $trips->count(),
            'vacations' => $vacs->count(),
        ];
    }

    /** Поручения: исполнительская дисциплина (в срок / с опозданием / открытые просроченные). */
    private function assignmentDiscipline(array $f): array
    {
        $done = Assignment::where('status', 'done')
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('accepted_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('accepted_at', '<=', $v))
            ->get(['executor_id', 'due_at', 'accepted_at']);

        $onTime = $done->filter(fn ($a) => ! $a->due_at || ($a->accepted_at && $a->accepted_at <= $a->due_at->copy()->endOfDay()))->count();
        $late = $done->count() - $onTime;

        $openOverdue = Assignment::where('status', '!=', 'done')
            ->whereNotNull('due_at')->where('due_at', '<', now())->count();

        $names = User::whereIn('id', $done->pluck('executor_id')->filter()->unique())->pluck('name', 'id');
        $executors = $done->groupBy('executor_id')->map(function ($grp, $uid) use ($names) {
            $ot = $grp->filter(fn ($a) => ! $a->due_at || ($a->accepted_at && $a->accepted_at <= $a->due_at->copy()->endOfDay()))->count();
            return [
                'name'    => $uid ? ($names[$uid] ?? '#' . $uid) : '—',
                'done'    => $grp->count(),
                'on_time' => $ot,
                'late'    => $grp->count() - $ot,
            ];
        })->values()->sortByDesc('done')->values()->all();

        return [
            'on_time'        => $onTime,
            'late'           => $late,
            'open_overdue'   => $openOverdue,
            'discipline_pct' => $done->count() ? (int) round($onTime / $done->count() * 100) : null,
            'executors'      => $executors,
        ];
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** Диапазон дат: из фильтра либо последние 12 месяцев. @return array{0: Carbon, 1: Carbon} */
    private function range(array $f): array
    {
        $end = ! empty($f['to']) ? Carbon::parse($f['to']) : now();
        $start = ! empty($f['from']) ? Carbon::parse($f['from']) : $end->copy()->subMonths(11)->startOfMonth();

        return [$start, $end];
    }

    /** @return array<string, int> ['Y-m' => count] */
    private function monthlyCounts(Builder $query, string $col, Carbon $start, Carbon $end): array
    {
        return (clone $query)
            ->whereBetween($col, [$start->copy()->startOfMonth(), $end->copy()->endOfMonth()])
            ->selectRaw("DATE_FORMAT($col, '%Y-%m') m, COUNT(*) c")
            ->groupBy('m')->pluck('c', 'm')->toArray();
    }

    /** % звеньев, закрытых в срок (среди тех, где срок задан). */
    private function onTimePct($stages): ?int
    {
        $withDl = $stages->filter(fn ($s) => $s->deadline_at);
        if ($withDl->isEmpty()) {
            return null;
        }

        return (int) round($withDl->filter(fn ($s) => $s->completed_at <= $s->deadline_at)->count() / $withDl->count() * 100);
    }

    private function stateBase(string $state, array $f): Builder
    {
        $q = $this->baseDocuments($f);

        if ($state === 'in_work') {
            $q->whereIn('status', ['draft', 'requires_changes']);
        } elseif ($state === 'on_ack') {
            $q->whereHas('approvals.stages', fn ($s) => $s->where('status', 'in_progress')
                ->whereHas('workflowStage', fn ($w) => $w->where('phase', 'ack')));
        } elseif ($state === 'on_approval') {
            $q->whereHas('approvals.stages', fn ($s) => $s->where('status', 'in_progress')
                ->whereHas('workflowStage', fn ($w) => $w->where(fn ($x) => $x->where('phase', '!=', 'ack')->orWhereNull('phase'))));
        } else { // overdue
            $q->whereHas('approvals.stages', fn ($s) => $s->where('status', 'in_progress')->where('deadline_at', '<', now()));
        }

        return $q;
    }

    /** @return array<int|string, int> [dimensionKey => count] */
    private function countMap(Builder $base, string $groupBy): array
    {
        if ($groupBy === 'type') {
            return (clone $base)->selectRaw('document_type_id as k, COUNT(*) c')
                ->groupBy('document_type_id')->pluck('c', 'k')->toArray();
        }

        return (clone $base)->join('users', 'documents.initiator_id', '=', 'users.id')
            ->selectRaw('users.department_id as k, COUNT(DISTINCT documents.id) c')
            ->groupBy('users.department_id')->pluck('c', 'k')->toArray();
    }

    private function baseDocuments(array $f): Builder
    {
        return $this->applyDocFilters(Document::query(), $f);
    }

    private function applyDocFilters(Builder $q, array $f): Builder
    {
        if (! empty($f['type'])) {
            $q->where('document_type_id', $f['type']);
        }
        if (! empty($f['from'])) {
            $q->whereDate('created_at', '>=', $f['from']);
        }
        if (! empty($f['to'])) {
            $q->whereDate('created_at', '<=', $f['to']);
        }
        $scope = $f['department'] ?? $f['direction'] ?? null;
        if ($scope) {
            $ids = Department::getDescendantIds((int) $scope);
            $q->whereHas('initiator', fn ($u) => $u->whereIn('department_id', $ids));
        }

        return $q;
    }

    /** Ключи → человекочитаемые подписи для выбранного разреза. */
    private function dimensionLabels(string $dimension, array $maps): array
    {
        $keys = collect($maps)->flatMap(fn ($m) => array_keys($m))->unique()->all();

        if ($dimension === 'type') {
            $names = DocumentType::whereIn('id', array_filter($keys))->pluck('name', 'id');
            $labels = [];
            foreach ($keys as $k) {
                $labels[$k] = $k ? ($names[$k] ?? 'Тип #' . $k) : 'Без типа';
            }
            return $labels;
        }

        $names = Department::whereIn('id', array_filter($keys))->pluck('name', 'id');
        $noneLabel = $dimension === 'direction' ? 'Без направления' : 'Без отдела';
        $labels = [];
        foreach ($keys as $k) {
            $labels[$k] = $k ? ($names[$k] ?? 'Отдел #' . $k) : $noneLabel;
        }
        return $labels;
    }

    /** Замыкание dept id → id корневого направления (один проход по дереву). */
    private function deptRootResolver(): callable
    {
        $parents = Department::pluck('parent_id', 'id')->toArray();

        return function (int $id) use ($parents): int {
            while (! empty($parents[$id])) {
                $id = (int) $parents[$id];
            }
            return $id;
        };
    }
}
