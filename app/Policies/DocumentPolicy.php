<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        return match($user->role) {
            'admin'    => true,
            'director' => $document->initiator->department_id === $user->department_id
                          || $document->approvals()
                              ->whereHas('stages.decisions', fn($q) => $q->where('user_id', $user->id))
                              ->exists(),
            'linear'   => $document->initiator_id === $user->id
                          || $document->approvals()
                              ->whereHas('stages', fn($q) => $q->whereHas('workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id)))
                              ->exists(),
            'archiver' => in_array($document->status, ['approved', 'signed', 'archived']),
            default    => false,
        };
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'linear']);
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return $document->initiator_id === $user->id
            && in_array($document->status, ['draft', 'requires_changes']);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->role === 'admin'
            || ($document->initiator_id === $user->id && $document->status === 'draft');
    }

    public function approve(User $user, Document $document): bool
    {
        if (!$document->activeApproval) {
            return false;
        }

        $activeStage = $document->activeApproval->activeStage();
        if (!$activeStage) {
            return false;
        }

        return $activeStage->workflowStage->approvers()
            ->where('approver_id', $user->id)
            ->exists();
    }
}
