<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\Inspection;
use App\Models\InspectionEvent;
use App\Models\InspectionFile;
use App\Models\User;
use App\Services\AssignmentNumberService;
use App\Services\AuditService;
use App\Services\InspectionNumberService;
use App\Services\InspectionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Mews\Purifier\Facades\Purifier;

class InspectionController extends Controller
{
    public function __construct(
        private InspectionService $service,
        private InspectionNumberService $numberService,
        private NotificationService $notifications,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $user   = auth()->user();
        $canAll = $user->isAdmin() || $user->hasMatrixPermission('inspections.view_all');

        $tab = $request->get('tab', $this->service->canInitiate($user) ? 'outgoing' : 'incoming');
        if ($tab === 'all' && ! $canAll) {
            $tab = 'incoming';
        }

        $query = Inspection::with(['initiator', 'executor', 'parent'])->latest('id');

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

        $incomingPending = Inspection::where('executor_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress', 'returned'])->count();

        return view('inspections.index', [
            'inspections'     => $query->paginate(20)->withQueryString(),
            'tab'             => $tab,
            'canAll'          => $canAll,
            'canInitiate'     => $this->service->canInitiate($user),
            'incomingPending' => $incomingPending,
            'statuses'        => Inspection::STATUSES,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        abort_unless($this->service->canInitiate($user), 403, 'Нет права инициировать проверки.');

        return view('inspections.create', [
            'executors'   => $this->service->assignableExecutors($user),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'employees'   => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'objectTypes' => Inspection::OBJECT_TYPES,
            'kinds'       => Inspection::KINDS,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($this->service->canInitiate($user), 403);

        $allowed = $this->service->assignableExecutors($user)->pluck('id')->all();

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'body_html'   => ['nullable', 'string'],
            'executor_id' => ['required', Rule::in($allowed)],
            'due_at'      => ['nullable', 'date'],
            'object_type' => ['nullable', Rule::in(array_keys(Inspection::OBJECT_TYPES))],
            'object_id'   => ['nullable', 'integer'],
            'period_from' => ['nullable', 'date'],
            'period_to'   => ['nullable', 'date', 'after_or_equal:period_from'],
            'kind'        => ['nullable', Rule::in(array_keys(Inspection::KINDS))],
        ]);

        $inspection = DB::transaction(function () use ($data, $user) {
            $i = Inspection::create([
                'title'        => $data['title'],
                'body_html'    => filled($data['body_html'] ?? null) ? Purifier::clean($data['body_html'], 'blank') : null,
                'initiator_id' => $user->id,
                'executor_id'  => $data['executor_id'],
                'due_at'       => $data['due_at'] ?? null,
                'object_type'  => $data['object_type'] ?? null,
                'object_id'    => $data['object_id'] ?? null,
                'object_label' => $this->resolveObjectLabel($data['object_type'] ?? null, $data['object_id'] ?? null),
                'period_from'  => $data['period_from'] ?? null,
                'period_to'    => $data['period_to'] ?? null,
                'kind'         => $data['kind'] ?? null,
                'depth'        => 0,
                'status'       => 'assigned',
            ]);
            $i->update(['root_id' => $i->id]);
            $this->numberService->assign($i);

            return $i;
        });

        $this->event($inspection, 'created');
        $this->audit->log('inspection_created', $inspection);
        $this->notifications->notify($inspection->executor, 'inspection_created', [
            'title' => $inspection->title, 'inspection_id' => $inspection->id,
        ]);

        return redirect()->route('inspections.show', $inspection)->with('success', "Проверка {$inspection->number} создана.");
    }

    public function show(Inspection $inspection)
    {
        $user = auth()->user();
        abort_unless($inspection->visibleTo($user), 403);

        $inspection->load([
            'initiator.department', 'executor.department', 'parent',
            'events.user', 'files.source',
            'children.executor', 'children.initiator',
        ]);

        $children = $inspection->children->filter(fn ($c) => $c->visibleTo($user))->values();
        $canSub   = $this->service->canSubRequest($user, $inspection);
        $isInitiator = $inspection->initiator_id === $user->id;

        return view('inspections.show', [
            'inspection'    => $inspection,
            'children'      => $children,
            'isExecutor'    => $inspection->executor_id === $user->id,
            'isInitiator'   => $isInitiator,
            'canSubRequest' => $canSub,
            'executors'     => ($canSub || ($isInitiator && $inspection->isDone())) ? $this->service->assignableExecutors($user) : collect(),
            'parentVisible' => $inspection->parent && $inspection->parent->visibleTo($user),
            'verdicts'      => Inspection::VERDICTS,
        ]);
    }

    /** Создать подпроверку-запрос данных под текущим узлом (ТЗ 20.2). */
    public function storeSub(Request $request, Inspection $inspection)
    {
        $user = auth()->user();
        abort_unless($this->service->canSubRequest($user, $inspection), 403, 'Запрос данных недоступен (нет прав или превышена глубина).');

        $allowed = $this->service->assignableExecutors($user)->pluck('id')->all();

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'body_html'    => ['nullable', 'string'],
            'executor_id'  => ['required', Rule::in($allowed)],
            'due_at'       => ['nullable', 'date'],
            'is_mandatory' => ['nullable', 'boolean'],
        ]);

