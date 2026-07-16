<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;

/**
 * Раскрытие выбранной аудитории приказа (отделы + сотрудники) в поимённый
 * список пользователей. Отдел раскрывается вместе со всеми вложенными.
 */
class OrderAudienceService
{
    /**
     * @param  array<int>  $departmentIds
     * @param  array<int>  $userIds
     * @return array<int, int>  distinct user ids
     */
    public function resolveUserIds(array $departmentIds, array $userIds): array
    {
        $fromDepts = [];

        if (!empty($departmentIds)) {
            $allDeptIds = collect($departmentIds)
                ->flatMap(fn ($id) => Department::getDescendantIds((int) $id))
                ->unique()
                ->all();

            $fromDepts = User::where('is_active', true)
                ->whereIn('department_id', $allDeptIds)
                ->pluck('id')
                ->all();
        }

        $explicit = array_map('intval', $userIds);

        return array_values(array_unique(array_merge($fromDepts, $explicit)));
    }

    /**
     * Активные сотрудники поддерева по каждому отделу (для точного счётчика
     * адресатов на клиенте: объединение множеств снимает двойной счёт).
     *
     * @return array<int, array<int, int>>  [department_id => [user_id, ...]]
     */
    public function departmentUserIds(): array
    {
        $usersByDept = User::where('is_active', true)
            ->whereNotNull('department_id')
            ->get(['id', 'department_id'])
            ->groupBy('department_id')
            ->map(fn ($g) => $g->pluck('id')->all())
            ->all();

        $result = [];
        foreach (Department::pluck('id') as $deptId) {
            $ids = [];
            foreach (Department::getDescendantIds((int) $deptId) as $d) {
                $ids = array_merge($ids, $usersByDept[$d] ?? []);
            }
            $result[$deptId] = array_values(array_unique($ids));
        }

        return $result;
    }

    /**
     * Число активных сотрудников в отделе с учётом вложенных — по каждому отделу.
     *
     * @return array<int, int>  [department_id => count]
     */
    public function departmentCounts(): array
    {
        $usersByDept = User::where('is_active', true)
            ->whereNotNull('department_id')
            ->selectRaw('department_id, count(*) as c')
            ->groupBy('department_id')
            ->pluck('c', 'department_id')
            ->all();

        $counts = [];
        foreach (Department::pluck('id') as $deptId) {
            $counts[$deptId] = collect(Department::getDescendantIds((int) $deptId))
                ->sum(fn ($id) => $usersByDept[$id] ?? 0);
        }

        return $counts;
    }
}
