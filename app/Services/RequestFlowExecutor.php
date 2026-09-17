<?php

namespace App\Services;

use App\Models\Department;
use App\Models\RequestFlow;
use App\Models\Role;
use App\Models\User;

/**
 * Исполнитель граф-процесса заявки (ТЗ «Конструктор заявок»).
 *
 * Прогоняет заявку по нарисованному графу и собирает план исполнения: упорядоченную
 * цепочку согласующих (по роли / по оргструктуре) и порождаемые задания — с учётом
 * веток по инициатору (роль+отдел) и по полям формы. Одна схема работает на все отделы:
 * согласующих «по оргструктуре» движок находит сам, поднимаясь по руководителям отделов.
 */
class RequestFlowExecutor
{
    /**
     * Роль, которую в цепочке «по оргструктуре» пропускаем: руководитель аппарата
     * управления согласует как роль (отдельный узел), а не как начальник по дереву.
     */
    private const SKIP_IN_ORG_CHAIN = 'chief_of_staff';

    private ?int $ceoDeptId = null;

    private bool $ceoDeptLoaded = false;

    /** @var array<int, Department>|null */
    private ?array $departments = null;

    public function __construct(private RequestFlowService $flows) {}

    /**
     * План исполнения для заявки инициатора с ответами на поля формы.
     *
     * @return array{approvals: array<int, array>, tasks: array<int, array>, notifications: array<int, array>, terminal: ?string}
     */
    public function resolve(RequestFlow $flow, User $initiator, array $fields = []): array
    {
        $plan = ['approvals' => [], 'tasks' => [], 'notifications' => [], 'terminal' => null];

        // Цепочка руководителей отделов вверх по дереву — каждый узел «по оргструктуре»
        // берёт следующего из неё. Курсор общий на весь обход.
        $orgChain = $this->orgHeadChain($initiator);
        $cursor   = 0;

        $this->walk($this->flows->tree($flow), $initiator, $fields, $plan, $orgChain, $cursor);

        return $plan;
    }

    /**
     * @param array<int, User> $orgChain
     */
    private function walk(array $chain, User $initiator, array $fields, array &$plan, array $orgChain, int &$cursor): void
    {
        foreach ($chain as $node) {
            if ($plan['terminal']) {
                return;
            }

            $cfg = $node['config'] ?? [];

            switch ($node['type']) {
                case 'approver_org':
                    $u = $orgChain[$cursor] ?? null;
                    if ($u) {
                        $cursor++;
                    }
                    $plan['approvals'][] = $this->step($node, 'approver_org', $u);
                    break;

                case 'approver_role':
                    $u = $this->resolveRole($cfg['group_role'] ?? null, $initiator);
                    $plan['approvals'][] = $this->step($node, 'approver_role', $u);
                    break;

                case 'registry':
                    $plan['approvals'][] = $this->step($node, 'registry', null);
                    break;

                case 'cond_role':
                    $key = $this->matchInitiator($cfg['branches'] ?? [], $initiator);
                    if ($key !== null) {
                        $this->walk($node['branches'][$key] ?? [], $initiator, $fields, $plan, $orgChain, $cursor);
                    }
                    break;

                case 'cond_field':
                    $key = $this->matchField($cfg, $fields);
                    if ($key !== null) {
                        $this->walk($node['branches'][$key] ?? [], $initiator, $fields, $plan, $orgChain, $cursor);
                    }
                    break;

                case 'parallel':
                    // Все потоки идут одновременно — обходим каждую ветку.
                    foreach (($cfg['branches'] ?? []) as $b) {
                        $this->walk($node['branches'][$b['key']] ?? [], $initiator, $fields, $plan, $orgChain, $cursor);
                    }
                    break;

                case 'task':
                    $plan['tasks'][] = [
                        'title'    => $cfg['title'] ?: $node['name'],
                        'assignee' => $cfg['assignee'] ?? null,
                    ];
                    break;

                case 'notify':
                    $plan['notifications'][] = ['text' => $cfg['text'] ?? '', 'recipients' => $cfg['recipients'] ?? 'initiator'];
                    break;

                case 'success':
                    $plan['terminal'] = 'success';
                    return;

                case 'reject':
                    $plan['terminal'] = 'reject';
                    return;
            }
        }
    }

    private function step(array $node, string $type, ?User $u): array
    {
        return [
            'label'         => $node['name'],
            'type'          => $type,
            'approver_id'   => $u?->id,
            'approver_name' => $u?->name,
        ];
    }

    /**
     * Ветка «условия по инициатору»: самая точная из подходящих. Критерии ветки —
     * уровень (0 — любой) И отдел/поддерево (0 — любой); подходящих может быть
     * несколько (порог «не ниже»), выигрывает ветка с отделом, затем с наибольшим
     * порогом уровня. Иначе инициатор любого ранга падал бы в первую ветку списка.
     */
    private function matchInitiator(array $branches, User $initiator): ?string
    {
        $level    = $this->initiatorLevel($initiator);
        $bestKey  = null;
        $bestRank = -1;

        foreach ($branches as $b) {
            $needLevel = (int) ($b['role_level'] ?? 0);
            $needDept  = (int) ($b['department_id'] ?? 0);

            $levelOk = $needLevel === 0 || $level >= $needLevel;
            $deptOk  = $needDept === 0
                || ($initiator->department_id
                    && in_array((int) $initiator->department_id, Department::getDescendantIds($needDept), true));

            if (! $levelOk || ! $deptOk) {
                continue;
            }

            $rank = ($needDept > 0 ? 100 : 0) + $needLevel;
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $bestKey  = $b['key'];
            }
        }

