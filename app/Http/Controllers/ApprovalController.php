<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveDocumentRequest;
use App\Models\Document;
use App\Models\Workflow;
use App\Services\ApprovalEngineService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalEngineService $engine) {}

    public function start(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        if ($document->activeApproval) {
            return back()->with('error', 'Согласование уже запущено.');
        }

        // Ad-hoc: user selected approvers manually
        if ($request->has('approvers')) {
            $request->validate([
                'approvers'   => ['required', 'array', 'min:1'],
                'approvers.*' => ['required', 'integer', 'exists:users,id'],
            ]);

            $this->engine->startAdHocApproval($document, $request->approvers);

            return back()->with('success', 'Согласование запущено.');
        }

        // Workflow-based approval
        $workflowId = $request->input('workflow_id');
        $workflow = $workflowId
            ? Workflow::findOrFail($workflowId)
            : ($document->type->defaultWorkflow ?? Workflow::where('is_active', true)->first());

        if (!$workflow) {
            return back()->with('error', 'Не найден подходящий маршрут согласования.');
        }

        $this->engine->startApproval($document, $workflow);

        return back()->with('success', 'Согласование запущено.');
    }

    public function approve(ApproveDocumentRequest $request, Document $document)
    {
        $this->authorize('approve', $document);

        $stage = $document->activeApproval?->activeStage();
        if (!$stage) {
            return back()->with('error', 'Нет активного этапа согласования.');
        }

        $this->engine->processDecision($stage, auth()->user(), 'approve', $request->comment);

        return back()->with('success', 'Документ одобрен.');
    }

    public function reject(ApproveDocumentRequest $request, Document $document)
    {
        $this->authorize('approve', $document);

        $stage = $document->activeApproval?->activeStage();
        if (!$stage) {
            return back()->with('error', 'Нет активного этапа согласования.');
        }

        $this->engine->processDecision($stage, auth()->user(), 'reject', $request->comment);

        return back()->with('success', 'Документ отклонён.');
    }

    public function requestChanges(ApproveDocumentRequest $request, Document $document)
    {
        $this->authorize('approve', $document);

        $stage = $document->activeApproval?->activeStage();
        if (!$stage) {
            return back()->with('error', 'Нет активного этапа согласования.');
        }

        $this->engine->processDecision($stage, auth()->user(), 'request_changes', $request->comment);

        return back()->with('success', 'Документ отправлен на доработку.');
    }

    public function delegate(Request $request, Document $document)
    {
        $this->authorize('approve', $document);

        $request->validate(['delegated_to' => ['required', 'exists:users,id']]);

        $stage = $document->activeApproval?->activeStage();
        if (!$stage) {
            return back()->with('error', 'Нет активного этапа согласования.');
        }

        $this->engine->processDecision(
            $stage,
            auth()->user(),
            'delegate',
            $request->comment,
            $request->delegated_to
        );

        return back()->with('success', 'Задача делегирована.');
    }
}
