<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Вкладки списка документов. По умолчанию — «Ждут меня»: сотрудник тонул в документах
 * всего отдела. «Мой отдел» есть у всех, но линейный видит его без подотделов; «Все» — админ.
 */
class DocumentListScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL проекта содержит подпуть (/documents/public) — с ним тестовый запрос
        // не попадает в маршрут. Для теста ходим по корню.
        URL::forceRootUrl('http://localhost');
    }

    public function test_list_hides_a_colleague_document_by_default(): void
    {
        [$me, $colleague] = $this->twoColleagues();
        $this->documentOf($colleague, 'Заявка коллеги');

        $this->actingAs($me)->get(route('documents.index'))->assertDontSee('Заявка коллеги');
    }

    public function test_waiting_tab_shows_a_document_with_my_pending_task(): void
    {
        [$me, $colleague] = $this->twoColleagues();
        $this->taskFor($me, $this->documentOf($colleague, 'Ждёт моего решения'));

        $this->actingAs($me)->get(route('documents.index'))->assertSee('Ждёт моего решения');
    }

    public function test_waiting_tab_hides_my_own_document_without_a_task(): void
    {
        [$me] = $this->twoColleagues();
        $this->documentOf($me, 'Моя заявка');

        $this->actingAs($me)->get(route('documents.index'))->assertDontSee('Моя заявка');
    }

    public function test_launched_tab_shows_my_own_document(): void
    {
        [$me] = $this->twoColleagues();
        $this->documentOf($me, 'Моя заявка');

        $this->actingAs($me)->get(route('documents.index', ['scope' => 'launched']))->assertSee('Моя заявка');
    }

    public function test_department_tab_shows_a_subordinate_document_to_the_head(): void
    {
        [$employee] = $this->twoColleagues();
        $head = $this->departmentHead($employee->department_id);
        $this->documentOf($employee, 'Заявка сотрудника');

        $this->actingAs($head)->get(route('documents.index', ['scope' => 'department']))->assertSee('Заявка сотрудника');
    }

    public function test_fired_filter_shows_a_document_stuck_on_a_dismissed_assignee(): void
    {
        [$employee, $dismissed] = $this->twoColleagues();
        $dismissed->update(['is_active' => false]);
        $this->taskFor($dismissed, $this->documentOf($employee, 'Зависла у уволенного'));

        $this->actingAs($this->departmentHead($employee->department_id))
            ->get(route('documents.index', ['scope' => 'department', 'quick' => 'fired']))
            ->assertSee('Зависла у уволенного');
    }

    public function test_credit_committee_list_hides_a_colleague_document_by_default(): void
    {
        [$me, $colleague] = $this->twoColleagues();
        $workflow = Workflow::create([
            'name'         => 'Кредитный комитет',
            'created_by'   => $me->id,
            'process_type' => 'credit_committee',
        ]);
        $this->documentOf($colleague, 'Заявка коллеги')->update(['workflow_id' => $workflow->id]);

        $this->actingAs($me)->get(route('credit-committee.index'))->assertDontSee('Заявка коллеги');
    }

    public function test_linear_employee_is_offered_the_department_tab(): void
    {
        [$me] = $this->twoColleagues();

        $scopes = $this->actingAs($me)->get(route('documents.index'))->viewData('scopes');

        $this->assertArrayHasKey('department', $scopes);
    }

    public function test_linear_employee_department_tab_ignores_subdepartments(): void
    {
        [$me] = $this->twoColleagues();

        $withSubdepartments = $this->actingAs($me)
            ->get(route('documents.index', ['scope' => 'department', 'sub' => 1]))
            ->viewData('withSubdepartments');

        $this->assertFalse($withSubdepartments);
    }

    public function test_department_head_is_offered_the_department_tab(): void
    {
        $head = $this->departmentHead(Department::create(['name' => 'Департамент'])->id);

        $scopes = $this->actingAs($head)->get(route('documents.index'))->viewData('scopes');

        $this->assertArrayHasKey('department', $scopes);
    }

    public function test_admin_is_offered_all_documents(): void
    {
        $admin = $this->employee('admin', Department::create(['name' => 'Департамент'])->id);

        $scopes = $this->actingAs($admin)->get(route('documents.index'))->viewData('scopes');

        $this->assertArrayHasKey('all', $scopes);
    }

    /** @return array{0: User, 1: User} двое сотрудников одного отдела */
    private function twoColleagues(): array
    {
        $department = Department::create(['name' => 'Отдел продаж']);

        return [
            $this->employee('linear', $department->id),
            $this->employee('linear', $department->id),
        ];
    }

    private function departmentHead(int $departmentId): User
    {
        $head = $this->employee('head_department', $departmentId);
        $head->update(['role_level' => 2]);

        return $head;
    }

    private function taskFor(User $assignee, Document $document): Task
    {
        return Task::create([
            'document_id' => $document->id,
            'assignee_id' => $assignee->id,
            'title'       => 'Согласовать',
            'status'      => 'pending',
        ]);
    }

    private function employee(string $role, int $departmentId): User
    {
        return User::factory()->create([
            'role'                  => $role,
            'department_id'         => $departmentId,
            'is_active'             => true,
            'agreement_accepted_at' => now(),
        ]);
    }

    private function documentOf(User $initiator, string $title): Document
    {
        return Document::create([
            'title'        => $title,
            'initiator_id' => $initiator->id,
            'status'       => 'in_review',
        ]);
    }
}
