<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveDocumentRequest;
use App\Models\Document;
use App\Models\Workflow;
use App\Services\ApprovalEngineService;
use App\Services\AuditService;
use App\Services\PdfGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApprovalController extends Controller
{
    public function __construct(
        private ApprovalEngineService $engine,
        private AuditService $audit,
        private PdfGeneratorService $pdf,
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

        if (!in_array($document->status, ['draft', 'requires_changes'])) {
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

    /**
     * Решения новых видов звеньев: заключение, ознакомление, приём.
     * Набор кнопок задаётся видом звена — чужое действие сюда не пройдёт.
     */
    public function decide(ApproveDocumentRequest $request, Document $document, string $action)
    {
        $this->authorize('approve', $document);

        $stage = $document->activeApproval?->activeStage();

        // Ознакомление не держит маршрут: маршрут мог уйти дальше (приём) или закрыться.
        // Если участник не входит в текущее активное звено — берём его ack-звено по
        // незакрытой задаче, чтобы «Ознакомлен» сработало при любом статусе документа.
        if ($action === 'acknowledge') {
            $inActive = $stage?->workflowStage?->approvers()
                ->where('approver_id', auth()->id())->exists() ?? false;
            if (!$inActive) {
                $stage = $document->myPendingAckStage(auth()->id()) ?? $stage;
            }
        }

        if (!$stage) {
            return back()->with('error', 'Нет активного этапа.');
        }

        if (!in_array($action, $stage->workflowStage->actions(), true)) {
            return back()->with('error', 'Это действие недоступно на текущем звене.');
        }

        $this->engine->processDecision($stage, auth()->user(), $action, $request->comment);

        $label = \App\Models\WorkflowStage::ACTION_LABELS[$action] ?? $action;

        $this->audit->log(auth()->user()->name . ' — «' . $label . '» по документу «' . $document->title . '»', $document);

        return back()->with('success', 'Решение сохранено: ' . mb_strtolower($label) . '.');
    }

    public function processApprove(ApproveDocumentRequest $request, Document $document)
    {
        $this->authorize('approve', $document);

        $stage = $document->activeApproval?->activeStage();
        if (!$stage) {
            return back()->with('error', 'Нет активного этапа согласования.');
        }

        $this->engine->processDecision($stage, auth()->user(), 'process_approve', $request->comment);

        $this->audit->log(auth()->user()->name . ' одобрил (процесс) «' . $document->title . '»', $document);

        return back()->with('success', 'Решение записано.');
    }

    public function processReject(ApproveDocumentRequest $request, Document $document)
    {
        $this->authorize('approve', $document);

        $stage = $document->activeApproval?->activeStage();
        if (!$stage) {
            return back()->with('error', 'Нет активного этапа согласования.');
        }

        $this->engine->processDecision($stage, auth()->user(), 'process_reject', $request->comment);

        $this->audit->log(auth()->user()->name . ' не одобрил (процесс) «' . $document->title . '»', $document);

        return back()->with('success', 'Решение записано.');
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

    /**
     * Согласующий или утверждающий дописывает людей в ознакомление и приём:
     * состав этих фаз виден только по ходу маршрута, поэтому его пополняют не только при запуске.
     */
    public function addParticipants(Request $request, Document $document, string $phase)
    {
        $this->authorize('approve', $document);

        abort_unless(in_array($phase, ['ack', 'intake'], true), 404);

        $data = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $approval = $document->activeApproval;
        $stage    = $approval?->activeStage();

        if (!$stage || !in_array($stage->workflowStage->kind(), ['approval', 'approve'], true)) {
            return back()->with('error', 'Добавлять участников можно на согласовании и утверждении.');
        }

        $added = $this->engine->addPhaseParticipants($approval, $phase, $data['user_ids']);
        $label = $phase === 'ack' ? 'ознакомление' : 'приём';

        if (!$added) {
            return back()->with('error', "Никого не добавили: эти люди уже в фазе «{$label}» или фаза уже началась.");
        }

        $this->audit->log(auth()->user()->name . ' добавил участников в ' . $label . ' по документу «' . $document->title . '»', $document);

        return back()->with('success', "Добавлено в {$label}: {$added}.");
    }

    public function cancelApproval(Request $request, Document $document)
    {
        $this->authorize('cancelApproval', $document);

        $approval = $document->activeApproval;
        if (!$approval) {
            return back()->with('error', 'Нет активного согласования.');
        }

        $title = $document->title;
        $id    = $document->id;

        // Delete physical files from storage
        Storage::deleteDirectory("documents/{$id}");
        Storage::deleteDirectory("approvals/{$id}");

        // Delete the document — DB cascades remove all related records
        // (files, approvals, stages, decisions, notes, related files, chats)
        $document->delete();

        $this->audit->log(auth()->user()->name . ' отменил и удалил документ «' . $title . '»');

        return redirect()->route('documents.index')
            ->with('success', 'Согласование отменено. Документ и все связанные файлы удалены.');
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

    /** Лист ознакомления — доступен, когда высказались все ознакомляющие. */
    public function acknowledgmentSheet(Document $document)
    {
        return $this->phaseSheet($document, 'ack', 'Лист_ознакомления');
    }

    /** Лист приёма — доступен, когда высказались все принимающие. */
    public function acceptanceSheet(Document $document)
    {
        return $this->phaseSheet($document, 'intake', 'Лист_приёма');
    }

    private function phaseSheet(Document $document, string $phase, string $fileLabel)
    {
        $this->authorize('view', $document);

        // Лист собирается только когда высказались все участники фазы.
        $approval = $document->approvals()
            ->with(['stages.decisions', 'stages.workflowStage.approvers'])
            ->latest()
            ->first();

        abort_unless($approval && $approval->phaseComplete($phase), 404);

        $path = $this->pdf->generatePhaseSheet($document, $phase);

        return Storage::download($path, "{$fileLabel}_{$document->id}.pdf");
    }
}
