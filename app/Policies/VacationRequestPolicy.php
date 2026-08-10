<?php

namespace App\Policies;

use App\Models\VacationRequest;
use App\Models\User;

class VacationRequestPolicy
{
    public function view(User $user, VacationRequest $vacation): bool
    {
        if ($user->hasAnyRole(['admin', 'director'])) {
            return true;
        }
        if ($vacation->user_id === $user->id) {
            return true;
        }
        // Согласующий из маршрута (в т.ч. как замещающий отсутствующего) может открыть заявку.
        if ($this->isRouteApprover($user, $vacation)) {
            return true;
        }
        $ids = array_merge($user->directSubordinateIds(), $user->allSubordinateIds());
        return in_array($vacation->user_id, $ids);
    }

    /** Является ли пользователь согласующим на любом шаге маршрута — сам или как замещающий. */
    private function isRouteApprover(User $user, VacationRequest $vacation): bool
    {
        if (! $vacation->route) {
            return false;
        }
        $approverIds = $vacation->route->steps->pluck('approver_user_id')->filter()->all();

        return in_array($user->id, $approverIds, true)
            || (bool) array_intersect($approverIds, \App\Models\Substitution::coveredUserIds($user));
    }

    public function update(User $user, VacationRequest $vacation): bool
    {
        return $vacation->user_id === $user->id
            && in_array($vacation->status, ['draft', 'revision']);
    }

    public function delete(User $user, VacationRequest $vacation): bool
    {
        return ($vacation->user_id === $user->id && in_array($vacation->status, ['draft', 'pending']))
            || $user->isAdmin();
    }
}
