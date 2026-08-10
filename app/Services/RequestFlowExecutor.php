<?php

namespace App\Services;

use App\Models\Department;
use App\Models\RequestFlow;
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
                    $u = $this->resolveRole($cfg['group_role'] ?? null);
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
     * Ветка «условия по инициатору»: первая, чьим критериям соответствует инициатор.
     * Критерии: уровень роли (0 — любой) И отдел/поддерево (0 — любой).
     */
    private function matchInitiator(array $branches, User $initiator): ?string
    {
        $level = $initiator->role_level ?? 1;

        foreach ($branches as $b) {
            $needLevel = (int) ($b['role_level'] ?? 0);
            $needDept  = (int) ($b['department_id'] ?? 0);

            $levelOk = $needLevel === 0 || $level >= $needLevel;
            $deptOk  = $needDept === 0
                || ($initiator->department_id
                    && in_array((int) $initiator->department_id, Department::getDescendantIds($needDept), true));

            if ($levelOk && $deptOk) {
                return $b['key'];
            }
        }

        return null;
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
     * начиная с руководителя отдела инициатора. Пропускаем самого инициатора,
     * неназначенных/неактивных руководителей и повторы. Каждый узел «по оргструктуре»
     * берёт следующего из этой цепочки.
     *
     * @return array<int, User>
     */
    private function orgHeadChain(User $initiator): array
    {
        $chain = [];
        $seen  = [];
        $dept  = $initiator->department;

        while ($dept) {
            $head = $dept->head;
            if ($head && $head->is_active && $head->id !== $initiator->id && ! isset($seen[$head->id])) {
                $chain[] = $head;
                $seen[$head->id] = true;
            }
            $dept = $dept->parent;
        }

        return $chain;
    }

    /** Согласующий по роли: первый активный пользователь с этой ролью. */
    private function resolveRole(?string $code): ?User
    {
        if (! $code) {
            return null;
        }

        return User::where('is_active', true)
            ->where(fn ($q) => $q->where('role', $code)->orWhereHas('roles', fn ($r) => $r->where('code', $code)))
            ->first();
    }
}
