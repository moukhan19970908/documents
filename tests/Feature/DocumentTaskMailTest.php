<?php

namespace Tests\Feature;

use App\Jobs\SendApprovalNotification;
use App\Mail\TaskAssigned;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
use App\Services\ApprovalEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Запуск документа по маршруту: согласующему ставится задача и уходит письмо
 * с инициатором в роли постановщика. Раньше на этом месте создавалась задача
 * в Битрикс24 — теперь её нет.
 */
class DocumentTaskMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_document_mails_the_approver_instead_of_bitrix(): void
    {
        Mail::fake();
        Queue::fake();

        $initiator = User::factory()->create(['is_active' => true, 'name' => 'Мингазов Азат']);
        $approver  = User::factory()->create(['is_active' => true, 'email' => 'approver@vamin.local']);

        $workflow = Workflow::create([
            'name'           => 'Стандартное согласование',
            'created_by'     => $initiator->id,
            'is_active'      => true,
            'status'         => 'published',
            'engine_version' => 1,
            'approval_type'  => 'sequential',
        ]);

        $stage = WorkflowStage::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Шаг 1',
            'phase'       => 'approval',
            'resolver'    => 'user',
            'stage_type'  => 'parallel',
            'sort_order'  => 0,
            'is_blocking' => true,
        ]);

        WorkflowStageApprover::create([
            'workflow_stage_id' => $stage->id,
            'approver_type'     => 'user',
            'approver_id'       => $approver->id,
            'is_required'       => true,
            'participant_type'  => 'approver',
        ]);

        $document = Document::create([
            'title'        => 'Договор поставки',
            'workflow_id'  => $workflow->id,
            'initiator_id' => $initiator->id,
            'status'       => 'draft',
        ]);

        app(ApprovalEngineService::class)->startApproval($document, $workflow);

        // Задача появилась в «Моих задачах», постановщик — инициатор.
        $task = Task::where('document_id', $document->id)->where('assignee_id', $approver->id)->first();
        $this->assertNotNull($task, 'Согласующему должна быть поставлена задача.');
        $this->assertSame($initiator->id, $task->created_by);
        $this->assertNull($task->bitrix24_task_id, 'Задача в Битрикс24 больше не создаётся.');

        // Письмо о задаче — с инициатором как постановщиком.
        Mail::assertQueued(TaskAssigned::class, fn (TaskAssigned $mail) => $mail->hasTo($approver->email)
            && $mail->assigner?->is($initiator));

        // Дубля письма по тому же событию нет.
        Queue::assertNotPushed(SendApprovalNotification::class,
            fn (SendApprovalNotification $job) => $job->notification->type === 'new_document');
    }
}
