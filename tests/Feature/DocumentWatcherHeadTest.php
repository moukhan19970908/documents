<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentWatcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Наблюдатель руководителя видит то же, что руководитель: документы подотделов,
 * а не только те, где руководитель сам инициатор или согласующий.
 */
class DocumentWatcherHeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL проекта содержит подпуть (/documents/public) — с ним тестовый запрос
        // не попадает в маршрут. Для теста ходим по корню.
        URL::forceRootUrl('http://localhost');
    }

    public function test_watched_scope_shows_a_subdepartment_document_of_a_watched_head(): void
    {
        [$watcher, $subordinate] = $this->watcherOf('head_department');
        $this->documentOf($subordinate, 'Заявка подотдела');

        $this->actingAs($watcher)->get(route('documents.index', ['scope' => 'watched']))->assertSee('Заявка подотдела');
    }

    public function test_watcher_of_a_head_may_open_a_subdepartment_document(): void
    {
        [$watcher, $subordinate] = $this->watcherOf('head_department');
        $document = $this->documentOf($subordinate, 'Заявка подотдела');

        $this->assertTrue($watcher->can('view', $document));
    }

    public function test_watcher_of_a_linear_employee_does_not_see_subdepartment_documents(): void
    {
        [$watcher, $subordinate] = $this->watcherOf('linear');
        $this->documentOf($subordinate, 'Заявка подотдела');

        $this->actingAs($watcher)->get(route('documents.index', ['scope' => 'watched']))->assertDontSee('Заявка подотдела');
    }

    public function test_initiator_only_watch_of_a_head_does_not_cover_subdepartments(): void
    {
        [$watcher, $subordinate] = $this->watcherOf('head_department', 'initiator');
        $document = $this->documentOf($subordinate, 'Заявка подотдела');

        $this->assertFalse($watcher->can('view', $document));
    }

    /** @return array{0: User, 1: User} наблюдатель цели и сотрудник подотдела цели */
    private function watcherOf(string $targetRole, string $scope = 'participant'): array
    {
        $direction = Department::create(['name' => 'Направление']);
        $child     = Department::create(['name' => 'Подотдел', 'parent_id' => $direction->id]);
        $other     = Department::create(['name' => 'Другой отдел']);

        $target  = $this->employee($targetRole, $direction->id);
        $watcher = $this->employee('linear', $other->id);
        DocumentWatcher::create(['watcher_id' => $watcher->id, 'target_id' => $target->id, 'scope' => $scope]);

        return [$watcher, $this->employee('linear', $child->id)];
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
