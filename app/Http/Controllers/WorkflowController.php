<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowFolder;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index(Request $request)
    {
        $user        = auth()->user()->load('department');
        $accessLevel = $user->resolveWorkflowAccess();

        if ($accessLevel === 'none') {
            return redirect()->route('dashboard');
        }

        $folderId = $request->query('folder_id');

        $query = Workflow::where('is_active', true)->with(['documentType', 'creator', 'stages' => function ($q) {
            $q->withCount('approvers')->orderBy('sort_order');
        }, 'folders']);

        // Department-level access: show only workflows that include the user's department
        if ($accessLevel === 'department' && $user->department_id) {
            $deptId = $user->department_id;
            $query->where(function ($q) use ($deptId) {
                $q->whereNull('allowed_departments')
                  ->orWhere('allowed_departments', '[]')
                  ->orWhereJsonContains('allowed_departments', $deptId);
            });
        }

        if ($folderId) {
            $query->whereHas('folders', fn($q) => $q->where('workflow_folders.id', $folderId));
        }

        $workflows = $query->get();

        $folderTree = WorkflowFolder::with(['children' => fn($q) => $q->withCount('workflows')])
            ->withCount('workflows')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $totalCount = Workflow::where('is_active',true)->count();
        $currentFolder = $folderId ? WorkflowFolder::find($folderId) : null;

        return view('workflows.index', compact('workflows', 'folderTree', 'totalCount', 'currentFolder', 'folderId'));
    }

    public function apiIndex()
    {
        $workflows = Workflow::where('is_active', true)
            ->with(['stages.approvers.user'])
            ->orderBy('name')
            ->get()
            ->map(fn($w) => [
                'id'     => $w->id,
                'name'   => $w->name,
                'fields' => $w->process_fields ?? [],
                'approvers' => $w->stages->flatMap(fn($s) => $s->approvers)->map(fn($a) => [
                    'id'   => $a->user?->id,
                    'name' => $a->user?->name,
                ])->filter(fn($a) => $a['id'])->unique('id')->values(),
            ]);

        return response()->json($workflows);
    }

    public function create()
    {
        $this->authorize('create', Workflow::class);
        $departments = Department::orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'position', 'department_id']);
        $deptNamesById = $departments->pluck('name', 'id');
        $usersForJs = $users->map(fn($u) => [
            'id'            => $u->id,
            'name'          => $u->name,
            'position'      => $u->position ?? '',
            'department_id' => $u->department_id,
            'deptName'      => $deptNamesById[$u->department_id] ?? '',
        ]);
        $folderTree = WorkflowFolder::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        return view('workflows.create', compact('users', 'usersForJs', 'departments', 'folderTree'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Workflow::class);

        $validated = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string'],
            'approval_type'            => ['required', 'in:sequential,parallel,parallel_sequential'],
            'approver_ids'             => ['nullable', 'array'],
            'approver_ids.*'           => ['exists:users,id'],
            'allowed_department_ids'   => ['nullable', 'array'],
            'allowed_department_ids.*' => ['exists:departments,id'],
            'allowed_user_ids'         => ['nullable', 'array'],
            'allowed_user_ids.*'       => ['exists:users,id'],
            'folder_ids'               => ['nullable', 'array'],
            'folder_ids.*'             => ['exists:workflow_folders,id'],
            'process_fields'           => ['nullable', 'array'],
            'process_fields.*.name'    => ['required_with:process_fields', 'string', 'max:255'],
            'process_fields.*.type'    => ['required_with:process_fields', 'in:string,number,date,file'],
        ]);

        $workflow = Workflow::create([
            'name'                => $validated['name'],
            'description'         => $validated['description'] ?? null,
            'approval_type'       => $validated['approval_type'],
            'allowed_departments' => $validated['allowed_department_ids'] ?? null,
            'allowed_users'       => $validated['allowed_user_ids'] ?? null,
            'process_fields'      => $validated['process_fields'] ?? null,
            'created_by'          => auth()->id(),
            'is_system'           => false,
            'is_active'           => true,
        ]);

        // Create initial stage with selected approvers
        $approverIds = $validated['approver_ids'] ?? [];
        if (count($approverIds) > 0) {
            $stageType = match ($validated['approval_type']) {
                'parallel', 'parallel_sequential' => 'parallel',
                default                           => 'sequential',
            };
            $stage = WorkflowStage::create([
                'workflow_id' => $workflow->id,
                'name'        => 'Этап 1',
                'stage_type'  => $stageType,
                'sort_order'  => 0,
            ]);
            foreach ($approverIds as $userId) {
                WorkflowStageApprover::create([
                    'workflow_stage_id' => $stage->id,
                    'approver_type'     => 'user',
                    'approver_id'       => $userId,
                    'is_required'       => true,
                ]);
            }
        }

        $this->auditService->log('workflow_created', $workflow);

        // Attach folders
        if (!empty($validated['folder_ids'])) {
            $workflow->folders()->sync($validated['folder_ids']);
        }

        return redirect()->route('workflows.show', $workflow)->with('success', 'Воркфлоу создан.');
    }

    public function show(Workflow $workflow)
    {
        $workflow->load(['stages.approvers', 'documentType', 'creator']);
        $users = User::where('is_active', true)->get();
        return view('workflows.builder', compact('workflow', 'users'));
    }

    public function builder(Workflow $workflow)
    {
        return $this->show($workflow);
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
