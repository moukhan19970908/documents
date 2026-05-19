<?php

namespace App\Services;

use App\Models\Registry;
use App\Models\RegistryItem;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistryService
{
    public function __construct(private ApprovalService $approvalService) {}

    public function createTripRegistry(User $creator, string $title, array $tripIds, ?string $comment = null): Registry
    {
        return DB::transaction(function () use ($creator, $title, $tripIds, $comment) {
            $trips = TripRequest::whereIn('id', $tripIds)
                ->where('status', 'approved')
                ->get();

            $total = $trips->sum('total_amount');

            $route = $this->approvalService->findRoute($creator, 'trip');

            $registry = Registry::create([
                'type'         => 'trip',
                'created_by'   => $creator->id,
                'route_id'     => $route?->id,
                'current_step' => 1,
                'status'       => 'draft',
                'title'        => $title,
                'total_amount' => $total,
                'comment'      => $comment,
            ]);

            foreach ($trips as $trip) {
                RegistryItem::create([
                    'registry_id'    => $registry->id,
                    'trip_request_id' => $trip->id,
                ]);
                $trip->update(['status' => 'in_registry']);
            }

            return $registry;
        });
    }

    public function submit(Registry $registry): void
    {
        $registry->update(['status' => 'pending', 'current_step' => 1]);
        $this->approvalService->log('registry', $registry->id, 1, $registry->created_by, 'submitted');
    }

    public function approve(Registry $registry, User $approver, ?string $comment = null): void
    {
        DB::transaction(function () use ($registry, $approver, $comment) {
            $steps     = $registry->route?->steps ?? collect();
            $stepCount = $steps->count();

            $this->approvalService->log('registry', $registry->id, $registry->current_step, $approver->id, 'approved', $comment);

            if ($registry->current_step >= $stepCount || $stepCount === 0) {
                $registry->update(['status' => 'approved']);
            } else {
                $registry->increment('current_step');
            }
        });
    }

    public function reject(Registry $registry, User $approver, string $comment): void
    {
        $this->approvalService->log('registry', $registry->id, $registry->current_step, $approver->id, 'rejected', $comment);
        $registry->update(['status' => 'rejected', 'comment' => $comment]);
    }

    public function sendToAccounting(Registry $registry): void
    {
        $registry->update(['status' => 'sent_to_accounting']);
    }

    public function acceptByAccounting(Registry $registry, User $accountant): void
    {
        $this->approvalService->log('registry', $registry->id, $registry->current_step, $accountant->id, 'approved', 'Принято бухгалтерией');
        $registry->update(['status' => 'accepted_by_accounting']);
    }
}
