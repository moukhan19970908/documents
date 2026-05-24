<?php

namespace App\Policies;

use App\Models\TripRequest;
use App\Models\User;

class TripRequestPolicy
{
    public function view(User $user, TripRequest $trip): bool
    {
        if ($user->isAdmin() || $user->role === 'director') {
            return true;
        }
        if ($trip->user_id === $user->id) {
            return true;
        }
        $ids = array_merge($user->directSubordinateIds(), $user->allSubordinateIds());
        return in_array($trip->user_id, $ids);
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
