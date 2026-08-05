<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentWatcher;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        // Наблюдатель видит документы своих целей (но действовать не может).
        if ($this->isWatching($user, $document)) {
            return true;
        }

        // Доступ уровня «department»: документы отделов своего направления
        // (с учётом кросс-видимости) — так же, как их показывает список.
        if ($this->withinDepartmentScope($user, $document)) {
            return true;
        }

        // Anyone delegated to in any approval stage can view
        $isDelegatee = $document->approvals()
            ->whereHas('stages.decisions', fn($q) => $q
                ->where('action', 'delegate')
                ->where('delegated_to', $user->id))
            ->exists();
        if ($isDelegatee) {
            return true;
        }

        return match($user->role) {
            'admin'    => true,
            'director' => $document->initiator->department_id === $user->department_id
                          || $document->approvals()
                              ->whereHas('stages.decisions', fn($q) => $q->where('user_id', $user->id))
                              ->exists()
                          || $document->approvals()
                              ->whereHas('stages.workflowStage.approvers', fn($q) => $q->where('approver_id', $user->id))
                              ->exists(),
            'linear'   => $document->initiator_id === $user->id
                          || $document->approvals()
                              ->whereHas('stages', fn($q) => $q->whereHas('workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id)))
                              ->exists()
                          || $document->approvals()
                              ->whereHas('stages.decisions', fn($q) => $q->where('user_id', $user->id))
                              ->exists(),
            'archiver' => in_array($document->status, ['approved', 'signed', 'archived']),
            'external' => $document->initiator_id === $user->id
                          || $document->approvals()
                              ->whereHas('stages.workflowStage.approvers', fn($q) => $q->where('approver_id', $user->id))
                              ->exists()
                          || $document->approvals()
                              ->whereHas('stages.decisions', fn($q) => $q->where('user_id', $user->id))
                              ->exists(),
            default    => false,
        };
    }

    /** Документ инициирован в пределах доступной пользователю области отделов. */
    private function withinDepartmentScope(User $user, Document $document): bool
    {
        if ($user->resolveWorkflowAccess() !== 'department' || !$user->department_id) {
            return false;
        }

        $initiatorDeptId = $document->initiator->department_id ?? null;
        if ($initiatorDeptId === null) {
            return false;
        }

        return in_array($initiatorDeptId, Department::visibleScopeIds($user->department_id), true);
    }

    /** Наблюдает ли пользователь за участником этого документа. */
    private function isWatching(User $user, Document $document): bool
    {
        $rules = DocumentWatcher::where('watcher_id', $user->id)->get(['target_id', 'scope']);

        foreach ($rules as $rule) {
            $targetId = $rule->target_id;

            if ($rule->scope !== 'approver' && $document->initiator_id === $targetId) {
                return true;
            }

            if ($rule->scope !== 'initiator'
                && $document->approvals()
                    ->whereHas('stages.workflowStage.approvers', fn ($q) => $q->where('approver_id', $targetId))
                    ->exists()) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'director', 'linear', 'external']);
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return $document->initiator_id === $user->id
            && in_array($document->status, ['draft', 'requires_changes']);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isAdmin()
            || ($document->initiator_id === $user->id && $document->status === 'draft');
    }

    public function cancelApproval(User $user, Document $document): bool
    {
        if (in_array($document->status, ['approved', 'rejected'])) {
            return false;
        }

        return $user->isAdmin()
            || ($document->initiator_id === $user->id && $document->status === 'in_review');
    }

    public function approve(User $user, Document $document): bool
    {
        $activeStage = $document->activeApproval?->activeStage();

        if (!$activeStage) {
            // Ознакомление не держит маршрут: согласование закрыто, но участник
            // с незакрытой задачей ознакомления всё ещё вправе отметиться.
            return $document->awaitingAck()
                && $document->myPendingAckStage($user->id) !== null;
        }

        // Direct approver
        if ($activeStage->workflowStage->approvers()->where('approver_id', $user->id)->exists()) {
            return true;
        }

        // Delegated approver
        return $activeStage->decisions()
            ->where('action', 'delegate')
            ->where('delegated_to', $user->id)
            ->exists();
    }
}
