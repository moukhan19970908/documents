<?php

namespace Tests\Feature;

use App\Models\ApprovalRoute;
use App\Models\ApprovalRouteStep;
use App\Models\Department;
use App\Models\RequestFlow;
use App\Models\Substitution;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\RequestFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Замещение на период отсутствия: у линейного сотрудника замещающего выбирает не он сам,
 * а первый согласующий его заявки. Руководители по-прежнему назначают замещающего сами.
 */
class DeputyChosenByApproverTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $colleague;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create(['name' => 'Отдел продаж']);
        $this->colleague  = $this->employee(['name' => 'Коллега Иванов']);
        $this->approver   = User::factory()->create(['role' => 'admin', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    private function employee(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role'                  => 'linear',
            'role_level'            => 1,
            'is_active'             => true,
            'department_id'         => $this->department->id,
            'agreement_accepted_at' => now(),
        ]);
    }

    /** Опубликованный граф отпуска — без него подача заявки отклоняется. */
    private function publishVacationFlow(): void
    {
        User::factory()->create(['role' => 'hr', 'is_active' => true]);
        $flow = RequestFlow::create(['request_type' => 'vacation', 'name' => 'Отпуск', 'status' => 'published']);
        app(RequestFlowService::class)->save($flow, [
            ['type' => 'approver_role', 'name' => 'Кадры', 'config' => ['group_role' => 'hr']],
        ]);
    }

    private function submitVacation(User $initiator): void
    {
        $this->actingAs($initiator)->post(route('vacations.store'), [
            'vacation_type' => 'annual',
            'date_start'    => '2026-11-02',
            'days_count'    => 5,
            'deputy_id'     => $this->colleague->id,
            'submit'        => 1,
        ]);
    }

    /** Заявка на шаге $step двухшагового маршрута; согласующий обоих шагов — $this->approver. */
    private function pendingVacation(User $initiator, int $step = 1): VacationRequest
    {
        $route = ApprovalRoute::create(['name' => 'Отпуск', 'request_type' => 'vacation', 'is_active' => true, 'is_ephemeral' => true]);
        foreach ([1, 2] as $order) {
            ApprovalRouteStep::create(['route_id' => $route->id, 'step_order' => $order, 'approver_user_id' => $this->approver->id]);
        }

        return VacationRequest::create([
            'user_id'       => $initiator->id,
            'route_id'      => $route->id,
            'current_step'  => $step,
            'status'        => 'pending',
            'vacation_type' => 'annual',
            'date_start'    => '2026-11-02',
            'date_end'      => '2026-11-06',
            'days_count'    => 5,
        ]);
    }

    public function test_linear_initiator_cannot_assign_own_deputy_on_submit(): void
    {
        $this->publishVacationFlow();

        $this->submitVacation($this->employee());

        $this->assertDatabaseCount('substitutions', 0);
    }

    public function test_manager_initiator_still_assigns_own_deputy_on_submit(): void
    {
        $this->publishVacationFlow();

        $this->submitVacation($this->employee(['role' => 'director', 'role_level' => 2]));

        $this->assertDatabaseHas('substitutions', ['deputy_user_id' => $this->colleague->id]);
    }

    public function test_first_approver_assigns_deputy_for_linear_initiator(): void
    {
        $initiator = $this->employee();
        $vacation  = $this->pendingVacation($initiator);

        $this->actingAs($this->approver)->post(route('vacations.approve', $vacation), ['deputy_id' => $this->colleague->id]);

        $this->assertDatabaseHas('substitutions', [
            'absent_user_id'      => $initiator->id,
            'deputy_user_id'      => $this->colleague->id,
            'vacation_request_id' => $vacation->id,
        ]);
    }

    public function test_approver_cannot_assign_deputy_from_another_department(): void
    {
        $vacation = $this->pendingVacation($this->employee());
        $outsider = User::factory()->create(['is_active' => true, 'department_id' => Department::create(['name' => 'Склад'])->id]);

        $this->actingAs($this->approver)
            ->post(route('vacations.approve', $vacation), ['deputy_id' => $outsider->id])
            ->assertSessionHasErrors('deputy_id');
    }

    public function test_later_approver_does_not_assign_deputy(): void
    {
        $vacation = $this->pendingVacation($this->employee(), step: 2);

        $this->actingAs($this->approver)->post(route('vacations.approve', $vacation), ['deputy_id' => $this->colleague->id]);

        $this->assertDatabaseCount('substitutions', 0);
    }

    public function test_approvals_page_offers_deputy_choice_for_linear_request(): void
    {
        $this->pendingVacation($this->employee());

        $this->actingAs($this->approver)
            ->get(route('requests.approvals'))
            ->assertSee('Коллега Иванов');
    }
}