        $child = DB::transaction(function () use ($data, $user, $inspection) {
            $c = Inspection::create([
                'parent_id'    => $inspection->id,
                'root_id'      => $inspection->root_id ?? $inspection->id,
                'depth'        => $inspection->depth + 1,
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

        $this->event($child, 'created');
        $this->event($inspection, 'subrequest_created', null, ['child_id' => $child->id, 'child_number' => $child->number]);
        $this->audit->log('inspection_subrequest_created', $child);
        $this->notifications->notify($child->executor, 'inspection_created', [
            'title' => $child->title, 'inspection_id' => $child->id,
        ]);

        return redirect()->route('inspections.show', $inspection)->with('success', "Запрос данных {$child->number} создан.");
    }

    public function start(Inspection $inspection)
    {
        abort_unless($inspection->isParticipant(auth()->user()), 403);
        abort_unless(in_array($inspection->status, ['assigned', 'returned'], true), 403);

        $inspection->update(['status' => 'in_progress', 'started_at' => $inspection->started_at ?? now()]);
        $this->event($inspection, 'started');

        return back()->with('success', 'Проверка взята в работу.');
    }

    public function contribute(Request $request, Inspection $inspection)
    {
        abort_unless($inspection->isParticipant(auth()->user()), 403);
        abort_unless(in_array($inspection->status, ['in_progress', 'returned'], true), 403);

        $request->validate(['files' => ['required', 'array'], 'files.*' => ['file', 'max:51200']]);

        foreach ($request->file('files', []) as $file) {
            $this->storeFile($inspection, $file);
        }
        $this->event($inspection, 'contributed');

        return back()->with('success', 'Материалы приложены.');
    }

    /** Исполнитель сдаёт итоговый акт: вердикт + перечень нарушений + файлы (ТЗ 20.2). */
    public function submit(Request $request, Inspection $inspection)
    {
        abort_unless($inspection->executor_id === auth()->id(), 403);
        abort_unless(in_array($inspection->status, ['in_progress', 'returned'], true), 403);

        if ($inspection->openMandatoryChildren()->exists()) {
            return back()->with('error', 'Сначала примите обязательные запросы данных — только потом можно сдать акт.');
        }

        $data = $request->validate([
            'act_verdict'    => ['required', Rule::in(array_keys(Inspection::VERDICTS))],
            'act_violations' => ['nullable', 'required_if:act_verdict,found', 'string', 'max:5000'],
            'result_comment' => ['nullable', 'string', 'max:5000'],
            'files'          => ['nullable', 'array'],
            'files.*'        => ['file', 'max:51200'],
        ]);

        DB::transaction(function () use ($request, $inspection, $data) {
            $inspection->update([
                'status'         => 'submitted',
                'submitted_at'   => now(),
                'act_verdict'    => $data['act_verdict'],
                'act_violations' => $data['act_violations'] ?? null,
                'result_comment' => $data['result_comment'] ?? null,
            ]);

            foreach ($request->file('files', []) as $file) {
                $this->storeFile($inspection, $file);
            }
        });

        $this->event($inspection, 'submitted', $data['result_comment'] ?? null, ['verdict' => $data['act_verdict']]);
        $this->audit->log('inspection_submitted', $inspection);
        $this->notifications->notify($inspection->initiator, 'inspection_submitted', [
            'title' => $inspection->title, 'inspection_id' => $inspection->id,
        ]);

        return back()->with('success', 'Акт отправлен на приёмку.');
    }

    /** Постановщик принимает акт: узел закрывается, файлы подтягиваются в родителя. */
    public function accept(Inspection $inspection)
    {
        abort_unless($inspection->initiator_id === auth()->id() && $inspection->status === 'submitted', 403);

        DB::transaction(function () use ($inspection) {
            $inspection->update(['status' => 'done', 'accepted_at' => now()]);
            $this->aggregateFilesUp($inspection);
        });

        $this->event($inspection, 'accepted');
        $this->audit->log('inspection_accepted', $inspection);
        $this->notifications->notify($inspection->executor, 'inspection_accepted', [
            'title' => $inspection->title, 'inspection_id' => $inspection->id,
        ]);

        return back()->with('success', 'Акт принят.');
    }

    public function returnToRework(Request $request, Inspection $inspection)
    {
        abort_unless($inspection->initiator_id === auth()->id() && $inspection->status === 'submitted', 403);

        $data = $request->validate(['return_comment' => ['required', 'string', 'max:2000']]);

        $inspection->update([
            'status'         => 'returned',
            'returned_at'    => now(),
            'return_comment' => $data['return_comment'],
        ]);

        $this->event($inspection, 'returned', $data['return_comment']);
        $this->audit->log('inspection_returned', $inspection);
        $this->notifications->notify($inspection->executor, 'inspection_returned', [
            'title' => $inspection->title, 'inspection_id' => $inspection->id, 'comment' => $data['return_comment'],
        ]);

        return back()->with('success', 'Проверка возвращена на доработку.');
    }

    /** Опционально: породить Поручение по итогам проверки — «устранить выявленное» (ТЗ 20.2). */
    public function spawnAssignment(Request $request, Inspection $inspection, AssignmentNumberService $assignmentNumbers)
    {
        $user = auth()->user();
        abort_unless($inspection->initiator_id === $user->id || $user->isAdmin(), 403);
        abort_unless($inspection->isDone(), 422, 'Поручение порождается только по принятой проверке.');
        abort_unless($user->hasMatrixPermission('assignments.issue') || $user->isAdmin(), 403, 'Нет права ставить поручения.');

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'executor_id' => ['required', 'exists:users,id'],
            'due_at'      => ['nullable', 'date'],
            'body'        => ['nullable', 'string', 'max:5000'],
        ]);

        $assignment = DB::transaction(function () use ($data, $user, $inspection, $assignmentNumbers) {
            $a = Assignment::create([
                'title'        => $data['title'],
                'body_html'    => filled($data['body'] ?? null) ? Purifier::clean('<p>' . e($data['body']) . '</p>', 'blank') : null,
                'initiator_id' => $user->id,
                'executor_id'  => $data['executor_id'],
                'due_at'       => $data['due_at'] ?? null,
                'depth'        => 0,
                'status'       => 'assigned',
            ]);
            $a->update(['root_id' => $a->id]);
            $assignmentNumbers->assign($a);

            return $a;
        });

        $this->event($inspection, 'assignment_spawned', null, ['assignment_id' => $assignment->id, 'assignment_number' => $assignment->number]);
        $this->audit->log('inspection_assignment_spawned', $assignment);
        $this->notifications->notify($assignment->executor, 'assignment_created', [
            'title' => $assignment->title, 'assignment_id' => $assignment->id,
        ]);

        return redirect()->route('assignments.show', $assignment)
            ->with('success', "По итогам проверки поставлено поручение {$assignment->number}.");
    }

    public function file(InspectionFile $file)
    {
        abort_unless($file->inspection->visibleTo(auth()->user()), 403);
        abort_unless(Storage::exists($file->path), 404);

        return Storage::download($file->path, $file->original_name);
    }

    public function destroy(Inspection $inspection)
    {
        $user = auth()->user();
        abort_unless($inspection->initiator_id === $user->id || $user->isAdmin(), 403);
        abort_unless($inspection->children()->doesntExist() && $inspection->status === 'assigned', 422);

        $this->audit->log('inspection_deleted', $inspection);
        $inspection->delete();

        return redirect()->route('inspections.index')->with('success', 'Проверка удалена.');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function resolveObjectLabel(?string $type, ?int $id): ?string
    {
        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'department', 'direction' => Department::find($id)?->name,
            'employee'                => User::find($id)?->name,
            default                   => null,
        };
    }

    private function storeFile(Inspection $inspection, $file): void
    {
        InspectionFile::create([
            'inspection_id' => $inspection->id,
            'uploaded_by'   => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $file->store("inspections/{$inspection->id}"),
            'size'          => $file->getSize(),
            'mime'          => $file->getClientMimeType(),
        ]);
    }

    /** При приёмке подпроверки её файлы-результаты подтягиваются в родителя (ТЗ 20.2). */
    private function aggregateFilesUp(Inspection $node): void
    {
        if (! $node->parent_id) {
            return;
        }

        InspectionFile::where('inspection_id', $node->parent_id)
            ->where('source_inspection_id', $node->id)->delete();

        foreach ($node->files()->get() as $f) {
            InspectionFile::create([
                'inspection_id'        => $node->parent_id,
                'uploaded_by'          => $f->uploaded_by,
                'source_inspection_id' => $node->id,
                'original_name'        => $f->original_name,
                'path'                 => $f->path,
                'size'                 => $f->size,
                'mime'                 => $f->mime,
            ]);
        }
    }

    private function event(Inspection $inspection, string $type, ?string $comment = null, ?array $meta = null): void
    {
        InspectionEvent::create([
            'inspection_id' => $inspection->id,
            'user_id'       => auth()->id(),
            'type'          => $type,
            'comment'       => $comment,
            'meta'          => $meta,
        ]);
    }
}
