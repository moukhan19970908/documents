<?php

namespace App\Policies;

use App\Models\VacationRequest;
use App\Models\User;

class VacationRequestPolicy
{
    public function view(User $user, VacationRequest $vacation): bool
    {
        if ($user->isAdmin() || $user->role === 'director') {
            return true;
        }
        if ($vacation->user_id === $user->id) {
            return true;
        }
        $ids = array_merge($user->directSubordinateIds(), $user->allSubordinateIds());
        return in_array($vacation->user_id, $ids);
    }

    public function update(User $user, VacationRequest $vacation): bool
    {
        return $vacation->user_id === $user->id
            && in_array($vacation->status, ['draft', 'revision']);
    }

    public function delete(User $user, VacationRequest $vacation): bool
    {
        return ($vacation->user_id === $user->id && $vacation->status === 'draft')
            || $user->isAdmin();
    }
}
