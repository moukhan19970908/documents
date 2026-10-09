<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Models\Workflow;
use App\Services\ApprovalEngineService;
use App\Services\RouteGraphService;
use App\Services\ScenarioPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «На доработку» в маршруте-графе: у звена ветка «Нет» не нарисована — документ уходит
 * инициатору на доработку, а не проскакивает дальше и не становится «Одобрено».
 * Схема как у «[СЗ] на списание образцов»: три звена подряд с пустыми ветками «Да/Нет».
 */
class GraphRequestChangesTest extends TestCase
{
    use RefreshDatabase;

    private User $head;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $user = fn () => User::factory()->create(['role' => 'linear', 'is_active' => true, 'agreement_accepted_at' => now()]);
        $initiator  = $user();
        $this->head = $user();
        $security   = $user();
        $director   = $user();

        $type = DocumentType::create(['name' => 'Служебная записка', 'code' => 'СЗ', 'slug' => 'sz', 'is_active' => true]);
        $scenario = Workflow::create(['name' => '[СЗ] на списание образцов', 'document_type_id' => $type->id, 'is_active' => true, 'status' => 'draft', 'created_by' => $initiator->id]);

        $task = fn (string $type, string $name, User $who) => [
            'type' => $type, 'name' => $name, 'config' => ['resolver' => 'user', 'approver_ids' => [$who->id]], 'yes' => [], 'no' => [],
        ];
        app(RouteGraphService::class)->save($scenario, [
            $task('approval', 'Согласование Рук Деп', $this->head),
            $task('approval', 'Согласование СБ', $security),
            $task('approve', 'Утверждение Ком Дир', $director),
        ]);
        app(ScenarioPublisher::class)->publish($scenario->refresh());

        $this->document = Document::create([
            'title' => 'СЗ на списание образцов', 'document_type_id' => $type->id,
            'workflow_id' => $scenario->id, 'initiator_id' => $initiator->id, 'status' => 'draft',
        ]);
        app(ApprovalEngineService::class)->startApproval($this->document, $scenario->refresh());
    }

    private function requestChangesOnFirstStage(): void
    {
        $stage = $this->document->refresh()->activeApproval->activeStage();

        app(ApprovalEngineService::class)->processDecision($stage, $this->head, 'request_changes', 'Уточните количество');
    }

    public function test_request_changes_without_negative_branch_returns_document_to_initiator(): void
    {
        $this->requestChangesOnFirstStage();

        $this->assertSame('requires_changes', $this->document->refresh()->status);
    }

    /** Прод, СЗ-0079: «Отклонить» уводил документ в «Требует изменений», как вторая кнопка доработки. */
    public function test_reject_without_negative_branch_rejects_document(): void
    {
        $stage = $this->document->refresh()->activeApproval->activeStage();

        app(ApprovalEngineService::class)->processDecision($stage, $this->head, 'reject', 'Цены не подтверждены');

        $this->assertSame('rejected', $this->document->refresh()->status);
    }

    /** Прод, СЗ-0158: после доработки повторная отправка сразу давала «Одобрено» — маршрут пропускался целиком. */
    public function test_resubmit_after_changes_goes_through_the_route_again(): void
    {
        $this->requestChangesOnFirstStage();

        $this->actingAs($this->document->initiator)->post(route('documents.resubmit', $this->document));

        $this->assertSame('Согласование Рук Деп', $this->document->refresh()->activeApproval?->activeStage()?->workflowStage?->name);
    }

    /**
     * Состояние прода после ручного исправления: ложное одобрение отменено, документ снова
     * «Требует изменений». Повторная отправка берёт исходный запуск, а не отменённый.
     */
    public function test_resubmit_after_cancelled_false_approval_goes_through_the_route_again(): void
    {
        $this->requestChangesOnFirstStage();
        $runtimeCopy = $this->document->approvals()->first()->workflow_id;
        $this->document->approvals()->create([
            'workflow_id'  => $runtimeCopy,
            'runtime_data' => ['graph_workflow_id' => $runtimeCopy],
            'status'       => 'cancelled',
            'started_at'   => now(),
        ]);

        $this->actingAs($this->document->initiator)->post(route('documents.resubmit', $this->document));

        $this->assertSame('Согласование Рук Деп', $this->document->refresh()->activeApproval?->activeStage()?->workflowStage?->name);
    }

    /** Прод, СЗ-0152: после ручного исправления вкладка показывала отменённый пустой запуск вместо маршрута. */
    public function test_document_page_shows_real_route_instead_of_cancelled_false_approval(): void
    {
        $this->requestChangesOnFirstStage();
        $runtimeCopy = $this->document->approvals()->first()->workflow_id;
        $this->document->approvals()->create([
            'workflow_id'  => $runtimeCopy,
            'runtime_data' => ['graph_workflow_id' => $runtimeCopy],
            'status'       => 'cancelled',
            'started_at'   => now(),
        ]);

        $this->actingAs($this->document->initiator)
            ->get(route('documents.show', $this->document))
            ->assertDontSee('Отменён');
    }

    public function test_route_without_steps_is_not_approved_silently(): void
    {
        $runtimeCopy = $this->document->approvals()->first()->workflow;

        $this->expectException(\RuntimeException::class);

        app(ApprovalEngineService::class)->startApproval($this->document, $runtimeCopy);
    }
}
