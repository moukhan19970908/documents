<?php

namespace App\Services;

use App\Models\TripRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TripService
{
    public function __construct(
        private ApprovalService $approvalService,
        private NotificationService $notifications,
        private TripTaskService $tripTasks,
        private RequestFlowRouter $flowRouter,
    ) {}

    /**
     * Notify the approver responsible for the trip's current step.
     */
    private function notifyCurrentApprover(TripRequest $trip): void
    {
        if ($trip->status !== 'pending') {
            return;
        }
        $trip->loadMissing('route.steps', 'user');
        $step = $trip->route?->steps->firstWhere('step_order', $trip->current_step);
        if (!$step) {
            return;
        }
        $approver = $this->approvalService->findApprover($trip->user, $step);
        if (!$approver) {
            return;
        }
        $this->notifications->notify($approver, 'trip_approval', [
            'title'   => $trip->city ?? ('Командировка #' . $trip->id),
            'trip_id' => $trip->id,
        ]);
    }

    public function create(User $user, array $data, bool $submit = false): TripRequest
    {
        // Граф-процесс (если опубликован) строит маршрут; иначе — автоподбор ApprovalRoute.
        $routing = $this->flowRouter->routeFor($user, 'trip', [
            'transport_type' => $data['transport_type'] ?? null,
            'location_type'  => $data['location_type'] ?? null,
        ]);
        $route     = $routing['route'];
        $flowTasks = $routing['via_graph'] ? $routing['tasks'] : null;   // null — задания порождает TripTaskService

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

        $trip = DB::transaction(function () use ($user, $data, $route, $total, $submit, $signatoryId, $flowTasks) {
            $trip = TripRequest::create([
                'user_id'              => $user->id,
                'signatory_id'         => $signatoryId,
                'route_id'             => $route?->id,
                'current_step'         => 1,
                'status'               => $submit ? 'pending' : 'draft',
                'city'                 => $data['city'],
                'location_type'        => $data['location_type'] ?? null,
                'purpose'              => $data['purpose'],
                'date_start'           => $data['date_start'],
                'date_end'             => $data['date_end'],
                'daily_rate'           => $data['daily_rate'] ?? 0,
                'accommodation_total'  => $data['accommodation_total'] ?? 0,
                'transport_total'      => $data['transport_total'] ?? 0,
                'transport_type'       => $data['transport_type'] ?? null,
                'total_amount'         => $total,
                'comment'              => $data['comment'] ?? null,
                'flow_tasks'           => $flowTasks,
            ]);

            if ($submit) {
                $this->approvalService->log('trip', $trip->id, 1, $user->id, 'submitted');
            }

            return $trip;
        });

        if ($submit) {
            $this->notifyCurrentApprover($trip);
        }

        return $trip;
    }

    public function submit(TripRequest $trip): void
    {
        $trip->update(['status' => 'pending', 'current_step' => 1]);
        $this->approvalService->log('trip', $trip->id, 1, $trip->user_id, 'submitted');
        $this->notifyCurrentApprover($trip);
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

        $fresh = $trip->fresh();

        // Moved on to the next step — notify its approver.
        if ($fresh?->status === 'pending') {
            $this->notifyCurrentApprover($fresh);
        }

        // Согласование завершено — порождаются задания: по графу (если заявка шла по нему)
        // либо по прежним правилам (ТЗ 18.3).
        if ($fresh?->status === 'approved') {
            if ($fresh->flow_tasks !== null) {
                $this->tripTasks->generateFromFlow($fresh);
            } else {
                $this->tripTasks->generateFor($fresh);
            }
        }
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
