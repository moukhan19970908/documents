<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveDocumentRequest;
use App\Models\Document;
use App\Models\Workflow;
use App\Services\ApprovalEngineService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private ApprovalEngineService $engine,
        private AuditService $audit,
    ) {}

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

            $this->audit->log(auth()->user()->name . ' начал процесс «' . $document->title . '»', $document);

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

        $this->audit->log(auth()->user()->name . ' начал процесс «' . $document->title . '»', $document);

        return back()->with('success', 'Согласование запущено.');
    }

    public function resubmit(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        if ($document->status !== 'draft') {
            return back()->with('error', 'Документ не готов к повторному согласованию.');
        }

        // Reuse the workflow from the most recent approval
        $previousApproval = $document->approvals()->latest()->first();
        if (!$previousApproval) {
            return back()->with('error', 'Предыдущее согласование не найдено.');
        }

        $this->engine->startApproval($document, $previousApproval->workflow);

        return back()->with('success', 'Документ отправлен на повторное согласование.');
    }

    public function approve(ApproveDocumentRequest $request, Document $document)
    {
        $this->authorize('approve', $document);

        $stage = $document->activeApproval?->activeStage();
        if (!$stage) {
            return back()->with('error', 'Нет активного этапа согласования.');
        }

        $this->engine->processDecision($stage, auth()->user(), 'approve', $request->comment);

        $this->audit->log(auth()->user()->name . ' согласовал «' . $document->title . '»', $document);

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

        $this->audit->log(auth()->user()->name . ' отказал по документу «' . $document->title . '»', $document);

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

        $this->audit->log(auth()->user()->name . ' отправил на доработку «' . $document->title . '»', $document);

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

        $this->audit->log(auth()->user()->name . ' делегировал «' . $document->title . '»', $document);

        return back()->with('success', 'Задача делегирована.');
    }

    public function approvalSheet(Document $document)
    {
        $this->authorize('view', $document);

        $document->load([
            'initiator',
            'type',
            'approvals.stages.decisions.user',
            'approvals.stages.workflowStage',
        ]);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $approval = $document->approvals()->latest()->first();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.approval_sheet', compact('document', 'approval'));
            return $pdf->download('approval_sheet.pdf');
        }

        // Fallback: render HTML in browser (user can print/save as PDF)
        $approval = $document->approvals()->with(['stages.decisions.user', 'stages.workflowStage'])->latest()->first();
        $html = view('pdf.approval_sheet', compact('document', 'approval'))->render();

        // Inject print button
        $html = str_replace(
            '</body>',
            '<div style="text-align:center;margin:20px"><button onclick="window.print()" style="padding:8px 24px;background:#5B4FE8;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer">Распечатать / Сохранить как PDF</button></div></body>',
            $html
        );

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
