<?php

namespace App\Services;

use App\Models\TripRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TripService
{
    public function __construct(private ApprovalService $approvalService) {}

    public function create(User $user, array $data, bool $submit = false): TripRequest
    {
        $route = $this->approvalService->findRoute($user, 'trip');

        $dateStart = Carbon::parse($data['date_start']);
        $dateEnd   = Carbon::parse($data['date_end']);
        $days      = $dateStart->diffInDays($dateEnd) + 1;

        $dailyRate     = (float) ($data['daily_rate'] ?? 0);
        $accommodation = (float) ($data['accommodation_total'] ?? 0);
        $transport     = (float) ($data['transport_total'] ?? 0);
        $total         = ($dailyRate * $days) + $accommodation + $transport;

        $firstStep   = $route?->steps()->orderBy('step_order')->first();
        $signatoryId = $firstStep
            ? $this->approvalService->findApprover($user, $firstStep)?->id
            : null;

        return DB::transaction(function () use ($user, $data, $route, $total, $submit, $signatoryId) {
            $trip = TripRequest::create([
                'user_id'              => $user->id,
                'signatory_id'         => $signatoryId,
                'route_id'             => $route?->id,
                'current_step'         => 1,
                'status'               => $submit ? 'pending' : 'draft',
                'city'                 => $data['city'],
                'purpose'              => $data['purpose'],
                'date_start'           => $data['date_start'],
                'date_end'             => $data['date_end'],
                'daily_rate'           => $data['daily_rate'] ?? 0,
                'accommodation_total'  => $data['accommodation_total'] ?? 0,
                'transport_total'      => $data['transport_total'] ?? 0,
                'total_amount'         => $total,
                'comment'              => $data['comment'] ?? null,
            ]);

            if ($submit) {
                $this->approvalService->log('trip', $trip->id, 1, $user->id, 'submitted');
            }

            return $trip;
        });
    }

    public function submit(TripRequest $trip): void
    {
        $trip->update(['status' => 'pending', 'current_step' => 1]);
        $this->approvalService->log('trip', $trip->id, 1, $trip->user_id, 'submitted');
    }

    public function approve(TripRequest $trip, User $approver, ?string $comment = null): void
    {
        DB::transaction(function () use ($trip, $approver, $comment) {
            $steps     = $trip->route?->steps ?? collect();
            $stepCount = $steps->count();

            $this->approvalService->log('trip', $trip->id, $trip->current_step, $approver->id, 'approved', $comment);

            if ($trip->current_step >= $stepCount || $stepCount === 0) {
                $trip->update(['status' => 'approved']);
            } else {
                $trip->increment('current_step');
                $trip->update(['status' => 'pending']);
            }
        });
    }

    public function reject(TripRequest $trip, User $approver, string $comment): void
    {
        $this->approvalService->log('trip', $trip->id, $trip->current_step, $approver->id, 'rejected', $comment);
        $trip->update(['status' => 'rejected', 'comment' => $comment]);
    }

    public function sendRevision(TripRequest $trip, User $approver, string $comment): void
    {
        $this->approvalService->log('trip', $trip->id, $trip->current_step, $approver->id, 'sent_revision', $comment);
        $trip->update(['status' => 'revision', 'comment' => $comment]);
    }
}
