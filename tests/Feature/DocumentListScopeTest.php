<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Фильтр отображения списка документов. Сотрудник тонул в документах всего отдела,
 * поэтому список по умолчанию показывает только свои, а шире смотрят те, кому по роли
 * положено: отдел — всем, подотделы — руководителю департамента и выше, всё — админу.
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

    public function test_list_shows_my_own_document_by_default(): void
    {
        [$me] = $this->twoColleagues();
        $this->documentOf($me, 'Моя заявка');

        $this->actingAs($me)->get(route('documents.index'))->assertSee('Моя заявка');
    }

    public function test_department_scope_shows_a_colleague_document(): void
    {
        [$me, $colleague] = $this->twoColleagues();
        $this->documentOf($colleague, 'Заявка коллеги');

        $this->actingAs($me)->get(route('documents.index', ['scope' => 'department']))->assertSee('Заявка коллеги');
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

    public function test_linear_employee_is_not_offered_the_subdepartment_scope(): void
    {
        [$me] = $this->twoColleagues();

        $scopes = $this->actingAs($me)->get(route('documents.index'))->viewData('scopes');

        $this->assertArrayNotHasKey('subtree', $scopes);
    }

    public function test_department_head_is_offered_the_subdepartment_scope(): void
    {
        $head = $this->employee('head_department', Department::create(['name' => 'Департамент'])->id);

        $scopes = $this->actingAs($head)->get(route('documents.index'))->viewData('scopes');

        $this->assertArrayHasKey('subtree', $scopes);
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
