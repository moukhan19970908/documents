<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentApprovalDecision;
use App\Models\DocumentApprovalStage;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Лист согласования и фаза заключений. Заключение отмечается своими действиями
 * (opinion_yes / opinion_no), а не «согласовать» — лист обязан показать это решение,
 * иначе пройденная фаза выглядит как неотработанная.
 */
class ApprovalSheetOpinionPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_sheet_shows_the_verdict_of_an_opinion_stage(): void
    {
        $html = $this->sheetWithOpinionDecision('opinion_yes');

        $this->assertStringContainsString('Одобрено', $html);
    }

    public function test_sheet_names_the_phase_of_an_opinion_stage(): void
    {
        $html = $this->sheetWithOpinionDecision('opinion_yes');

        $this->assertStringContainsString('Фаза заключений', $html);
    }

    /** Лист согласования документа, прошедшего одно звено фазы заключений. */
    private function sheetWithOpinionDecision(string $action): string
    {
        $user = User::factory()->create();

        $workflow = Workflow::create(['name' => 'Кредитный комитет', 'created_by' => $user->id]);

        $stage = WorkflowStage::create([
            'workflow_id' => $workflow->id,
            'name'        => 'Заключение БУХ',
            'phase'       => 'opinion',
            'sort_order'  => 1,
        ]);

        $document = Document::create([
            'title'        => 'Заявка на отсрочку',
            'initiator_id' => $user->id,
            'workflow_id'  => $workflow->id,
            'status'       => 'in_review',
        ]);

        $approval = DocumentApproval::create([
            'document_id' => $document->id,
            'workflow_id' => $workflow->id,
            'status'      => 'in_progress',
        ]);

        $documentStage = DocumentApprovalStage::create([
            'document_approval_id' => $approval->id,
            'workflow_stage_id'    => $stage->id,
            'status'               => 'approved',
        ]);

        DocumentApprovalDecision::create([
            'document_approval_stage_id' => $documentStage->id,
            'user_id'                    => $user->id,
            'action'                     => $action,
            'decided_at'                 => now(),
        ]);

        $approval->load(['stages.decisions.user', 'stages.workflowStage']);

        return view('pdf.approval_sheet', ['document' => $document, 'approval' => $approval])->render();
    }
}
