<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Chat;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Workflow;
use App\Models\User;
use App\Models\Department;
use App\Services\AuditService;
use App\Services\ApprovalEngineService;
use App\Services\DocumentVersionService;
use App\Services\PdfGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(
        private AuditService $auditService,
        private DocumentVersionService $versionService,
        private PdfGeneratorService $pdfService,
        private ApprovalEngineService $approvalEngine,
    ) {}

    public function index(Request $request)
    {
        $query = Document::with([
                'type', 'initiator',
                'activeApproval' => function ($q) {
                    $q->with(['stages' => function ($sq) {
                        $sq->orderBy('id')->with(['workflowStage.approvers.user', 'decisions']);
                    }]);
                },
                'latestApproval' => function ($q) {
                    $q->with(['stages' => function ($sq) {
                        $sq->orderBy('id')->with(['workflowStage.approvers.user']);
                    }]);
                },
            ])
            ->orderByDesc('updated_at');

        if ($search = $request->get('search')) {
            $query->where(fn($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhereRaw("LOWER(JSON_UNQUOTE(data)) LIKE ?", ['%' . strtolower($search) . '%'])
            );
        }

        if ($type = $request->get('type')) {
            $query->whereHas('type', fn($q) => $q->where('slug', $type));
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($author = $request->get('author')) {
            $query->where('initiator_id', $author);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($department = $request->get('department')) {
            $query->whereHas('initiator', fn($q) => $q->where('department_id', $department));
        }

        // Apply policy filtering
        $user = auth()->user();
        if ($user->role === 'archiver') {
            $query->whereIn('status', ['approved', 'signed', 'archived']);
        } elseif ($user->role === 'linear') {
            $query->where(fn($q) => $q
                ->where('initiator_id', $user->id)
                ->orWhereHas('approvals.stages', fn($q2) => $q2->whereHas('workflowStage.approvers', fn($q3) => $q3->where('approver_id', $user->id)))
                ->orWhereHas('approvals.stages.decisions', fn($q2) => $q2->where('action', 'delegate')->where('delegated_to', $user->id))
            );
        } elseif ($user->role === 'director') {
            $query->where(fn($q) => $q
                ->whereHas('initiator', fn($q2) => $q2->where('department_id', $user->department_id))
                ->orWhere('initiator_id', $user->id)
                ->orWhereHas('approvals.stages.decisions', fn($q2) => $q2->where('action', 'delegate')->where('delegated_to', $user->id))
            );
        }

        $documents = $query->paginate(25)->withQueryString();
        $documentTypes = DocumentType::all();
        $departments = Department::all();

        return view('documents.index', compact('documents', 'documentTypes', 'departments'));
    }

    public function create()
    {
        $this->authorize('create', Document::class);

        $user = auth()->user();

        $workflows = Workflow::where('is_active', true)
            ->with(['stages.approvers.user'])
            ->orderBy('name')
            ->get()
            ->filter(function ($workflow) use ($user) {
                // No department restriction — accessible to all
                if (empty($workflow->allowed_departments)) {
                    return true;
                }
                // User's department must be in allowed_departments
                if (!in_array($user->department_id, $workflow->allowed_departments)) {
                    return false;
                }
                // If specific users are set, user must be in that list
                if (!empty($workflow->allowed_users)) {
                    return in_array($user->id, $workflow->allowed_users);
                }
                return true;
            })
            ->values();

        return view('documents.create', compact('workflows'));
    }

    public function store(StoreDocumentRequest $request)
    {
        // Merge custom_fields into data
        $data = array_merge($request->data ?? [], $request->custom_fields ?? []);

        $document = Document::create([
            'title'            => $request->title,
            'workflow_id'      => $request->workflow_id ?: null,
            'document_type_id' => $request->document_type_id ?: null,
            'initiator_id'     => auth()->id(),
            'status'           => 'draft',
            'data'             => $data,
            'deadline_at'      => $request->deadline_at ?: null,
        ]);

        if ($request->hasFile('file')) {
            $this->versionService->storeFile($document, $request->file('file'));
        }

        $this->auditService->log('document_created', $document, null, $document->toArray());

        // Auto-start approval from the selected workflow
        if ($document->workflow_id) {
            $workflow = Workflow::with('stages.approvers')->find($document->workflow_id);
            if ($workflow) {
                $this->approvalEngine->startApproval($document, $workflow);
                $this->auditService->log(auth()->user()->name . ' начал процесс «' . $document->title . '»', $document);
            }
        } elseif ($request->filled('approvers') && is_array($request->approvers)) {
            // Legacy ad-hoc fallback
            $this->approvalEngine->startAdHocApproval($document, $request->approvers);
            $this->auditService->log(auth()->user()->name . ' начал процесс «' . $document->title . '»', $document);
        }

        return redirect()->route('documents.show', $document)
            ->with('success', 'Документ создан.');
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);

        $document->load([
            'type.fields',
            'workflow',
            'initiator.department',
            'files',
            'activeApproval.workflow.stages.approvers.user',
            'activeApproval.stages.decisions.user',
            'activeApproval.stages.decisions.delegatee.department',
            'activeApproval.stages.workflowStage.approvers.user',
            'approvals.workflow.stages.approvers.user',
            'approvals.stages.decisions.user',
            'approvals.stages.decisions.delegatee',
            'approvals.stages.workflowStage.approvers.user',
            'notes.author',
            'relatedFiles.uploader',
        ]);

        $user = auth()->user();
        $user->loadMissing('department');

        // linear: only users from the same department + the department head
        // director (and others): all users
        $approversQuery = User::where('is_active', true)
            ->where('id', '!=', $document->initiator_id)
            ->with('department')
            ->orderBy('name');

        if ($user->role === 'linear' && $user->department_id) {
            $deptHeadId = $user->department?->head_user_id;
            $approversQuery->where(function ($q) use ($user, $deptHeadId) {
                $q->where('department_id', $user->department_id);
                if ($deptHeadId) {
                    $q->orWhere('id', $deptHeadId);
                }
            });
        }

        $approvers = $approversQuery->get(['id', 'name', 'role', 'department_id']);

        $chat = Chat::whereHas('approval', fn($q) => $q->where('document_id', $document->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', auth()->id()))
            ->with(['messages' => fn($q) => $q->with('user:id,name')->limit(30)])
            ->latest()
            ->first();

        return view('documents.show', compact('document', 'approvers', 'chat'));
    }

    public function edit(Document $document)
    {
        $this->authorize('update', $document);
        $documentTypes = DocumentType::with('fields')->get();
        return view('documents.edit', compact('document', 'documentTypes'));
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $old = $document->toArray();
        $document->update([
            'title' => $request->title,
            'data'  => $request->data ?? $document->data,
        ]);

        $this->auditService->log('document_updated', $document, $old, $document->toArray());

        return redirect()->route('documents.show', $document)->with('success', 'Документ обновлён.');
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);
        $this->auditService->log('document_deleted', $document);
        $document->delete();
        return redirect()->route('documents.index')->with('success', 'Документ удалён.');
    }

    public function tasks(Request $request)
    {
        $user = auth()->user();
        $filter = $request->get('filter', 'all');

        $query = Document::with(['type', 'initiator'])
            ->whereIn('status', ['in_review', 'requires_changes'])
            ->whereHas('approvals.stages', function ($q) use ($user) {
                $q->where('status', 'active')
                  ->whereHas('workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id));
            })
            ->orderByDesc('updated_at');

        if ($filter === 'overdue') {
            $query->whereHas('approvals.stages', fn($q) => $q->where('deadline_at', '<', now()));
        } elseif ($filter === 'pending') {
            $query->whereHas('approvals.stages', fn($q) => $q->where('status', 'in_progress'));
        } elseif ($filter === 'completed') {
            $query->whereIn('status', ['approved', 'signed']);
        }

        $tasks = $query->paginate(20)->withQueryString();

        return view('tasks.index', compact('tasks', 'filter'));
    }

    public function approvalSheet(Document $document)
    {
        $this->authorize('view', $document);

        $path = $this->pdfService->generateApprovalSheet($document);

        return Storage::download($path, 'approval_sheet_' . $document->id . '.pdf');
    }
}