        return $bestKey;
    }

    /**
     * Уровень инициатора для выбора ветки. Считаем по оргструктуре, а не по полю
     * `role_level`: оно приходит из Битрикса и месту в дереве не соответствует.
     * Уровень — по отделу, которым человек руководит (если руководит несколькими,
     * берём высший); кто никем не руководит — по назначенным ролям.
     */
    private function initiatorLevel(User $initiator): int
    {
        $levels = Department::where('head_user_id', $initiator->id)
            ->pluck('id')
            ->map(fn ($id) => $this->departmentLevel((int) $id))
            ->filter()
            ->all();

        if ($levels) {
            return max($levels);
        }

        // «Администратор» — техническая роль, а не позиция в структуре: в уровень не идёт.
        $codes = array_diff($initiator->roleCodes(), ['admin']);

        return max(
            (int) ($initiator->role_level ?? 1),
            $codes ? (int) Role::whereIn('code', $codes)->max('level') : 1,
        );
    }

    /**
     * Уровень руководителя отдела по месту отдела в дереве, считая от генерального
     * директора: прямые подчинённые ГД — директора (4), под ними руководители
     * департаментов (3), ниже — руководители отделов (2). null — отдел вне ветки ГД.
     */
    private function departmentLevel(int $deptId): ?int
    {
        $ceoDept = $this->ceoDepartmentId();

        if (! $ceoDept) {
            return null;
        }

        $depts = $this->departments();
        $id    = $deptId;
        $depth = 0;

        while ($id !== null && isset($depts[$id])) {
            if ($id === $ceoDept) {
                return match ($depth) {
                    0       => 5,
                    1       => 4,
                    2       => 3,
                    default => 2,
                };
            }

            $id = $depts[$id]->parent_id !== null ? (int) $depts[$id]->parent_id : null;
            $depth++;
        }

        return null;
    }

    /** Отдел генерального директора — вершина оргструктуры для маршрутов. */
    private function ceoDepartmentId(): ?int
    {
        if (! $this->ceoDeptLoaded) {
            $this->ceoDeptLoaded = true;
            $this->ceoDeptId     = $this->resolveRole('ceo', null)?->department_id;
        }

        return $this->ceoDeptId;
    }

    /** @return array<int, Department> */
    private function departments(): array
    {
        return $this->departments ??= Department::all(['id', 'parent_id'])->keyBy('id')->all();
    }

    /** Ветка «условия по полю»: точное совпадение значения; пустое значение ветки — «иначе». */
    private function matchField(array $cfg, array $fields): ?string
    {
        $value = $fields[$cfg['field'] ?? ''] ?? null;
        $else  = null;

        foreach (($cfg['branches'] ?? []) as $b) {
            if (($b['value'] ?? '') === '') {
                $else ??= $b['key'];
            } elseif ((string) ($b['value'] ?? '') === (string) $value) {
                return $b['key'];
            }
        }

        return $else;
    }

    /**
     * Цепочка согласующих «по оргструктуре»: руководители отделов вверх по дереву,
     * начиная с руководителя отдела инициатора. Каждый узел «по оргструктуре» берёт
     * следующего из неё, то есть отсутствующее звено просто пропускается. Не берём:
     * самого инициатора, неназначенных/неактивных руководителей, повторы,
     * руководителя аппарата управления (он согласует как роль). Выше генерального
     * директора не поднимаемся — там маршрут заканчивается ролевыми узлами.
     *
     * @return array<int, User>
     */
    private function orgHeadChain(User $initiator): array
    {
        $ceoDept = $this->ceoDepartmentId();
        $chain   = [];
        $seen    = [];
        $dept    = $initiator->department;

        while ($dept) {
            if ($ceoDept && (int) $dept->id === $ceoDept) {
                break;
            }

            $head = $dept->head;
            if ($head
                && $head->is_active
                && $head->id !== $initiator->id
                && ! isset($seen[$head->id])
                && ! $head->hasRole(self::SKIP_IN_ORG_CHAIN)
            ) {
                $chain[] = $head;
                $seen[$head->id] = true;
            }
            $dept = $dept->parent;
        }

        return $chain;
    }

    /**
     * Согласующий по роли: первый активный пользователь с этой ролью, кроме самого
     * инициатора — иначе он согласовывал бы собственную заявку.
     */
    private function resolveRole(?string $code, ?User $initiator): ?User
    {
        if (! $code) {
            return null;
        }

        return User::where('is_active', true)
            ->when($initiator, fn ($q) => $q->where('id', '!=', $initiator->id))
            ->where(fn ($q) => $q->where('role', $code)->orWhereHas('roles', fn ($r) => $r->where('code', $code)))
            ->first();
    }
}
