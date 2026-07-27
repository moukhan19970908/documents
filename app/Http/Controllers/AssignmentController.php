<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentEvent;
use App\Models\AssignmentFile;
use App\Models\AssignmentSetting;
use App\Models\User;
use App\Services\ArchiveService;
use App\Services\AssignmentNumberService;
use App\Services\AssignmentService;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Mews\Purifier\Facades\Purifier;

class AssignmentController extends Controller
{
    public function __construct(
        private AssignmentService $service,
        private AssignmentNumberService $numberService,
        private NotificationService $notifications,
        private AuditService $audit,
        private ArchiveService $archive,
    ) {}

    public function index(Request $request)
    {
        $user   = auth()->user();
        $canAll = $user->isAdmin() || $user->hasMatrixPermission('assignments.view_all');

        $tab = $request->get('tab', $this->service->canInitiate($user) ? 'outgoing' : 'incoming');
        if ($tab === 'all' && ! $canAll) {
            $tab = 'incoming';
        }

        $query = Assignment::with(['initiator', 'executor', 'parent'])->latest('id');

        match ($tab) {
            'incoming' => $query->where('executor_id', $user->id),
            'outgoing' => $query->where('initiator_id', $user->id),
            'all'      => $query,
        };

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%"));
        }

        $assignments = $query->paginate(20)->withQueryString();

