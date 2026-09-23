<?php

namespace Tests\Feature;

use App\Models\RequestFlow;
use App\Models\TripTask;
use App\Models\TripTaskSetting;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\RequestFlowService;
use App\Services\VacationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Узел «Задание исполнителю» в граф-процессе отпуска: план замораживается при подаче
 * и порождает задание при полном согласовании (как у командировок).
 */
class VacationFlowTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $settingsUser;

    protected function setUp(): void
    {
        parent::setUp();

        // TripTaskSetting::current() держит строку в статическом кэше — он переживает
        // RefreshDatabase, поэтому перед каждым тестом сбрасываем.
        $cache = new \ReflectionProperty(TripTaskSetting::class, 'cached');
        $cache->setValue(null, null);

        $this->hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        // В настройках исполнителей — другой человек: исполнителя задания движок должен
        // брать по роли, а настройки остаются лишь запасным путём.
        $this->settingsUser = User::factory()->create(['role' => 'linear', 'is_active' => true]);
        TripTaskSetting::current()->update(['hr_user_id' => $this->settingsUser->id]);

        $flow = RequestFlow::create([
            'request_type' => 'vacation',
            'name'         => 'Отпуск',
            'status'       => 'published',
        ]);

        app(RequestFlowService::class)->save($flow, [
            ['type' => 'approver_role', 'name' => 'Согласующий (роль)', 'config' => ['group_role' => 'hr']],
            ['type' => 'task', 'name' => 'Задание исполнителю',
             'config' => ['assignee' => 'hr', 'title' => 'Оформить отпуск сотруднику']],
        ]);
    }

    private function submit(): VacationRequest
    {
        return app(VacationService::class)->create(
            User::factory()->create(['is_active' => true]),
            ['vacation_type' => 'annual', 'date_start' => '2026-10-01', 'date_end' => '2026-10-07'],
            submit: true,
        );
    }

    public function test_submitting_freezes_the_flow_tasks(): void
    {
        $vacation = $this->submit();

        $this->assertSame(
            [['title' => 'Оформить отпуск сотруднику', 'assignee' => 'hr']],
            $vacation->refresh()->flow_tasks,
        );
        $this->assertCount(0, $vacation->tasks, 'До согласования заданий быть не должно.');
    }

    public function test_full_approval_creates_the_task_for_the_configured_assignee(): void
    {
        $vacation = $this->submit();

        app(VacationService::class)->approve($vacation, $this->hr);

        $vacation->refresh();
        $this->assertSame('approved', $vacation->status);

        $task = $vacation->tasks()->first();
        $this->assertNotNull($task, 'Задание графа не создалось при полном согласовании.');
        $this->assertSame('Оформить отпуск сотруднику', $task->title);
        $this->assertSame($this->hr->id, $task->assignee_id);
        $this->assertSame($vacation->id, $task->vacation_request_id);
        $this->assertNull($task->trip_request_id);
        $this->assertTrue($task->isVacationTask());
        $this->assertSame('V-' . $vacation->id, $task->requestNumber());
        $this->assertSame($vacation->user_id, $task->initiator()?->id);
    }

    public function test_assignee_comes_from_the_role_not_from_the_settings_table(): void
    {
        $vacation = $this->submit();

        app(VacationService::class)->approve($vacation, $this->hr);

        $task = $vacation->refresh()->tasks()->first();
        $this->assertSame($this->hr->id, $task->assignee_id, 'Исполнителем должен быть носитель роли HR.');
        $this->assertNotSame($this->settingsUser->id, $task->assignee_id);
    }

    /** Ключ без одноимённой роли (офис-менеджер) по-прежнему берётся из настроек. */
    public function test_a_key_without_a_matching_role_falls_back_to_the_settings(): void
    {
        TripTaskSetting::current()->update(['office_manager_id' => $this->settingsUser->id]);

        $flow = \App\Models\RequestFlow::where('request_type', 'vacation')->first();
        app(RequestFlowService::class)->save($flow, [
            ['type' => 'approver_role', 'name' => 'Согласующий (роль)', 'config' => ['group_role' => 'hr']],
            ['type' => 'task', 'name' => 'Задание исполнителю',
             'config' => ['assignee' => 'office_manager', 'title' => 'Забронировать']],
        ]);

        $vacation = $this->submit();
        app(VacationService::class)->approve($vacation, $this->hr);

        $this->assertSame($this->settingsUser->id, $vacation->refresh()->tasks()->first()?->assignee_id);
    }

    public function test_task_is_not_duplicated_on_repeated_generation(): void
    {
        $vacation = $this->submit();

        app(VacationService::class)->approve($vacation, $this->hr);
        app(\App\Services\TripTaskService::class)->generateFromFlow($vacation->refresh());

        $this->assertCount(1, $vacation->refresh()->tasks);
    }

    public function test_assignee_sees_the_vacation_task_in_the_tasks_tab(): void
    {
        $vacation = $this->submit();
        app(VacationService::class)->approve($vacation, $this->hr);

        $this->hr->update(['agreement_accepted_at' => now(), 'role' => 'admin']);

        $this->actingAs($this->hr->fresh())
            ->get(route('requests.tasks'))
            ->assertOk()
            ->assertSee('Оформить отпуск сотруднику')
            ->assertSee('V-' . $vacation->id)
            ->assertSee('отпуск');
    }

    public function test_initiator_sees_the_task_on_the_vacation_card(): void
    {
        $vacation = $this->submit();
        app(VacationService::class)->approve($vacation, $this->hr);

        $initiator = $vacation->user;
        $initiator->update(['agreement_accepted_at' => now()]);

        $this->actingAs($initiator->fresh())
            ->get(route('vacations.show', $vacation))
            ->assertOk()
            ->assertSee('Задания по отпуску')
            ->assertSee('Оформить отпуск сотруднику')
            ->assertSee($this->hr->name);
    }

    public function test_backfill_command_creates_tasks_for_already_approved_vacations(): void
    {
        $vacation = $this->submit();
        app(VacationService::class)->approve($vacation, $this->hr);

        // Возвращаем заявку в состояние «до правки»: план не сохранён, заданий нет.
        TripTask::where('vacation_request_id', $vacation->id)->delete();
        $vacation->update(['flow_tasks' => null]);

        $this->artisan('vacations:backfill-tasks', ['--dry-run' => true])->assertSuccessful();
        $this->assertCount(0, $vacation->refresh()->tasks, '--dry-run не должен ничего создавать.');

        $this->artisan('vacations:backfill-tasks')->assertSuccessful();

        $task = $vacation->refresh()->tasks()->first();
        $this->assertNotNull($task);
        $this->assertSame('Оформить отпуск сотруднику', $task->title);
        $this->assertSame($this->hr->id, $task->assignee_id);
    }
}
