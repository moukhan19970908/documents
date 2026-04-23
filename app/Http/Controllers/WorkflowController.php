<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index()
    {
        $workflows = Workflow::with(['documentType', 'creator', 'stages' => function ($q) {
            $q->withCount('approvers')->orderBy('sort_order');
        }])->get();
        return view('workflows.index', compact('workflows'));
    }

    public function create()
    {
        $this->authorize('create', Workflow::class);
        $documentTypes = DocumentType::all();
        $users = User::where('is_active', true)->get();
        return view('workflows.create', compact('documentTypes', 'users'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Workflow::class);

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'stages'           => ['nullable', 'array'],
        ]);

        $workflow = Workflow::create([
            'name'             => $validated['name'],
            'document_type_id' => $validated['document_type_id'] ?? null,
            'created_by'       => auth()->id(),
            'is_system'        => false,
            'is_active'        => false,
        ]);

        $this->saveStages($workflow, $request->input('stages', []));

        $this->auditService->log('workflow_created', $workflow);

        return redirect()->route('workflows.show', $workflow)->with('success', 'Воркфлоу создан.');
    }

    public function show(Workflow $workflow)
    {
        $workflow->load(['stages.approvers', 'documentType', 'creator']);
        $users = User::where('is_active', true)->get();
        return view('workflows.builder', compact('workflow', 'users'));
    }

    public function edit(Workflow $workflow)
    {
        $this->authorize('update', $workflow);
        $workflow->load(['stages.approvers']);
        $documentTypes = DocumentType::all();
        $users = User::where('is_active', true)->get();
        return view('workflows.edit', compact('workflow', 'documentTypes', 'users'));
    }

    public function update(Request $request, Workflow $workflow)
    {
        $this->authorize('update', $workflow);

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'stages'           => ['nullable', 'array'],
        ]);

        $workflow->update([
            'name'             => $validated['name'],
            'document_type_id' => $validated['document_type_id'] ?? null,
        ]);

        // Rebuild stages
        $workflow->stages()->delete();
        $this->saveStages($workflow, $request->input('stages', []));

        $this->auditService->log('workflow_updated', $workflow);

        return redirect()->route('workflows.show', $workflow)->with('success', 'Воркфлоу обновлён.');
    }

    public function destroy(Workflow $workflow)
    {
        $this->authorize('delete', $workflow);
        $this->auditService->log('workflow_deleted', $workflow);
        $workflow->delete();
        return redirect()->route('workflows.index')->with('success', 'Воркфлоу удалён.');
    }

    public function publish(Workflow $workflow)
    {
        $this->authorize('update', $workflow);
        $workflow->update(['is_active' => true]);
        $this->auditService->log('workflow_published', $workflow);
        return back()->with('success', 'Воркфлоу опубликован.');
    }

    private function saveStages(Workflow $workflow, array $stages): void
    {
        foreach ($stages as $index => $stageData) {
            $stage = WorkflowStage::create([
                'workflow_id'    => $workflow->id,
                'name'           => $stageData['name'] ?? 'Этап ' . ($index + 1),
                'stage_type'     => $stageData['stage_type'] ?? 'sequential',
                'sort_order'     => $index,
                'deadline_hours' => $stageData['deadline_hours'] ?? null,
            ]);

            foreach ($stageData['approvers'] ?? [] as $approverId) {
                WorkflowStageApprover::create([
                    'workflow_stage_id' => $stage->id,
                    'approver_type'     => 'user',
                    'approver_id'       => $approverId,
                    'is_required'       => true,
                ]);
            }
        }
    }
}
