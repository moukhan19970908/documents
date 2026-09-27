<?php

namespace Tests\Feature;

use App\Models\ApprovalRoute;
use App\Models\ApprovalRouteStep;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Доступ к вкладке «На согласовании». Граф-процесс ставит звеном конкретного человека
 * любой роли, поэтому назначенный согласующий должен видеть заявку даже без права
 * из матрицы — иначе маршрут встаёт: решение ждут от того, кто заявку не видит.
 */
class ApprovalsTabAccessTest extends TestCase
{
    use RefreshDatabase;

    private function linear(): User
    {
        return User::factory()->create([
            'role'                  => 'linear',
            'role_level'            => 1,
            'is_active'             => true,
            'agreement_accepted_at' => now(),
        ]);
    }

    /** Заявка с одноразовым маршрутом графа, первое звено — конкретный человек. */
    private function pendingTripFor(User $approver, User $initiator): TripRequest
    {
        $route = ApprovalRoute::create([
            'name'         => 'Граф: Командировка',
            'request_type' => 'trip',
            'is_active'    => true,
            'is_ephemeral' => true,
        ]);

        ApprovalRouteStep::create([
            'route_id'         => $route->id,
            'step_order'       => 1,
            'approver_user_id' => $approver->id,
        ]);

        return TripRequest::create([
            'user_id'      => $initiator->id,
            'signatory_id' => $approver->id,
            'route_id'     => $route->id,
            'current_step' => 1,
            'status'       => 'pending',
            'city'         => 'Казань',
            'purpose'      => 'Переговоры',
            'date_start'   => '2026-10-01',
            'date_end'     => '2026-10-03',
        ]);
    }

    public function test_assigned_linear_approver_sees_the_request(): void
    {
        $approver  = $this->linear();
        $initiator = $this->linear();
        $this->pendingTripFor($approver, $initiator);

        $this->assertFalse(
            $approver->canSeeMenu('menu.trips.approvals'),
            'Предпосылка теста: у линейного нет права из матрицы.',
        );

        $this->actingAs($approver)
            ->get(route('requests.approvals'))
            ->assertOk()
            ->assertSee($initiator->name);
    }

    public function test_transport_type_is_shown_in_russian(): void
    {
        $approver  = $this->linear();
        $initiator = $this->linear();
        $trip = $this->pendingTripFor($approver, $initiator);

        $trip->update(['transport_type' => 'own']);
        $this->assertSame('Свой', $trip->fresh()->transport_type_label);

        $trip->update(['transport_type' => 'company']);
        $this->assertSame('Организации', $trip->fresh()->transport_type_label);
    }

    public function test_linear_without_assignments_is_still_denied(): void
    {
        $this->actingAs($this->linear())
            ->get(route('requests.approvals'))
            ->assertForbidden();
    }

    public function test_the_badge_counts_the_assigned_request(): void
    {
        $approver  = $this->linear();
        $initiator = $this->linear();
        $this->pendingTripFor($approver, $initiator);

        // Счётчик раньше обнулялся вместе с доступом — заявка была не видна и в нём.
        $this->actingAs($approver)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('На согласовании');
    }
}
