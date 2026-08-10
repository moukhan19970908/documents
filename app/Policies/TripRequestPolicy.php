<?php

namespace App\Policies;

use App\Models\TripRequest;
use App\Models\User;

class TripRequestPolicy
{
    public function view(User $user, TripRequest $trip): bool
    {
        if ($user->hasAnyRole(['admin', 'director'])) {
            return true;
        }
        if ($trip->user_id === $user->id) {
            return true;
        }
        // Согласующий из маршрута (в т.ч. как замещающий отсутствующего) может открыть заявку.
        if ($this->isRouteApprover($user, $trip)) {
            return true;
        }
        $ids = array_merge($user->directSubordinateIds(), $user->allSubordinateIds());
        return in_array($trip->user_id, $ids);
    }

    /** Является ли пользователь согласующим на любом шаге маршрута — сам или как замещающий. */
    private function isRouteApprover(User $user, TripRequest $trip): bool
    {
        if (! $trip->route) {
            return false;
        }
        $approverIds = $trip->route->steps->pluck('approver_user_id')->filter()->all();

        return in_array($user->id, $approverIds, true)
            || (bool) array_intersect($approverIds, \App\Models\Substitution::coveredUserIds($user));
    }

    public function update(User $user, TripRequest $trip): bool
    {
        return $trip->user_id === $user->id
            && in_array($trip->status, ['draft', 'revision']);
    }

    public function delete(User $user, TripRequest $trip): bool
    {
        return ($trip->user_id === $user->id && in_array($trip->status, ['draft', 'pending']))
            || $user->isAdmin();
    }
}
