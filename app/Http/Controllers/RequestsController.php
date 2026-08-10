<?php

namespace App\Http\Controllers;

use App\Models\Registry;
use App\Models\TripRequest;
use App\Models\TripTask;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\ApprovalService;
use App\Services\RegistryService;
use Illuminate\Http\Request;

/**
 * Единая страница «Заявки» (ТЗ «Раздел заявки»): хаб над модулями Отпуска / Командировки / Иное.
 * Ничего не дублирует — агрегирует существующие заявки, согласование и реестры в одном месте.
 * Вкладки: Мои заявки / На согласовании / Мои задания.
 */
class RequestsController extends Controller
{
    /** Короткая метка звена маршрута по уровню роли (когда согласующий задан ролью, а не человеком). */
    private const LEVEL_LABELS = [
        1 => 'Сотрудник',
        2 => 'Рук. отдела',
        3 => 'Рук. департамента',
        4 => 'Директор',
        5 => 'Генеральный',
    ];

    public function __construct(
        private ApprovalService $approvals,
        private RegistryService $registries,
    ) {}

    // ── Вкладка «Мои заявки» ─────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $request->user();

        $trips = TripRequest::where('user_id', $user->id)
            ->with('route.steps.approverUser')->latest()->get()
            ->map(fn ($r) => $this->mapRequest($r, 'trip'));

        $vacations = VacationRequest::where('user_id', $user->id)
            ->with('route.steps.approverUser')->latest()->get()
            ->map(fn ($r) => $this->mapRequest($r, 'vacation'));

        $myRequests = $trips->concat($vacations)->sortByDesc('at')->values();

        // Фильтр-чипы по типу — только те, что реально встречаются у пользователя.
        $typeChips = $myRequests->pluck('type_label', 'type_key')->unique()
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values();

