<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Правила проверок (ТЗ 20). Круг инициаторов узкий (аппарат управления, ГД,
 * контрольные органы) — они и так орг-широкие, поэтому проверять можно любые
 * звенья. Подпроверки-запросы данных создаёт исполнитель узла.
 */
class InspectionService
{
    private const MAX_DEPTH = 5;

    /** Может ли пользователь инициировать корневые проверки. */
    public function canInitiate(User $user): bool
    {
        return $user->hasMatrixPermission('inspections.issue');
    }

    /** Может ли исполнитель узла создать подпроверку-запрос данных. */
    public function canSubRequest(User $user, Inspection $node): bool
    {
        return ($node->executor_id === $user->id || $user->isAdmin())
            && $node->depth < self::MAX_DEPTH
            && $node->status !== 'done';
    }

    /**
     * Кого можно назначить проверяющим/адресатом запроса — все активные сотрудники
     * (кроме себя). Область не сужается: проверки инициируют орг-широкие роли.
     *
     * @return Collection<int, User>
     */
    public function assignableExecutors(User $initiator): Collection
    {
        return User::where('is_active', true)
            ->where('id', '!=', $initiator->id)
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'department_id']);
    }
}
