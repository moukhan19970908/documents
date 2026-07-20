<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSetting;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Правила постановки поручений (ТЗ 17.1). Значения берутся из настроек
 * (страница «Правила поручений»), для орг-широких ролей область всегда «вся организация».
 *
 *   • Область постановки: локальные руководители — по настройке (подчинённые /
 *     направление / организация); ГД / аппарат / админ — всегда на любых.
 *   • Подпоручения — вкл/выкл настройкой, своя (обычно более широкая) область.
 *   • Максимальная глубина дерева — настройка.
 *   • Приёмка на каждом узле — обязательна (реализовано в модели/контроллере).
 */
class AssignmentService
{
    /** Роли с орг-широкой областью постановки — на ЛЮБЫХ сотрудников. */
    private const ORG_WIDE_ROLES = ['ceo', 'chief_of_staff', 'admin'];

    /** Может ли пользователь ставить корневые поручения. */
    public function canInitiate(User $user): bool
    {
        return $user->hasMatrixPermission('assignments.issue');
    }

    /** Может ли исполнитель узла породить подпоручение. */
    public function canSubAssign(User $user, Assignment $node): bool
    {
        $settings = AssignmentSetting::current();

        return $settings->allow_subassignments
            && $node->executor_id === $user->id
            && $node->depth < $settings->max_depth
            && $node->status !== 'done';
    }

    private function isOrgWide(User $user): bool
    {
        return $user->hasAnyRole(self::ORG_WIDE_ROLES);
    }

    /**
     * Кого пользователь может назначить исполнителем.
     *   $isSub = true — подпоручение (область sub_scope), иначе корень (manager_scope).
     *
     * @return Collection<int, User>
     */
    public function assignableExecutors(User $initiator, bool $isSub = false): Collection
    {
        $settings = AssignmentSetting::current();
        $scope = $this->isOrgWide($initiator)
            ? 'organization'
            : ($isSub ? $settings->sub_scope : $settings->manager_scope);

        $base = User::where('is_active', true)
            ->where('id', '!=', $initiator->id)
            ->orderBy('name');

        if ($scope === 'organization') {
            return $base->get(['id', 'name', 'position', 'department_id']);
        }

        $ids = $this->scopeUserIds($initiator, $scope);

        return $base->whereIn('id', $ids)->get(['id', 'name', 'position', 'department_id']);
    }

    /** ID сотрудников в области «подчинённые» или «своё направление». */
    private function scopeUserIds(User $initiator, string $scope): array
    {
        if ($scope === 'direction' && $initiator->department_id) {
            // Всё направление: поддерево корневого отдела.
            $rootId  = Department::directionRootId($initiator->department_id);
            $deptIds = Department::getDescendantIds($rootId);

            return User::whereIn('department_id', $deptIds)
                ->where('id', '!=', $initiator->id)->pluck('id')->all();
        }

        // subordinates: прямые/косвенные подчинённые + поддерево своего отдела.
        $ids = $initiator->allSubordinateIds();

        if ($initiator->department_id) {
            $deptIds = Department::getDescendantIds($initiator->department_id);
            $ids = array_merge($ids, User::whereIn('department_id', $deptIds)->pluck('id')->all());
        }

        return array_values(array_unique(array_diff($ids, [$initiator->id])));
    }
}
