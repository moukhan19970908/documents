<?php

namespace App\Services;

use App\Models\VacationRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VacationService
{
    public function __construct(private ApprovalService $approvalService) {}

    public function create(User $user, array $data, bool $submit = false): VacationRequest
    {
        $route = $this->approvalService->findRoute($user, 'vacation');

        $days = Carbon::parse($data['date_start'])->diffInDays($data['date_end']) + 1;

        $firstStep   = $route?->steps()->orderBy('step_order')->first();
        $signatoryId = $firstStep
            ? $this->approvalService->findApprover($user, $firstStep)?->id
            : null;

        return DB::transaction(function () use ($user, $data, $route, $days, $submit, $signatoryId) {
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
    }

    public function submit(VacationRequest $vacation): void
    {
        $vacation->update(['status' => 'pending', 'current_step' => 1]);
        $this->approvalService->log('vacation', $vacation->id, 1, $vacation->user_id, 'submitted');
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