        return view('requests.index', array_merge($this->tabBadges($user), [
            'myRequests' => $myRequests,
            'typeChips'  => $typeChips,
        ]));
    }

    // ── Вкладка «На согласовании» ────────────────────────────────────────

    public function approvals(Request $request)
    {
        $user = $request->user();

        abort_unless($this->canSeeApprovals($user), 403);

        return view('requests.approvals', array_merge($this->tabBadges($user), [
            'individual' => $this->individualQueue($user),
            'poolByDept' => $this->registryPool($user),
            'inTransit'  => $this->inTransit($user),
        ]));
    }

    // ── Вкладка «Мои задания» ────────────────────────────────────────────

    public function tasks(Request $request)
    {
        $user = $request->user();

        abort_unless($this->canSeeTasks($user), 403);

        // Замещение: замещающий видит и задания тех, кого сейчас замещает.
        $coveredIds = \App\Models\Substitution::coveredUserIds($user);

        $tasks = TripTask::with(['trip.user', 'files', 'messages.user', 'assignee', 'doneBy'])
            ->where(function ($q) use ($user, $coveredIds) {
                $q->where('assignee_id', $user->id)->orWhereIn('assignee_id', $coveredIds);
                if ($user->isAdmin()) {
                    $q->orWhereNull('assignee_id');
                }
            })
            ->orderByRaw("FIELD(status, 'pending', 'in_progress', 'done')")
            ->latest('id')
            ->get();

        return view('requests.tasks', array_merge($this->tabBadges($user), [
            'tasks' => $tasks,
        ]));
    }

    /**
     * Формирование реестра из пула (ТЗ 18): собранные заявки уходят в реестр.
     * Пул может содержать оба типа — создаём отдельный реестр на каждый тип.
     */
    public function storeRegistry(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isManager(), 403);

        $data = $request->validate([
            'trip_ids'       => ['nullable', 'array'],
            'trip_ids.*'     => ['integer', 'exists:trip_requests,id'],
            'vacation_ids'   => ['nullable', 'array'],
            'vacation_ids.*' => ['integer', 'exists:vacation_requests,id'],
        ]);

        $tripIds = $data['trip_ids'] ?? [];
        $vacIds  = $data['vacation_ids'] ?? [];

        if (empty($tripIds) && empty($vacIds)) {
            return back()->with('error', 'Выберите хотя бы одну заявку для реестра.');
        }

        $created = [];
        $date    = now()->format('d.m.Y');

        if (! empty($tripIds)) {
            $created[] = $this->registries->createTripRegistry($user, "Реестр командировок — {$date}", $tripIds);
        }

        if (! empty($vacIds)) {
            $created[] = $this->registries->createVacationRegistry($user, "Реестр отпусков — {$date}", $vacIds);
        }

        // Один реестр — открываем его для передачи; несколько — возвращаемся в список.
        if (count($created) === 1) {
            $reg = $created[0];
            $show = $reg->type === 'trip'
                ? route('trips.registries.show', $reg)
                : route('vacations.registries.show', $reg);

            return redirect($show)->with('success', 'Реестр сформирован. Проверьте состав и передайте в бухгалтерию.');
        }

        return redirect()->route('requests.approvals')
            ->with('success', 'Реестры сформированы (по одному на каждый тип). Откройте каждый и передайте в бухгалтерию.');
    }

    // ── Данные вкладок ───────────────────────────────────────────────────

    /** Индивидуальные заявки, ожидающие решения текущего пользователя (командировки + отпуска). */
    private function individualQueue(User $user): \Illuminate\Support\Collection
    {
        $trips = $this->approvals->getPendingForApprover($user, 'trip')
            ->map(fn ($r) => $this->mapQueueRow($r, 'trip'));

        $vacations = $this->approvals->getPendingForApprover($user, 'vacation')
            ->map(fn ($r) => $this->mapQueueRow($r, 'vacation'));

        return $trips->concat($vacations)->sortByDesc('at')->values();
    }

    private function mapQueueRow(TripRequest|VacationRequest $r, string $kind): array
    {
        $isTrip = $kind === 'trip';

        return [
            'kind'        => $kind,
            'type_label'  => $isTrip ? 'Командировка' : ($r->vacation_type === 'sick_leave' ? 'Больничный' : 'Отпуск'),
            'initiator'   => $r->user?->name ?? '—',
            'position'    => $r->user?->position ?: ($r->user?->department?->name ?? ''),
            'summary'     => $isTrip
                                ? ('Командировка' . ($r->city ? ', ' . $r->city : '') . ' — ' . ($r->days_count ?? '?') . ' дн.')
                                : ($r->vacation_type_label . ', ' . $r->date_start?->format('d.m') . '–' . $r->date_end?->format('d.m')),
            'approve_url' => $isTrip ? route('trips.approve', $r) : route('vacations.approve', $r),
            'reject_url'  => $isTrip ? route('trips.reject', $r)  : route('vacations.reject', $r),
            'show_url'    => $isTrip ? route('trips.show', $r)    : route('vacations.show', $r),
            'at'          => $r->created_at,
        ];
    }

    /**
     * Пул для реестра: согласованные заявки ТОЛЬКО линейных сотрудников (руководители идут
     * индивидуальным маршрутом), ещё не в активном реестре. Сгруппированы по отделу инициатора.
     */
    private function registryPool(User $user): \Illuminate\Support\Collection
    {
        $linear = fn ($q) => $q
            ->where(fn ($qq) => $qq->whereNull('role_level')->orWhere('role_level', '<', 2))
            ->whereNotIn('role', ['admin', 'director', 'ceo', 'chief_of_staff']);

        $trips = TripRequest::where('status', 'approved')
            ->whereDoesntHave('registryItem', fn ($q) => $q->where('status', 'active'))
            ->whereHas('user', $linear)->visibleBy($user)
            ->with('user.department')->get()
            ->map(fn ($r) => $this->mapPoolRow($r, 'trip'));

        $vacations = VacationRequest::where('status', 'approved')
            ->whereDoesntHave('registryItem', fn ($q) => $q->where('status', 'active'))
            ->whereHas('user', $linear)->visibleBy($user)
            ->with('user.department')->get()
            ->map(fn ($r) => $this->mapPoolRow($r, 'vacation'));

        return $trips->concat($vacations)
            ->groupBy('dept')
            ->map(fn ($items, $dept) => ['dept' => $dept ?: 'Без отдела', 'items' => $items->values()])
            ->values();
    }

    private function mapPoolRow(TripRequest|VacationRequest $r, string $kind): array
    {
        $isTrip = $kind === 'trip';

        return [
            'kind'       => $kind,
            'id'         => $r->id,
            'dept'       => $r->user?->department?->name,
            'name'       => $r->user?->name ?? '—',
            'position'   => $r->user?->position ?: '',
            'type_label' => $isTrip ? 'Командировка' : ($r->vacation_type === 'sick_leave' ? 'Больничный' : 'Отпуск'),
            'summary'    => $isTrip
                                ? (($r->city ?: 'Командировка') . ', ' . ($r->days_count ?? '?') . ' дн.')
                                : ($r->date_start?->format('d.m') . '–' . $r->date_end?->format('d.m') . ' · ' . ($r->days_count ?? '?') . ' дн.'),
            'submitted'  => 'подана ' . $r->created_at->format('d.m'),
        ];
    }

    /** Реестры: входящие на решение текущему пользователю + мои реестры в пути. */
    private function inTransit(User $user): array
    {
        $map = fn (Registry $reg, string $role) => [
            'id'         => $reg->id,
            'type'       => $reg->type,
            'type_label' => $reg->type === 'trip' ? 'Командировки' : 'Отпуска',
            'title'      => $reg->title ?: 'Реестр #' . $reg->id,
            'count'      => $reg->items->where('status', 'active')->count(),
            'status'     => $reg->status_label,
            'color'      => $reg->status_color,
            'creator'    => $reg->creator?->name ?? '',
            'url'        => $reg->type === 'trip'
                                ? route('trips.registries.show', $reg)
                                : route('vacations.registries.show', $reg),
            'role'       => $role,
        ];

        $incoming = $this->approvals->getPendingRegistriesForApprover($user, 'trip')
            ->concat($this->approvals->getPendingRegistriesForApprover($user, 'vacation'))
            ->map(fn ($reg) => $map($reg, 'decide'));

        $mine = Registry::where('created_by', $user->id)
            ->whereIn('status', ['pending', 'draft'])
            ->with(['items', 'creator'])->latest()->get()
            ->map(fn ($reg) => $map($reg, 'mine'));

        return ['incoming' => $incoming->values(), 'mine' => $mine->values()];
    }

    // ── Общее ────────────────────────────────────────────────────────────

    /** Счётчики для верхних вкладок + признаки доступа к вкладкам (матрица прав). */
    private function tabBadges(User $user): array
    {
        $canSeeApprovals = $this->canSeeApprovals($user);
        $canSeeTasks     = $this->canSeeTasks($user);

        $pending = [
            'trip'         => $canSeeApprovals ? $this->approvals->getPendingForApprover($user, 'trip')->count() : 0,
            'vacation'     => $canSeeApprovals ? $this->approvals->getPendingForApprover($user, 'vacation')->count() : 0,
            'trip_reg'     => $canSeeApprovals && $user->isManager() ? $this->approvals->getPendingRegistriesForApprover($user, 'trip')->count() : 0,
            'vacation_reg' => $canSeeApprovals && $user->isManager() ? $this->approvals->getPendingRegistriesForApprover($user, 'vacation')->count() : 0,
        ];

        // Замещение: кого пользователь сейчас замещает (для баннера) и его задания в счётчике.
        $coveringFor = \App\Models\Substitution::activeOn()
            ->where('deputy_user_id', $user->id)->with('absent')->get();
        $coveredIds = $coveringFor->pluck('absent_user_id')->all();

        return [
            'canSeeApprovals' => $canSeeApprovals,
            'canSeeTasks'     => $canSeeTasks,
            'pendingTotal'    => array_sum($pending),
            'coveringFor'     => $coveringFor,
            'tasksOpen'       => $canSeeTasks
                ? TripTask::where(fn ($q) => $q->where('assignee_id', $user->id)->orWhereIn('assignee_id', $coveredIds))
                    ->where('status', '!=', 'done')->count()
                : 0,
            'counts'          => [
                'total' => TripRequest::where('user_id', $user->id)->count()
                         + VacationRequest::where('user_id', $user->id)->count(),
            ],
        ];
    }

    /** «На согласовании» — тем, кто согласует командировки или отпуска (матрица прав, не линейным). */
    private function canSeeApprovals(User $user): bool
    {
        return $user->canSeeMenu('menu.trips.approvals') || $user->canSeeMenu('menu.vacations.approvals');
    }

    /** «Мои задания» — исполнителям заданий (матрица прав, ключ «Задания»). */
    private function canSeeTasks(User $user): bool
    {
        return $user->canSeeMenu('menu.processes.jobs');
    }

    /** Единый формат карточки заявки для списка «Мои заявки» + шаги «цикла движения». */
    private function mapRequest(TripRequest|VacationRequest $r, string $kind): array
    {
        $isTrip = $kind === 'trip';

        if ($isTrip) {
            $typeKey   = 'trip';
            $typeLabel = 'Командировка';
            $title     = 'Командировка' . ($r->city ? ' — ' . $r->city : '');
            $url       = route('trips.show', $r);
        } else {
            $sick      = $r->vacation_type === 'sick_leave';
            $typeKey   = $sick ? 'sick' : 'vacation';
            $typeLabel = $sick ? 'Больничный' : 'Отпуск';
            $title     = ($sick ? 'Больничный' : 'Отпуск') . ' — ' . $r->vacation_type_label;
            $url       = route('vacations.show', $r);
        }

        return [
            'kind'        => $kind,
            'type_key'    => $typeKey,
            'type_label'  => $typeLabel,
            'title'       => $title,
            'dates'       => $r->date_start?->format('d.m.Y') . ' – ' . $r->date_end?->format('d.m.Y'),
            'submitted'   => $r->status === 'draft'
                                ? '— (не отправлена)'
                                : 'Подана ' . $r->created_at->format('d.m'),
            'stage_pos'   => $r->status === 'draft' ? 0 : $r->current_step,
            'stage_total' => ($r->route?->steps->count() ?? 0),
            'steps'       => $this->buildSteps($r),
            'status'      => $r->status_label,
            'color'       => $r->status_color,
            'indicator'   => $this->indicatorColor($r->status),
            'state_group' => in_array($r->status, ['approved', 'rejected'], true) ? 'done' : 'active',
            'url'         => $url,
            'at'          => $r->created_at,
        ];
    }

    /** Шаги «цикла движения»: «Подача» + звенья маршрута, каждое со статусом done/current/pending. */
    private function buildSteps(TripRequest|VacationRequest $r): array
    {
        $submitted = $r->status !== 'draft';

        $steps = [[
            'label' => 'Подача',
            'state' => $submitted ? 'done' : 'current',
        ]];

        foreach ($r->route?->steps ?? [] as $step) {
            // Согласованная заявка — все звенья пройдены; иначе пройдены те, что до текущего.
            $done    = $step->step_order < $r->current_step || $r->status === 'approved';
            $current = ! $done && $step->step_order === $r->current_step
                && in_array($r->status, ['pending', 'revision', 'rejected', 'in_registry'], true);

            $steps[] = [
                'label' => $step->approver_role_level
                    ? (self::LEVEL_LABELS[$step->approver_role_level] ?? 'Уровень ' . $step->approver_role_level)
                    : ($step->approverUser?->name ?? 'Согласующий'),
                'state' => $done ? 'done' : ($current ? 'current' : 'pending'),
            ];
        }

        return $steps;
    }

    /** Цвет индикатора цикла по статусу заявки. */
    private function indicatorColor(string $status): string
    {
        return match ($status) {
            'pending'     => 'blue',
            'approved'    => 'green',
            'revision'    => 'orange',
            'rejected'    => 'red',
            'in_registry' => 'blue',
            default       => 'gray',
        };
    }
}
