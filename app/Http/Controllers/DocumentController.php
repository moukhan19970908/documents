<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\BlankTemplate;
use App\Models\Chat;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentNote;
use App\Models\DocumentWatcher;
use App\Models\DocumentType;
use App\Models\Task;
use App\Models\Workflow;
use App\Models\User;
use App\Models\Department;
use App\Services\AuditService;
use App\Services\ApprovalEngineService;
use App\Services\DocumentNamingService;
use App\Services\DocumentNumberService;
use App\Services\DocumentVersionService;
use App\Services\PdfGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mews\Purifier\Facades\Purifier;

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
            $deptIds = Department::getDescendantIds((int) $department);
            $query->whereHas('initiator', fn($q) => $q->whereIn('department_id', $deptIds));
        }

        // Процессная страница (Кредитный комитет и т.п.): только документы,
        // чей сценарий относится к этому типу процесса.
        $process = $request->route('process');
        if ($process) {
            $query->whereHas('workflow', fn ($q) => $q->where('process_type', $process));
        }

        // Apply access-level-based filtering
        $user      = auth()->user();
        $docAccess = $user->resolveWorkflowAccess();

        // Closure: documents where the user is an approver, delegator, or delegatee in any stage
        $isInvolved = fn($q) => $q
            ->where('initiator_id', $user->id)
            ->orWhereHas('approvals.stages.workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id))
            ->orWhereHas('approvals.stages.decisions', fn($q2) => $q2
                ->where('delegated_to', $user->id)           // delegated TO this user
                ->orWhere('user_id', $user->id)              // this user made any decision (approve/reject/delegate/…)
            );

        // Watch rules broaden visibility: a watcher also sees documents of the
        // users they observe (per scope), without gaining any action rights.
        $watchClause = $this->watchVisibilityClause($user);

        // Apply a restriction while still letting watched documents through.
        $restrictWith = function (\Closure $restriction) use ($query, $watchClause) {
            $query->where(function ($q) use ($restriction, $watchClause) {
                $q->where($restriction);
                if ($watchClause) {
                    $q->orWhere($watchClause);
                }
            });
        };

        if ($user->isAdmin()) {
            // Admin: by default show only documents where they participate;
            // pass ?scope=all to see all documents in the workspace.
            if ($request->get('scope') !== 'all') {
                $restrictWith($isInvolved);
            }
        } elseif ($docAccess === 'department' && $user->department_id) {
            // Учитываем кросс-видимость направления (visibleScopeIds).
            $accessibleDeptIds = Department::visibleScopeIds($user->department_id);
            $restrictWith(fn($q) => $q
                ->whereHas('initiator', fn($q2) => $q2->whereIn('department_id', $accessibleDeptIds))
                ->orWhereHas('approvals.stages.workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id))
                ->orWhereHas('approvals.stages.decisions', fn($q2) => $q2
                    ->where('delegated_to', $user->id)
                    ->orWhere('user_id', $user->id)
                )
            );
        } elseif ($docAccess === 'own') {
            $restrictWith($isInvolved);
        }
        // full access non-admin: no restriction — all documents visible

        $documents     = $query->paginate(25)->withQueryString();
        $documentTypes = DocumentType::all();

        // Approver candidates for the "Свой сценарий" (ad-hoc) form.
        // Linear users may pick only colleagues from their department + the department head.
        $user->loadMissing('department');
        $approversQuery = User::where('is_active', true)
            ->where('role', '!=', 'external')
            ->where('id', '!=', $user->id)
            ->with('department')
            ->orderBy('name');

        if ($user->hasRole('linear') && !$user->hasAnyRole(['admin', 'director']) && $user->department_id) {
            $deptHeadId = $user->department?->head_user_id;
            $approversQuery->where(function ($q) use ($user, $deptHeadId) {
                $q->where('department_id', $user->department_id);
                if ($deptHeadId) {
                    $q->orWhere('id', $deptHeadId);
                }
            });
        }

        $approverCandidates = $approversQuery->get(['id', 'name', 'department_id']);

        if ($user->isAdmin() || $docAccess === 'full') {
            $departments = Department::all();
        } elseif ($docAccess === 'department' && $user->department_id) {
            $departments = Department::whereIn('id', Department::visibleScopeIds($user->department_id))->get();
        } else {
            $departments = collect();
        }

        $processMeta = $this->processMeta($process);

        return view('documents.index', compact('documents', 'documentTypes', 'departments', 'approverCandidates', 'processMeta'));
    }

    /** Сценарии без указанного процесса относятся к общему документообороту (legacy). */
    private const GENERAL_PROCESS = [null, '', 'document_flow'];

    /** Ограничить запрос сценариев процессом страницы (или общим документооборотом). */
    private function scopeWorkflowsToProcess($query, ?string $process)
    {
        return $process
            ? $query->where('process_type', $process)
            : $query->where(fn ($q) => $q->whereNull('process_type')->orWhereIn('process_type', ['', 'document_flow']));
    }

    /** Подходит ли сценарий (коллекция) процессу страницы. */
    private function workflowMatchesProcess($workflow, ?string $process): bool
    {
        return $process
            ? $workflow->process_type === $process
            : in_array($workflow->process_type, self::GENERAL_PROCESS, true);
    }

    /** Контекст процессной страницы (заголовок, маршруты) или null для общего документооборота. */
    private function processMeta(?string $process): ?array
    {
        if (!$process) {
            return null;
        }

        $base = ['credit_committee' => 'credit-committee'][$process] ?? null;

        return [
            'type'         => $process,
            'label'        => Workflow::PROCESS_TYPES[$process] ?? $process,
            'index_route'  => $base ? "{$base}.index" : 'documents.index',
            'create_route' => $base ? "{$base}.create" : 'documents.create',
        ];
    }

    /**
     * Условие видимости по правилам наблюдения: документы всех целей,
     * за которыми наблюдает пользователь (с учётом области). null — правил нет.
     */
    private function watchVisibilityClause(User $user): ?\Closure
    {
        $rules = DocumentWatcher::where('watcher_id', $user->id)->get(['target_id', 'scope']);

        if ($rules->isEmpty()) {
            return null;
        }

        return function ($q) use ($rules) {
            foreach ($rules as $rule) {
                $q->orWhere(fn ($sub) => $sub->participatedBy($rule->target_id, $rule->scope));
            }
        };
    }

    public function create()
    {
        $this->authorize('create', Document::class);

        $user = auth()->user();

        // External participants may only initiate their own ad-hoc "Свой сценарий",
        // which lives as a modal on the documents list — no workflow-based form.
        if ($user->isExternal()) {
            return redirect()->route('documents.index');
        }

        // Каждая процессная страница создаёт документ только своего типа процесса.
        // Общий документооборот (процесс не задан) показывает свои сценарии
        // (document_flow + legacy без процесса), исключая приказы, кредитный комитет и т.п.
        $process = request()->route('process');

        $workflows = $this->availableWorkflows($user)
            ->filter(fn ($w) => $this->workflowMatchesProcess($w, $process))
            ->values();

        // The classifier drives the form: type → subtype → scenario.
        // Звенья с исполнителями — визард показывает маршрут по фазам ещё до запуска.
        $documentTypes = DocumentType::where('is_active', true)
            ->with(['fields', 'numerator', 'subtypes' => fn ($q) => $q->where('is_active', true)
                ->with(['fields', 'numerator',
                        'workflows' => fn ($w) => $this->scopeWorkflowsToProcess($w, $process),
                        'workflows.parameters', 'workflows.blankTemplates',
                        'workflows.stages' => fn ($s) => $s->orderBy('sort_order')->with('approvers')])])
            ->orderBy('name')
            ->get()
            ->filter(fn (DocumentType $type) => $type->isAvailableFor($user))
            ->values();

        // Права запуска действуют и на пути «тип → подтип → сценарий»: сценарии, недоступные
        // сотруднику, не должны попадать в форму создания.
        $documentTypes->each(fn (DocumentType $type) => $type->subtypes->each(
            fn ($subtype) => $subtype->setRelation(
                'workflows',
                $subtype->workflows->filter(fn ($w) => $w->isLaunchableBy($user))->values()
            )
        ));

        $processMeta = $this->processMeta($process);

        return view('documents.create', compact('workflows', 'documentTypes', 'processMeta'));
    }

    public function store(StoreDocumentRequest $request, DocumentNamingService $namingService, DocumentNumberService $numberService)
    {
        // Merge custom_fields into data
        $data = array_merge($request->data ?? [], $request->custom_fields ?? []);

        // External participants can only run an ad-hoc custom scenario — never a
        // predefined workflow or document type.
        $isExternal = auth()->user()->isExternal();

        $document = DB::transaction(function () use ($request, $data, $isExternal, $namingService) {
            // Бланк даёт заготовку тела; токены в ней остаются — их подставит показ документа.
            $blank = $isExternal || !$request->blank_template_id
                ? null
                : BlankTemplate::find($request->blank_template_id);

            // Тело: то, что инициатор набрал в редакторе на шаге «Документ»; если он его
            // не трогал — заготовка бланка как есть.
            $body = $blank
                ? ($request->filled('body_html') ? Purifier::clean($request->input('body_html'), 'blank') : $blank->content)
                : null;

            $document = Document::create([
                'title'               => $request->title,
                'workflow_id'         => $isExternal ? null : ($request->workflow_id ?: null),
                'document_type_id'    => $isExternal ? null : ($request->document_type_id ?: null),
                'document_subtype_id' => $isExternal ? null : ($request->document_subtype_id ?: null),
                'blank_template_id'   => $blank?->id,
                'body_html'           => $body,
                'initiator_id'        => auth()->id(),
                'status'              => 'draft',
                'data'                => $data,
                'deadline_at'         => $request->deadline_at ?: null,
            ]);

            // The name is a projection of the fields — the draft one still carries the ___ stubs.
            if ($name = $namingService->forDocument($document)) {
                $document->update(['title' => $name]);
            }

            if ($request->hasFile('file')) {
                $this->versionService->storeFile($document, $request->file('file'));
            }

            return $document;
        });

        $this->auditService->log('document_created', $document, null, $document->toArray());

        $this->registerManually($document, $request->manual_number, $numberService);

        // Шаг «Запуск» даёт выбор: сохранить черновиком или сразу отправить по маршруту.
        // Черновик остаётся без номера и без маршрута — запустить его можно со страницы документа.
        if ($request->input('action') === 'draft') {
            return redirect()->route('documents.show', $document)
                ->with('success', 'Черновик сохранён. Запустить согласование можно на этой странице.');
        }

        // Auto-start approval from the selected workflow
        if ($document->workflow_id) {
            $workflow = Workflow::with('stages.approvers')->find($document->workflow_id);
            if ($workflow) {
                // Answers to the launch parameters decide which stages join this document's route;
                // adhoc — участники ознакомления и приёма, добавленные инициатором при запуске.
                $this->approvalEngine->startApproval(
                    $document,
                    $workflow,
                    $request->input('parameters', []),
                    $request->input('adhoc', []),
                    array_filter($request->input('role_picks', [])),
                );
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

    /** Back-dated paper registration: the number is taken as typed and the counter stays put. */
    private function registerManually(Document $document, ?string $number, DocumentNumberService $numberService): void
    {
        if (blank($number)) {
            return;
        }

        $numerator = $numberService->numeratorFor($document);

        if (!$numerator || !$numerator->allowsManualFor(auth()->user())) {
            return;
        }

        $numberService->register($document, $numerator, auth()->user(), $number);
    }

    private function availableWorkflows(User $user)
    {
        return Workflow::where('is_active', true)
            ->with(['stages.approvers.user', 'blankTemplates'])
            ->orderBy('name')
            ->get()
            // Права запуска: доступ выдан отделу пользователя либо ему лично (см. Workflow::isLaunchableBy).
            ->filter(fn ($workflow) => $workflow->isLaunchableBy($user))
            ->values();
    }

    public function show(Document $document, DocumentNamingService $namingService)
    {
        $this->authorize('view', $document);

        $document->load([
            'type.fields',
            'workflow.parameters',
            'latestApproval',
            'blank',
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
            ->where('role', '!=', 'external')
            ->where('id', '!=', $document->initiator_id)
            ->with('department')
            ->orderBy('name');

        if ($user->hasRole('linear') && !$user->hasAnyRole(['admin', 'director']) && $user->department_id) {
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

        // Токены бланка подставляются при показе: номер и дата приходят к документу
        // позже, при регистрации, — в сохранённом теле они так и остаются токенами.
        $blankBody = $namingService->fillBlank($document);

        // Ответы на параметры запуска (шаг «Параметры» сценария) — их вводили при запуске
        // и хранят в согласовании; подписи берём из параметров сценария по ключу.
        $parameterLabels = $document->workflow?->parameters->pluck('label', 'key') ?? collect();
        $launchParameters = collect($document->latestApproval?->parameter_values ?? [])
            ->reject(fn ($value) => $value === '' || $value === null)
            ->map(fn ($value, $key) => [
                'label' => $parameterLabels[$key] ?? $key,
                'value' => is_array($value) ? implode(', ', $value) : $value,
            ])
            ->values();

        return view('documents.show', compact('document', 'approvers', 'chat', 'blankBody', 'launchParameters'));
    }

    /** Тело документа, заполняемого по бланку. Токены в нём остаются — их подставит показ. */
    public function updateBlank(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        abort_if($document->blank_template_id === null, 404);

        $validated = $request->validate(['body_html' => ['nullable', 'string']]);

        $document->update([
            'body_html' => filled($validated['body_html'] ?? null)
                ? Purifier::clean($validated['body_html'], 'blank')
                : null,
        ]);

        $this->auditService->log('document_blank_updated', $document);

        return redirect()->route('documents.show', $document)->with('success', 'Бланк сохранён.');
    }

    public function storeNote(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $document->notes()->create([
            'user_id' => auth()->id(),
            'body'    => $request->body,
        ]);

        return back()->with('success', 'Комментарий добавлен.');
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

        // Ad-hoc workflows created for a "свой сценарий" document are linked only
        // through approvals (not documents.workflow_id). Capture them before the
        // document — and its approvals — are removed, so we can clean them up after.
        $adHocWorkflowIds = $document->approvals()
            ->whereHas('workflow', fn($q) => $q->where('is_system', false)->where('is_active', false))
            ->pluck('workflow_id')
            ->unique();

        $this->auditService->log('document_deleted', $document);

        DB::transaction(function () use ($document, $adHocWorkflowIds) {
            // Physical files (main versions + related files) live under documents/{id}.
            Storage::disk('s3')->deleteDirectory("documents/{$document->id}");

            // DB cascades remove files, notes, approvals, stages, decisions,
            // related files, chats, messages and tasks.
            $document->delete();

            // Remove orphaned ad-hoc workflows (cascades their stages and approvers).
            foreach ($adHocWorkflowIds as $workflowId) {
                if (!DocumentApproval::where('workflow_id', $workflowId)->exists()) {
                    Workflow::whereKey($workflowId)->delete();
                }
            }
        });

        return redirect()->route('documents.index')->with('success', 'Документ удалён.');
    }

    public function tasks(Request $request)
    {
        $user = auth()->user();
        $access = $user->resolveTasksAccess();

        if ($access === 'none') {
            abort(403, 'Нет доступа к задачам.');
        }

        // Переключатель «Обычный / Администратор» доступен только администратору.
        // Все остальные видят строго свои задачи, независимо от уровня доступа к разделу.
        $canSeeAll = $user->isAdmin();
        $scope     = ($canSeeAll && $request->get('scope') === 'all') ? 'all' : 'mine';

        // Pending tasks: документы (согласование/утверждение/ознакомление/приём/доработка) и приказы.
        $taskQuery = Task::with([
                'document.type',
                'document.initiator',
                'assignee',
                'stage.workflowStage',
                'document.activeApproval.stages.workflowStage',
                'order.initiator',
            ])
            ->where('status', 'pending');

        if ($scope === 'mine') {
            $taskQuery->where('assignee_id', $user->id);
        }
        // Админ в режиме «Администратор» — без ограничений, видит все задачи

        // Normalise all sources into a single card list for the view.
        $items = collect();

        foreach ($taskQuery->get() as $task) {
            // Задача по приказу (проверка перед публикацией или ознакомление).
            if ($task->order_id) {
                $order = $task->order;
                if (! $order) {
                    continue;
                }
                $items->push([
                    'kind'        => $order->status === 'on_approval' ? 'soglas' : 'oznak',
                    'title'       => $task->title,
                    'document'    => null,
                    'link'        => route('orders.show', $order),
                    'ref'         => $order->number ?? ('Приказ #' . $order->id),
                    'abbr'        => 'ПРК',
                    'stage_label' => null,
                    'initiator'   => $order->initiator,
                    'assignee'    => $task->assignee,
                    'deadline'    => $task->deadline_at,
                    'is_overdue'  => $task->isOverdue(),
                ]);
                continue;
            }

            $doc = $task->document;
            if (! $doc) {
                continue;
            }
            // Задача без звена — это «Доработайте документ» инициатору.
            $kind = $task->document_approval_stage_id === null
                ? 'dorabotka'
                : $this->phaseKind($task->stage?->workflowStage?->phase, $doc->type?->name, $task->stage?->workflowStage?->name);

            $items->push([
                'kind'        => $kind,
                'title'       => $task->title,
                'document'    => $doc,
                'link'        => route('documents.show', $doc),
                'ref'         => 'D-' . $doc->id,
                'abbr'        => $this->typeAbbr($doc->type?->name),
                'stage_label' => $this->stagePosition($doc->activeApproval, $task->document_approval_stage_id),
                'initiator'   => $doc->initiator,
                'assignee'    => $task->assignee,
                'deadline'    => $task->deadline_at,
                'is_overdue'  => $task->isOverdue(),
            ]);
        }

        // Legacy: документы на доработке БЕЗ задачи (созданные до появления задач-доработок).
        $reworkDocIds = Task::whereNull('document_approval_stage_id')->whereNull('order_id')
            ->where('status', 'pending')->pluck('document_id')->filter()->all();

        $revisionQuery = Document::with(['type', 'initiator', 'latestApproval.stages.workflowStage'])
            ->where('status', 'requires_changes')
            ->whereNotIn('id', $reworkDocIds ?: [0]);

        if ($scope === 'mine') {
            $revisionQuery->where('initiator_id', $user->id);
        }

        foreach ($revisionQuery->get() as $doc) {
            $items->push([
                'kind'        => 'dorabotka',
                'title'       => 'Доработайте документ ' . $doc->title,
                'document'    => $doc,
                'link'        => route('documents.show', $doc),
                'ref'         => 'D-' . $doc->id,
                'abbr'        => $this->typeAbbr($doc->type?->name),
                'stage_label' => null,
                'initiator'   => $doc->initiator,
                'assignee'    => $doc->initiator,
                'deadline'    => $doc->deadline_at,
                'is_overdue'  => $doc->deadline_at && now()->gt($doc->deadline_at),
            ]);
        }

        // Overdue first, then soonest deadline (no deadline last).
        $items = $items
            ->sortBy(fn($i) => [$i['is_overdue'] ? 0 : 1, optional($i['deadline'])->timestamp ?? PHP_INT_MAX])
            ->values();

        return view('tasks.index', compact('items', 'scope', 'canSeeAll'));
    }

    /**
     * Derive the business action-kind of a task from its (freeform) stage name,
     * falling back to the document type. The app has no stored "phase" field.
     */
    private function deriveTaskKind(?string $stageName, ?string $typeName): string
    {
        $n = mb_strtolower($stageName ?? '');
        $t = mb_strtolower($typeName ?? '');

        return match (true) {
            str_contains($n, 'ознаком')                                                     => 'oznak',
            str_contains($n, 'приём') || str_contains($n, 'прием') || str_contains($n, 'исполн') => 'priem',
            str_contains($n, 'соглас') || str_contains($n, 'одобр') || str_contains($n, 'утвержд') => 'soglas',
            str_contains($t, 'приказ')                                                      => 'oznak',
            str_contains($t, 'поручен') || str_contains($t, 'задани')                       => 'priem',
            default                                                                         => 'soglas',
        };
    }

    /** Карточная категория задачи по фазе звена; для старых звеньев без фазы — эвристика по названию. */
    private function phaseKind(?string $phase, ?string $typeName, ?string $stageName): string
    {
        return match ($phase) {
            'ack'                          => 'oznak',
            'intake'                       => 'priem',
            'approval', 'approve', 'opinion' => 'soglas',
            default                        => $this->deriveTaskKind($stageName, $typeName),
        };
    }

    /** Аббревиатура типа для значка карточки ([ДК] по умолчанию). */
    private function typeAbbr(?string $typeName): string
    {
        $words = collect(preg_split('/\s+/', trim((string) $typeName)))->filter()->values();

        return $words->count() >= 2
            ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1))
            : ($words->count() ? mb_strtoupper(mb_substr($words[0], 0, 2)) : 'ДК');
    }

    /** "этап X из Y" for a task's stage within its approval, or null. */
    private function stagePosition($approval, ?int $stageId): ?string
    {
        if (!$approval || !$stageId) {
            return null;
        }
        $ordered = $approval->stages->sortBy(fn($s) => $s->workflowStage?->sort_order ?? 0)->values();
        $total   = $ordered->count();
        $pos     = $ordered->search(fn($s) => $s->id === $stageId);

        return ($total && $pos !== false) ? 'этап ' . ($pos + 1) . ' из ' . $total : null;
    }

    public function approvalSheet(Document $document)
    {
        $this->authorize('view', $document);

        $path = $this->pdfService->generateApprovalSheet($document);

        return Storage::download($path, 'approval_sheet_' . $document->id . '.pdf');
    }
}
