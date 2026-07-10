<?php

namespace App\Services;

use App\Models\VacationRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VacationService
{
    public function __construct(
        private ApprovalService $approvalService,
        private NotificationService $notifications,
    ) {}

    /**
     * Notify the approver responsible for the vacation's current step.
     */
    private function notifyCurrentApprover(VacationRequest $vacation): void
    {
        if ($vacation->status !== 'pending') {
            return;
        }
        $vacation->loadMissing('route.steps', 'user');
        $step = $vacation->route?->steps->firstWhere('step_order', $vacation->current_step);
        if (!$step) {
            return;
        }
        $approver = $this->approvalService->findApprover($vacation->user, $step);
        if (!$approver) {
            return;
        }
        $this->notifications->notify($approver, 'vacation_approval', [
            'title'       => 'Отпуск #' . $vacation->id,
            'vacation_id' => $vacation->id,
        ]);
    }

    public function create(User $user, array $data, bool $submit = false): VacationRequest
    {
        $route = $this->approvalService->findRoute($user, 'vacation');

        $days = Carbon::parse($data['date_start'])->diffInDays($data['date_end']) + 1;

        $firstStep   = $route?->steps()->orderBy('step_order')->first();
        $signatoryId = $firstStep
            ? $this->approvalService->findApprover($user, $firstStep)?->id
            : null;

        $vacation = DB::transaction(function () use ($user, $data, $route, $days, $submit, $signatoryId) {
            $vacation = VacationRequest::create([
                'user_id'       => $user->id,
                'signatory_id'  => $signatoryId,
                'route_id'      => $route?->id,
                'current_step'  => 1,
                'status'        => $submit ? 'pending' : 'draft',
                'vacation_type' => $data['vacation_type'] ?? 'annual',
                'date_start'    => $data['date_start'],
                'date_end'      => $data['date_end'],
                'days_count'    => $days,
                'comment'       => $data['comment'] ?? null,
            ]);

            if ($submit) {
                $this->approvalService->log('vacation', $vacation->id, 1, $user->id, 'submitted');
            }

            return $vacation;
        });

        if ($submit) {
            $this->notifyCurrentApprover($vacation);
        }

        return $vacation;
    }

    public function submit(VacationRequest $vacation): void
    {
        $vacation->update(['status' => 'pending', 'current_step' => 1]);
        $this->approvalService->log('vacation', $vacation->id, 1, $vacation->user_id, 'submitted');
        $this->notifyCurrentApprover($vacation);
    }

    public function approve(VacationRequest $vacation, User $approver, ?string $comment = null): void
    {
        DB::transaction(function () use ($vacation, $approver, $comment) {
            $steps     = $vacation->route?->steps ?? collect();
            $stepCount = $steps->count();

            $this->approvalService->log('vacation', $vacation->id, $vacation->current_step, $approver->id, 'approved', $comment);

            if ($vacation->current_step >= $stepCount || $stepCount === 0) {
                $vacation->update(['status' => 'approved']);
            } else {
                $vacation->increment('current_step');
            }
        });

        // Moved on to the next step — notify its approver.
        if ($vacation->fresh()?->status === 'pending') {
            $this->notifyCurrentApprover($vacation->fresh());
        }
    }

    public function reject(VacationRequest $vacation, User $approver, string $comment): void
    {
        $this->approvalService->log('vacation', $vacation->id, $vacation->current_step, $approver->id, 'rejected', $comment);
        $vacation->update(['status' => 'rejected', 'comment' => $comment]);
    }

    public function sendRevision(VacationRequest $vacation, User $approver, string $comment): void
    {
        $this->approvalService->log('vacation', $vacation->id, $vacation->current_step, $approver->id, 'sent_revision', $comment);
        $vacation->update(['status' => 'revision', 'comment' => $comment]);
    }
}
