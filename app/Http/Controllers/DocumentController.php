<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\DocumentType;
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
            );
        } elseif ($user->role === 'director') {
            $query->where(fn($q) => $q
                ->whereHas('initiator', fn($q2) => $q2->where('department_id', $user->department_id))
                ->orWhere('initiator_id', $user->id)
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
        $documentTypes = DocumentType::with('fields')->get();
        $users = User::where('is_active', true)->where('id', '!=', auth()->id())
            ->with('department')->orderBy('name')->get(['id', 'name', 'role', 'department_id']);
        return view('documents.create', compact('documentTypes', 'users'));
    }

    public function store(StoreDocumentRequest $request)
    {
        $document = Document::create([
            'title'            => $request->title,
            'document_type_id' => $request->document_type_id ?: null,
            'initiator_id'     => auth()->id(),
            'status'           => 'draft',
            'data'             => $request->data ?? [],
            'deadline_at'      => $request->deadline_at ?: null,
        ]);

        if ($request->hasFile('file')) {
            $this->versionService->storeFile($document, $request->file('file'));
        }

        $this->auditService->log('document_created', $document, null, $document->toArray());

        // Auto-start ad-hoc approval if approvers selected
        if ($request->filled('approvers') && is_array($request->approvers)) {
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
            'initiator.department',
            'files',
            'activeApproval.workflow.stages.approvers.user',
            'activeApproval.stages.decisions.user',
            'activeApproval.stages.workflowStage.approvers.user',
            'approvals.workflow.stages.approvers.user',
            'approvals.stages.decisions.user',
            'approvals.stages.workflowStage.approvers.user',
            'notes.author',
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

        return view('documents.show', compact('document', 'approvers'));
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
            $query->whereHas('approvals.stages', fn($q) => $q->where('deadline', '<', now()));
        } elseif ($filter === 'pending') {
            $query->whereHas('approvals.stages', fn($q) => $q->where('status', 'active'));
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
