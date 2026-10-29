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
use App\Services\BlankPdfService;
use App\Services\DocumentNamingService;
use App\Services\DocumentNumberService;
use App\Services\DocumentVersionService;
use App\Services\FileWatermarkService;
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
        $user    = auth()->user();
        $process = $request->route('process');

        // Обе выборки зовутся на каждую вкладку-счётчик — считаем один раз.
        $watchClause = $this->watchVisibilityClause($user);
        $restriction = $this->accessRestriction($user);
        $myTasks     = $this->myPendingDocumentTasks($user, $process);

        $scopes = $this->availableScopes($user);
        $scope  = $request->get('scope');
        if (!isset($scopes[$scope])) {
            // Поиск из шапки должен находить и уже пройденные документы, а не только ждущие.
            $scope = $request->filled('search') ? 'involved' : 'waiting';
        }

        // Линейному сотруднику — только свой отдел: подотделы ему не подчинены.
        $canSeeSubdepartments = $user->isAdmin() || !$user->isLinearEmployee();
        $withSubdepartments   = $canSeeSubdepartments && $request->boolean('sub');
        $departmentIds      = $this->departmentScopeIds($user, $withSubdepartments);

        $scoped = fn (string $key) => $this->applyScope(
            $this->visibleDocuments($process, $restriction, $watchClause),
            $user, $key, $myTasks, $departmentIds, $watchClause,
        );

        // У «Все документы» вместо счётчика метка «админ».
        $counts = [];
        foreach (array_keys($scopes) as $key) {
            if ($key !== 'all') {
                $counts[$key] = $scoped($key)->count();
            }
        }

        $query = $scoped($scope)
            ->with([
                'type', 'initiator.department', 'pendingTasks.assignee',
                // Подписанты и решения нужны полоске прогресса в колонке «Статус».
                'activeApproval' => fn ($q) => $q->with(['stages' => fn ($sq) => $sq
                    ->orderBy('id')->with(['workflowStage.approvers.user', 'decisions'])]),
                'latestApproval.stages.workflowStage',
            ])
            ->orderByDesc('updated_at');

        // Быстрые фильтры: во «Ждут меня» — что от меня нужно, в «Мой отдел» — где застряло.
        $quick       = $request->get('quick');
        $quickCounts = [];
        if ($scope === 'waiting') {
            $quickCounts = $myTasks->countBy('kind')->all()
                + ['overdue' => $myTasks->where('is_overdue', true)->count()];

            if ($quick === 'overdue') {
                $query->whereIn('id', $myTasks->where('is_overdue', true)->keys());
            } elseif (isset(self::TASK_KINDS[$quick])) {
                $query->whereIn('id', $myTasks->where('kind', $quick)->keys());
            } else {
                $quick = null;
            }
        } elseif ($scope === 'department') {
            $overdue = fn ($q) => $q->whereHas('pendingTasks', fn ($t) => $t->where('deadline_at', '<', now()));
            $fired   = fn ($q) => $q->whereHas('pendingTasks.assignee', fn ($u) => $u->where('is_active', false));

            $quickCounts = [
                'overdue' => $overdue(clone $query)->count(),
                'fired'   => $fired(clone $query)->count(),
            ];

            if ($quick === 'overdue') {
                $overdue($query);
            } elseif ($quick === 'fired') {
                $fired($query);
            } else {
                $quick = null;
            }
        } else {
            $quick = null;
        }

        if ($search = $request->get('search')) {
            $query->where(fn($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%")
                ->orWhereRaw("LOWER(JSON_UNQUOTE(data)) LIKE ?", ['%' . strtolower($search) . '%'])
            );
        }

        if ($type = $request->get('type')) {
            $query->whereHas('type', fn($q) => $q->where('slug', $type));
        }

        if ($subtype = $request->get('subtype')) {
            $query->where('document_subtype_id', $subtype);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($author = $request->get('author')) {
            $query->where('initiator_id', $author);
        }

        if ($direction = $request->get('direction')) {
            // Направление — корневой департамент; фильтруем по всему его поддереву.
            $deptIds = Department::directionMemberIds((int) $direction);
            $query->whereHas('initiator', fn($q) => $q->whereIn('department_id', $deptIds));
        }

        $since = match ($request->get('period')) {
            'week'    => now()->subWeek(),
            'month'   => now()->subMonth(),
            'quarter' => now()->subMonths(3),
            'year'    => now()->subYear(),
            default   => null,
        };
        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $documents = $query->paginate(25)->withQueryString();

        // Фильтр по типу предлагает только типы процесса этой страницы: у общего
        // документооборота и «Кредитного комитета» наборы разные, а отключённым
        // типам и типам чужих процессов («Приказ») в списке делать нечего.
        // Подтипы фильтра берутся отсюда же — в выпадающем списке они сгруппированы
        // по своему типу, поэтому отдельной выборки не нужно.
        $documentTypes = DocumentType::where('is_active', true)
            ->whereHas('workflows', fn ($q) => $this->scopeWorkflowsToProcess($q, $process))
            ->with(['subtypes' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

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

        // Направления — корневые департаменты (без родителя).
        $docAccess = $user->resolveWorkflowAccess();
        if ($user->isAdmin() || $docAccess === 'full') {
            $directions = Department::whereNull('parent_id')->orderBy('name')->get();
        } elseif ($docAccess === 'department' && $user->department_id) {
            $rootId = Department::directionRootId($user->department_id);
            $directions = Department::whereNull('parent_id')->where('id', $rootId)->get();
        } else {
            $directions = collect();
        }

        $departmentEmployees = $scope === 'department'
            ? User::whereIn('department_id', $departmentIds)->orderBy('name')->get(['id', 'name', 'is_active'])
            : collect();

        $processMeta = $this->processMeta($process);
        $taskKinds   = self::TASK_KINDS;
        $periods     = self::PERIODS;

        return view('documents.index', compact(
            'documents', 'documentTypes', 'directions', 'approverCandidates', 'processMeta',
            'scopes', 'scope', 'counts', 'quick', 'quickCounts', 'myTasks', 'taskKinds', 'periods',
            'withSubdepartments', 'canSeeSubdepartments', 'departmentEmployees',
        ));
    }

    /** Вкладки списка: отношение пользователя к документу, а не раздел. */
    private const SCOPES = [
        'waiting'    => 'Ждут меня',
        'involved'   => 'Я участвую',
        'launched'   => 'Я запустил',
        'department' => 'Мой отдел',
        'watched'    => 'Наблюдаю',
        'all'        => 'Все документы',
    ];

    /** Что от пользователя нужно по документу — «Моя роль» во вкладке «Ждут меня». */
    private const TASK_KINDS = [
        'soglas'    => 'Согласовать',
        'zakl'      => 'Дать заключение',
        'oznak'     => 'Ознакомиться',
        'priem'     => 'Принять на исполнение',
        'dorabotka' => 'Доработать',
    ];

    private const PERIODS = [
        'week'    => 'За неделю',
        'month'   => 'За месяц',
        'quarter' => 'За квартал',
        'year'    => 'За год',
    ];

    /**
     * Какие вкладки показать. Свои документы — всем; отдел — всем, у кого он есть;
     * наблюдаемые — при наличии правил наблюдения; все документы — администратору.
     *
     * @return array<string, string>
     */
    private function availableScopes(User $user): array
    {
        $keys = ['waiting', 'involved', 'launched'];

        if ($user->department_id) {
            $keys[] = 'department';
        }

        if (DocumentWatcher::where('watcher_id', $user->id)->exists()) {
            $keys[] = 'watched';
        }

        if ($user->isAdmin()) {
            $keys[] = 'all';
        }

        return array_intersect_key(self::SCOPES, array_flip($keys));
    }

    /** Сужает список до вкладки. «Все документы» ничего не добавляют к правам. */
    private function applyScope($query, User $user, string $scope, $myTasks, array $departmentIds, ?\Closure $watchClause)
    {
        return match ($scope) {
            'waiting'    => $query->whereIn('id', $myTasks->keys()),
            'involved'   => $query->where($this->involvementClause($user)),
            'launched'   => $query->where('initiator_id', $user->id),
            'department' => $query->whereHas('initiator', fn ($q) => $q->whereIn('department_id', $departmentIds)),
            'watched'    => $query->where($watchClause),
            default      => $query,
        };
    }

    /**
     * Отделы вкладки «Мой отдел». «С подотделами» берёт всё направление: отделы
     * прикрепляются к нему через direction_id, а parent_id остаётся деревом Битрикса.
     *
     * @return array<int, int>
     */
    private function departmentScopeIds(User $user, bool $withSubdepartments): array
    {
        if (!$user->department_id) {
            return [];
        }

        return $withSubdepartments
            ? Department::directionMemberIds($user->department_id)
            : [$user->department_id];
    }

    /** Документы, которые пользователю вообще видны на этой странице. */
    private function visibleDocuments(?string $process, ?\Closure $restriction, ?\Closure $watchClause)
    {
        $query = $this->scopeDocumentsToProcess(Document::query(), $process);

        // Наблюдение расширяет видимость: наблюдатель видит документы наблюдаемых, но прав действий не получает.
        if ($restriction) {
            $query->where(function ($q) use ($restriction, $watchClause) {
                $q->where($restriction);
                if ($watchClause) {
                    $q->orWhere($watchClause);
                }
            });
        }

        return $query;
    }

    /** Ограничение по уровню доступа к документообороту; null — «full», без ограничений. */
    private function accessRestriction(User $user): ?\Closure
    {
        $docAccess = $user->resolveWorkflowAccess();

        if ($docAccess === 'department' && $user->department_id) {
            // Учитываем кросс-видимость направления (visibleScopeIds).
            $accessibleDeptIds = Department::visibleScopeIds($user->department_id);

            return fn ($q) => $q
                ->whereHas('initiator', fn ($q2) => $q2->whereIn('department_id', $accessibleDeptIds))
                ->orWhereHas('approvals.stages.workflowStage.approvers', fn ($q2) => $q2->where('approver_id', $user->id))
                ->orWhereHas('approvals.stages.decisions', fn ($q2) => $q2
                    ->where('delegated_to', $user->id)
                    ->orWhere('user_id', $user->id)
                );
        }

        return $docAccess === 'own' ? $this->involvementClause($user) : null;
    }

    /** Документы, где пользователь инициатор, согласующий, делегат или уже принимал решение. */
    private function involvementClause(User $user): \Closure
    {
        return fn ($q) => $q
            ->where('initiator_id', $user->id)
            ->orWhereHas('approvals.stages.workflowStage.approvers', fn ($q2) => $q2->where('approver_id', $user->id))
            ->orWhereHas('approvals.stages.decisions', fn ($q2) => $q2
                ->where('delegated_to', $user->id)
                ->orWhere('user_id', $user->id)
            );
    }

    /**
     * Открытые задачи пользователя по документам страницы, по одной на документ —
     * самая срочная. Ключ — id документа.
     */
    private function myPendingDocumentTasks(User $user, ?string $process)
    {
        return Task::with(['stage.workflowStage', 'document.type'])
            ->where('assignee_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('document', fn ($d) => $this->scopeDocumentsToProcess($d, $process))
            ->orderByRaw('deadline_at IS NULL')
            ->orderBy('deadline_at')
            ->get()
            ->unique('document_id')
            ->mapWithKeys(fn (Task $task) => [$task->document_id => [
                // Задача без звена — это «Доработайте документ» инициатору. Заключение здесь
                // отдельно от согласования: оно не решает судьбу документа (isAdvisory).
                'kind' => match (true) {
                    $task->document_approval_stage_id === null           => 'dorabotka',
                    $task->stage?->workflowStage?->phase === 'opinion'   => 'zakl',
                    default => $this->phaseKind($task->stage?->workflowStage?->phase, $task->document->type?->name, $task->stage?->workflowStage?->name),
                },
                'deadline'   => $task->deadline_at,
                'is_overdue' => $task->isOverdue(),
            ]]);
    }

    /**
     * Процессная страница (Кредитный комитет и т.п.) показывает только документы своего
     * процесса; общий документооборот — документы общих сценариев и без сценария.
     */
    private function scopeDocumentsToProcess($query, ?string $process)
    {
        return $process
            ? $query->whereHas('workflow', fn ($q) => $q->where('process_type', $process))
            : $query->where(fn ($q) => $q
                ->whereDoesntHave('workflow')
                ->orWhereHas('workflow', fn ($w) => $this->scopeWorkflowsToProcess($w, null)));
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
        $rules = DocumentWatcher::where('watcher_id', $user->id)->with('target')->get(['target_id', 'scope']);

        if ($rules->isEmpty()) {
            return null;
        }

        return function ($q) use ($rules) {
            foreach ($rules as $rule) {
                $q->orWhere(fn ($sub) => $sub->participatedBy($rule->target_id, $rule->scope));

                if ($deptIds = $rule->subordinateDepartmentIds()) {
                    $q->orWhereHas('initiator', fn ($i) => $i->whereIn('department_id', $deptIds));
                }
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
                if ($workflow->isComposed()) {
                    // Индивидуальный процесс отдела: маршрут инициатор собрал при запуске.
                    $this->approvalEngine->startComposedApproval(
                        $document,
                        $workflow,
                        $request->input('route', []),
                    );
                } else {
                    // Answers to the launch parameters decide which stages join this document's route;
                    // adhoc — участники ознакомления и приёма, добавленные инициатором при запуске.
                    $this->approvalEngine->startApproval(
                        $document,
                        $workflow,
                        $request->input('parameters', []),
                        $request->input('adhoc', []),
                        array_filter($request->input('role_picks', [])),
                    );
                }
                $this->auditService->log(auth()->user()->name . ' начал процесс «' . $document->title . '»', $document);
            }
        } elseif ($request->filled('approvers') && is_array($request->approvers)) {
            // Legacy ad-hoc fallback (плоский список согласующих — модалка внешних участников)
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
            'subtype.fields',
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

        // Поля карточки, которые инициатор правит в черновике и на доработке.
        $editableFields = $user->can('update', $document) ? $document->editableFields() : collect();
        $referenceOptions = $editableFields->contains('type', 'reference') ? [
            'department' => \App\Models\Department::orderBy('name')->pluck('name'),
            'user'       => User::where('is_active', true)->orderBy('name')->pluck('name'),
        ] : [];

        return view('documents.show', compact('document', 'approvers', 'chat', 'blankBody', 'launchParameters', 'editableFields', 'referenceOptions'));
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

    /**
     * Скачать документ по бланку как PDF. Тело (с подставленными токенами) рендерится в PDF,
     * а у согласованного документа поверх ложится штамп-удостоверение — как у загруженных файлов.
     */
    public function downloadBlank(
        Document $document,
        DocumentNamingService $namingService,
        BlankPdfService $blankPdf,
        FileWatermarkService $watermark,
    ) {
        $this->authorize('view', $document);
        abort_if($document->blank_template_id === null, 404);

        $bytes = $blankPdf->render($document, $namingService->fillBlank($document) ?? '');
        $bytes = $watermark->stampPdfBytes($bytes, $document);

        $this->auditService->log('document_blank_downloaded', $document);

        $name = trim((string) $document->title) !== '' ? $document->title : 'Документ ' . $document->id;

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($name) . '.pdf"',
            'Content-Length'      => strlen($bytes),
        ]);
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

    public function update(Request $request, Document $document, DocumentNamingService $namingService)
    {
        $this->authorize('update', $document);

        $request->validate([
            'title'  => ['nullable', 'string', 'max:255'],
            'data'   => ['nullable', 'array'],
            'data.*' => ['nullable', 'string', 'max:2048'],
        ]);

        // Принимаем только поля карточки документа — посторонние ключи в data не пишем.
        $fields = $document->editableFields()->keyBy('key');
        $input  = collect($request->input('data', []))->only($fields->keys());

        $missing = $fields->filter(fn ($f) => $f['required'] && $input->has($f['key']) && blank($input[$f['key']]));
        if ($missing->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(
                $missing->mapWithKeys(fn ($f) => ["data.{$f['key']}" => "Поле «{$f['label']}» обязательно."])->all()
            );
        }

        $old = $document->toArray();
        $document->update([
            'title' => $request->filled('title') ? $request->title : $document->title,
            'data'  => array_merge($document->data ?? [], $input->map(fn ($v) => $v ?? '')->all()),
        ]);

        // Название — проекция полей: после правки пересобираем его по маске типа.
        if ($name = $namingService->forDocument($document)) {
            $document->update(['title' => $name]);
        }

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
