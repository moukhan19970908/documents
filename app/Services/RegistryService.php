<?php

namespace App\Services;

use App\Models\Registry;
use App\Models\RegistryItem;
use App\Models\TripRequest;
use App\Models\VacationRequest;
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

            // Индивидуальные заявки уже согласованы — реестр собирается и сразу готов
            // к передаче в бухгалтерию, без отдельного маршрута согласования.
            $registry = Registry::create([
                'type'         => 'trip',
                'created_by'   => $creator->id,
                'route_id'     => null,
                'current_step' => 1,
                'status'       => 'approved',
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

    public function createVacationRegistry(User $creator, string $title, array $vacationIds, ?string $comment = null): Registry
    {
        return DB::transaction(function () use ($creator, $title, $vacationIds, $comment) {
            $vacations = VacationRequest::whereIn('id', $vacationIds)
                ->where('status', 'approved')
                ->get();

            // Индивидуальные заявки уже согласованы — реестр собирается и сразу готов
            // к передаче в бухгалтерию, без отдельного маршрута согласования.
            $registry = Registry::create([
                'type'         => 'vacation',
                'created_by'   => $creator->id,
                'route_id'     => null,
                'current_step' => 1,
                'status'       => 'approved',
                'title'        => $title,
                'total_amount' => 0,
                'comment'      => $comment,
            ]);

            foreach ($vacations as $vacation) {
                RegistryItem::create([
                    'registry_id'         => $registry->id,
                    'vacation_request_id' => $vacation->id,
                ]);
                $vacation->update(['status' => 'in_registry']);
            }

            return $registry;
        });
    }

    /**
     * Частичный возврат (ТЗ 18.1 п.12): одна заявка выпадает из реестра на доработку,
     * остальные продолжают путь. Если активных заявок не осталось — реестр отклоняется.
     */
    public function returnItem(Registry $registry, RegistryItem $item, User $approver, string $comment): void
    {
        DB::transaction(function () use ($registry, $item, $approver, $comment) {
            $request = $registry->type === 'trip' ? $item->tripRequest : $item->vacationRequest;

            if ($request) {
                $request->update(['status' => 'revision']);
                $this->approvalService->log($registry->type, $request->id, $registry->current_step, $approver->id, 'sent_revision', $comment);
            }

            $item->update([
                'status'       => 'dropped',
                'dropped_by'   => $approver->id,
                'drop_comment' => $comment,
                'dropped_at'   => now(),
            ]);

            // Пересчёт суммы реестра по оставшимся активным позициям (командировки).
            if ($registry->type === 'trip') {
                $total = $registry->items()->where('status', 'active')->with('tripRequest')->get()
                    ->sum(fn ($i) => (float) ($i->tripRequest?->total_amount ?? 0));
                $registry->update(['total_amount' => $total]);
            }

            // Реестр опустел — отклоняем целиком.
            if ($registry->items()->where('status', 'active')->count() === 0) {
                $registry->update(['status' => 'rejected', 'comment' => 'Все заявки выведены на доработку.']);
                $this->approvalService->log('registry', $registry->id, $registry->current_step, $approver->id, 'rejected', 'Все заявки выведены на доработку.');
            }
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
