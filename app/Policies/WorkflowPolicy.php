<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;

class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director']);
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $user->isAdmin()
            || ($workflow->created_by === $user->id && !$workflow->is_system);
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->isAdmin() && !$workflow->is_system;
    }
}