        // Бейдж: сколько поручений ждут моих действий как исполнителя.
        $incomingPending = Assignment::where('executor_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress', 'returned'])->count();

        return view('assignments.index', [
            'assignments'     => $assignments,
            'tab'             => $tab,
            'canAll'          => $canAll,
            'canInitiate'     => $this->service->canInitiate($user),
            'incomingPending' => $incomingPending,
            'statuses'        => Assignment::STATUSES,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        abort_unless($this->service->canInitiate($user), 403, 'Нет права ставить поручения.');

        $settings = AssignmentSetting::current();

        return view('assignments.create', [
            'executors'    => $this->service->assignableExecutors($user, false),
            'blankContent' => $settings->blank_template_id ? optional($settings->blank)->content : null,
            'settings'     => $settings,
            'people'       => $this->controllerCandidates(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($this->service->canInitiate($user), 403);

        $allowed = $this->service->assignableExecutors($user, false)->pluck('id')->all();

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'body_html'      => ['nullable', 'string'],
            'executor_id'    => ['required', Rule::in($allowed)],
            'due_at'         => ['nullable', 'date'],
            'co_executors'   => ['nullable', 'array'],
            'co_executors.*' => ['integer'],
            'controller_id'  => ['nullable', 'integer'],
        ]);

        $assignment = DB::transaction(function () use ($data, $user) {
            $a = Assignment::create([
                'title'        => $data['title'],
                'body_html'    => filled($data['body_html'] ?? null) ? Purifier::clean($data['body_html'], 'blank') : null,
                'initiator_id' => $user->id,
                'executor_id'  => $data['executor_id'],
                'due_at'       => $data['due_at'] ?? null,
                'depth'        => 0,
                'status'       => 'assigned',
            ]);
            $a->update(['root_id' => $a->id]);
            $this->numberService->assign($a);

            return $a;
        });

        $this->attachParticipants($assignment, $request, $allowed);
        $this->event($assignment, 'created');
        $this->audit->log('assignment_created', $assignment);
        $this->notifications->notify($assignment->executor, 'assignment_created', [
            'title' => $assignment->title, 'assignment_id' => $assignment->id,
        ]);

        return redirect()->route('assignments.show', $assignment)->with('success', "Поручение {$assignment->number} поставлено.");
    }

    public function show(Assignment $assignment)
    {
        $user = auth()->user();
        abort_unless($assignment->visibleTo($user), 403);

        $assignment->load([
            'initiator.department', 'executor.department', 'parent',
            'controller', 'coExecutors',
            'events.user', 'files.source',
            'children.executor', 'children.initiator',
        ]);

        // Послойная видимость применяется и к детям.
        $children = $assignment->children->filter(fn ($c) => $c->visibleTo($user))->values();

        $canSub = $this->service->canSubAssign($user, $assignment);

        return view('assignments.show', [
            'assignment'    => $assignment,
            'children'      => $children,
            'isExecutor'    => $assignment->executor_id === $user->id,
            'isInitiator'   => $assignment->initiator_id === $user->id,
            'isParticipant' => $assignment->isParticipant($user),
            'canSubAssign'  => $canSub,
            'executors'     => $canSub ? $this->service->assignableExecutors($user, true) : collect(),
            'people'        => $canSub ? $this->controllerCandidates() : collect(),
            'parentVisible' => $assignment->parent && $assignment->parent->visibleTo($user),
            'settings'      => AssignmentSetting::current(),
        ]);
    }

    /** Создать подпоручение под текущим узлом. */
    public function storeSub(Request $request, Assignment $assignment)
    {
        $user = auth()->user();
        abort_unless($this->service->canSubAssign($user, $assignment), 403, 'Подпоручение недоступно (нет прав или превышена глубина).');

        $allowed = $this->service->assignableExecutors($user, true)->pluck('id')->all();

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'body_html'      => ['nullable', 'string'],
            'executor_id'    => ['required', Rule::in($allowed)],
            'due_at'         => ['nullable', 'date'],
            'is_mandatory'   => ['nullable', 'boolean'],
            'co_executors'   => ['nullable', 'array'],
            'co_executors.*' => ['integer'],
            'controller_id'  => ['nullable', 'integer'],
        ]);

        $child = DB::transaction(function () use ($data, $user, $assignment) {
            $c = Assignment::create([
                'parent_id'    => $assignment->id,
                'root_id'      => $assignment->root_id ?? $assignment->id,
                'depth'        => $assignment->depth + 1,
                'title'        => $data['title'],
                'body_html'    => filled($data['body_html'] ?? null) ? Purifier::clean($data['body_html'], 'blank') : null,
                'initiator_id' => $user->id,
                'executor_id'  => $data['executor_id'],
                'due_at'       => $data['due_at'] ?? null,
                'is_mandatory' => (bool) ($data['is_mandatory'] ?? true),
                'status'       => 'assigned',
            ]);
            $this->numberService->assign($c);

            return $c;
        });

        $this->attachParticipants($child, $request, $allowed);
        $this->event($child, 'created');
        $this->event($assignment, 'subassignment_created', null, ['child_id' => $child->id, 'child_number' => $child->number]);
        $this->audit->log('assignment_subassignment_created', $child);
        $this->notifications->notify($child->executor, 'assignment_created', [
            'title' => $child->title, 'assignment_id' => $child->id,
        ]);

        return redirect()->route('assignments.show', $assignment)->with('success', "Подпоручение {$child->number} поставлено.");
    }

    /** Исполнитель или соисполнитель берёт узел в работу. */
    public function start(Assignment $assignment)
    {
        abort_unless($assignment->isParticipant(auth()->user()), 403);
        abort_unless(in_array($assignment->status, ['assigned', 'returned'], true), 403);

        $assignment->update(['status' => 'in_progress', 'started_at' => $assignment->started_at ?? now()]);
        $this->event($assignment, 'started');

        return back()->with('success', 'Поручение взято в работу.');
    }

    /** Соисполнитель/исполнитель прикладывает материалы к узлу (без отчёта наверх). */
    public function contribute(Request $request, Assignment $assignment)
    {
        abort_unless($assignment->isParticipant(auth()->user()), 403);
        abort_unless(in_array($assignment->status, ['in_progress', 'returned'], true), 403);

        $request->validate(['files' => ['required', 'array'], 'files.*' => ['file', 'max:51200']]);

        foreach ($request->file('files', []) as $file) {
            $this->storeFile($assignment, $file);
        }
        $this->event($assignment, 'contributed');

        return back()->with('success', 'Материалы приложены к поручению.');
    }

    /** Исполнитель отчитывается наверх (на приёмку). */
    public function submit(Request $request, Assignment $assignment)
    {
        abort_unless($assignment->executor_id === auth()->id(), 403);
        abort_unless(in_array($assignment->status, ['in_progress', 'returned'], true), 403);

        // Приёмка на каждом уровне: нельзя отчитаться, пока не закрыты обязательные подпоручения.
        if ($assignment->openMandatoryChildren()->exists()) {
            return back()->with('error', 'Сначала примите обязательные подпоручения — только потом можно отчитаться.');
        }

        $data = $request->validate([
            'result_comment' => ['nullable', 'string', 'max:5000'],
            'files'          => ['nullable', 'array'],
            'files.*'        => ['file', 'max:51200'],
        ]);

        DB::transaction(function () use ($request, $assignment, $data) {
            $assignment->update([
                'status'         => 'submitted',
                'submitted_at'   => now(),
                'result_comment' => $data['result_comment'] ?? null,
            ]);

            foreach ($request->file('files', []) as $file) {
                $this->storeFile($assignment, $file);
            }
        });

        $this->event($assignment, 'submitted', $data['result_comment'] ?? null);
        $this->audit->log('assignment_submitted', $assignment);
        $this->notifications->notify($assignment->initiator, 'assignment_submitted', [
            'title' => $assignment->title, 'assignment_id' => $assignment->id,
        ]);
        if ($assignment->controller) {
            $this->notifications->notify($assignment->controller, 'assignment_submitted', [
                'title' => $assignment->title, 'assignment_id' => $assignment->id,
            ]);
        }

        return back()->with('success', 'Отчёт отправлен на приёмку.');
    }

    /** Постановщик принимает результат: узел закрывается, файлы подтягиваются в родителя. */
    public function accept(Assignment $assignment)
    {
        abort_unless($assignment->initiator_id === auth()->id() && $assignment->status === 'submitted', 403);

        DB::transaction(function () use ($assignment) {
            $assignment->update(['status' => 'done', 'accepted_at' => now()]);
            if (AssignmentSetting::current()->aggregate_up) {
                $this->aggregateFilesUp($assignment);
            }
        });

        $this->event($assignment, 'accepted');
        $this->audit->log('assignment_accepted', $assignment);

        // Корневое поручение принято — дело закрыто, кладём его в архив
        // (подпоручения и файлы дела попадают снимком в метаданные).
        if ($assignment->isRoot()) {
            try {
                $this->archive->archiveAssignment($assignment);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Archive: поручение {$assignment->id} не заархивировано: {$e->getMessage()}");
            }
        }

        $this->notifications->notify($assignment->executor, 'assignment_accepted', [
            'title' => $assignment->title, 'assignment_id' => $assignment->id,
        ]);
        if ($assignment->controller) {
            $this->notifications->notify($assignment->controller, 'assignment_accepted', [
                'title' => $assignment->title, 'assignment_id' => $assignment->id,
            ]);
        }

        return back()->with('success', 'Поручение принято.');
    }

    /** Постановщик возвращает на доработку с обязательным комментарием. */
    public function returnToRework(Request $request, Assignment $assignment)
    {
        abort_unless($assignment->initiator_id === auth()->id() && $assignment->status === 'submitted', 403);

        $data = $request->validate(['return_comment' => ['required', 'string', 'max:2000']]);

        $assignment->update([
            'status'         => 'returned',
            'returned_at'    => now(),
            'return_comment' => $data['return_comment'],
        ]);

        $this->event($assignment, 'returned', $data['return_comment']);
        $this->audit->log('assignment_returned', $assignment);
        $this->notifications->notify($assignment->executor, 'assignment_returned', [
            'title' => $assignment->title, 'assignment_id' => $assignment->id, 'comment' => $data['return_comment'],
        ]);

        return back()->with('success', 'Поручение возвращено на доработку.');
    }

    /**
     * Перенос срока исполнителем — обязательный комментарий.
     * Режим из настроек: сразу (free) либо через одобрение постановщика (approval).
     */
    public function extendDeadline(Request $request, Assignment $assignment)
    {
        abort_unless($assignment->executor_id === auth()->id(), 403);
        $mode = AssignmentSetting::current()->deadline_extension;
        abort_if($mode === 'disabled', 403, 'Перенос срока запрещён настройками.');

        $data = $request->validate([
            'due_at'  => ['required', 'date'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        // С одобрением — фиксируем заявку и ждём постановщика.
        if ($mode === 'approval') {
            $assignment->update([
                'pending_due_at'      => $data['due_at'],
                'pending_due_comment' => $data['comment'],
            ]);
            $this->event($assignment, 'deadline_requested', $data['comment'], ['to' => $assignment->pending_due_at->format('d.m.Y')]);
            $this->notifications->notify($assignment->initiator, 'assignment_deadline_requested', [
                'title' => $assignment->title, 'assignment_id' => $assignment->id, 'comment' => $data['comment'],
            ]);

            return back()->with('success', 'Запрос на перенос срока отправлен постановщику.');
        }

        $this->applyNewDeadline($assignment, $data['due_at'], $data['comment']);

        return back()->with('success', 'Срок перенесён, постановщик уведомлён.');
    }

    /** Постановщик одобряет заявку на перенос срока. */
    public function approveExtension(Assignment $assignment)
    {
        abort_unless($assignment->initiator_id === auth()->id() && $assignment->pending_due_at, 403);

        $comment = $assignment->pending_due_comment;
        $due     = $assignment->pending_due_at->format('Y-m-d');
        $assignment->update(['pending_due_at' => null, 'pending_due_comment' => null]);
        $this->applyNewDeadline($assignment, $due, $comment, notifyInitiator: false);
        $this->notifications->notify($assignment->executor, 'assignment_deadline_changed', [
            'title' => $assignment->title, 'assignment_id' => $assignment->id,
            'comment' => $comment, 'from' => '', 'to' => $assignment->due_at->format('d.m.Y'),
        ]);

        return back()->with('success', 'Перенос срока одобрен.');
    }

    /** Постановщик отклоняет заявку на перенос срока. */
    public function rejectExtension(Assignment $assignment)
    {
        abort_unless($assignment->initiator_id === auth()->id() && $assignment->pending_due_at, 403);

        $assignment->update(['pending_due_at' => null, 'pending_due_comment' => null]);
        $this->event($assignment, 'deadline_rejected');
        $this->notifications->notify($assignment->executor, 'assignment_deadline_rejected', [
            'title' => $assignment->title, 'assignment_id' => $assignment->id,
        ]);

        return back()->with('success', 'Заявка на перенос срока отклонена.');
    }

    private function applyNewDeadline(Assignment $assignment, string $due, ?string $comment, bool $notifyInitiator = true): void
    {
        $old = $assignment->due_at?->format('d.m.Y') ?? '—';
        $assignment->update(['due_at' => $due]);
        $new = $assignment->due_at->format('d.m.Y');

        $this->event($assignment, 'deadline_changed', $comment, ['from' => $old, 'to' => $new]);
        $this->audit->log('assignment_deadline_changed', $assignment);

        if ($notifyInitiator) {
            $this->notifications->notify($assignment->initiator, 'assignment_deadline_changed', [
                'title' => $assignment->title, 'assignment_id' => $assignment->id,
                'comment' => $comment, 'from' => $old, 'to' => $new,
            ]);
        }
    }

    public function file(AssignmentFile $file)
    {
        abort_unless($file->assignment->visibleTo(auth()->user()), 403);
        abort_unless(Storage::exists($file->path), 404);

        return Storage::download($file->path, $file->original_name);
    }

    public function destroy(Assignment $assignment)
    {
        $user = auth()->user();
        abort_unless(($assignment->initiator_id === $user->id || $user->isAdmin()), 403);
        abort_unless($assignment->children()->doesntExist() && $assignment->status === 'assigned', 422);

        $this->audit->log('assignment_deleted', $assignment);
        $assignment->delete();

        return redirect()->route('assignments.index')->with('success', 'Поручение удалено.');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** Назначить соисполнителей и контролёра при постановке (если включены настройками). */
    private function attachParticipants(Assignment $assignment, Request $request, array $execPool): void
    {
        $settings = AssignmentSetting::current();

        if ($settings->coexecutors_enabled) {
            $co = collect($request->input('co_executors', []))
                ->map('intval')
                ->filter(fn ($id) => in_array($id, $execPool, true) && $id !== $assignment->executor_id)
                ->unique()->values();

            if ($co->isNotEmpty()) {
                $assignment->coExecutors()->sync($co->all());
                $this->notifications->notifyMany(
                    User::whereIn('id', $co)->get(),
                    'assignment_created',
                    ['title' => $assignment->title, 'assignment_id' => $assignment->id],
                );
            }
        }

        if ($settings->controller_enabled && $request->filled('controller_id')) {
            $cid = (int) $request->input('controller_id');
            if ($cid !== $assignment->executor_id && User::where('id', $cid)->where('is_active', true)->exists()) {
                $assignment->update(['controller_id' => $cid]);
                $this->notifications->notify(User::find($cid), 'assignment_control', [
                    'title' => $assignment->title, 'assignment_id' => $assignment->id,
                ]);
            }
        }
    }

    /** Кандидаты в контролёры — все активные сотрудники. */
    private function controllerCandidates()
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function storeFile(Assignment $assignment, $file): void
    {
        AssignmentFile::create([
            'assignment_id' => $assignment->id,
            'uploaded_by'   => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $file->store("assignments/{$assignment->id}"),
            'size'          => $file->getSize(),
            'mime'          => $file->getClientMimeType(),
        ]);
    }

    /** При приёмке дочернего узла его файлы-результаты подтягиваются в родительский (ТЗ 17.3). */
    private function aggregateFilesUp(Assignment $node): void
    {
        if (! $node->parent_id) {
            return;
        }

        AssignmentFile::where('assignment_id', $node->parent_id)
            ->where('source_assignment_id', $node->id)->delete();

        foreach ($node->files()->get() as $f) {
            AssignmentFile::create([
                'assignment_id'        => $node->parent_id,
                'uploaded_by'          => $f->uploaded_by,
                'source_assignment_id' => $node->id,
                'original_name'        => $f->original_name,
                'path'                 => $f->path,
                'size'                 => $f->size,
                'mime'                 => $f->mime,
            ]);
        }
    }

    private function event(Assignment $assignment, string $type, ?string $comment = null, ?array $meta = null): void
    {
        AssignmentEvent::create([
            'assignment_id' => $assignment->id,
            'user_id'       => auth()->id(),
            'type'          => $type,
            'comment'       => $comment,
            'meta'          => $meta,
        ]);
    }
}
