<?php

namespace App\Http\Controllers;

use App\Models\Procedure;
use App\Models\ProcedureFile;
use App\Models\ProcedureTask;
use App\Models\ProcedureTemplate;
use App\Models\User;
use App\Services\ProcedureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProcedureController extends Controller
{
    public function __construct(private ProcedureService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tab  = $request->query('tab', 'mine');
        $canViewAll = $user->isAdmin() || $user->hasMatrixPermission('procedures.view_all');

        // Задачи по процедурам — отдельная вкладка на этой же странице.
        $myTasks = $reviewTasks = collect();
        if ($tab === 'tasks') {
            $myTasks = ProcedureTask::query()
                ->with('procedure', 'assignee')
                ->where(function ($q) use ($user) {
                    $q->where('assignee_id', $user->id);
                    if ($user->isAdmin()) {
                        $q->orWhereNull('assignee_id');
                    }
                })
                ->orderByRaw("FIELD(status,'returned','pending','in_progress','submitted','done')")
                ->latest('id')
                ->get();

            $reviewTasks = ProcedureTask::query()
                ->with('procedure', 'assignee', 'files')
                ->where('status', 'submitted')
                ->whereHas('procedure', fn ($q) => $q->where('initiator_id', $user->id))
                ->latest('submitted_at')
                ->get();
        }

        $query = Procedure::query()->with('template', 'initiator')->latest('id');

        if ($tab === 'all' && $canViewAll) {
            // все процедуры
        } elseif ($tab === 'inbox') {
            $query->whereHas('runs', fn ($q) => $q->where('status', 'active')->where('executor_id', $user->id));
        } elseif ($tab !== 'tasks') {
            $tab = 'mine';
            $query->where('initiator_id', $user->id);
        }

        return view('procedures.index', [
            'procedures'  => $query->paginate(20)->withQueryString(),
            'tab'         => $tab,
            'canViewAll'  => $canViewAll,
            'canStart'    => $this->canStart($user),
            'inboxCount'  => Procedure::whereHas('runs', fn ($q) => $q->where('status', 'active')->where('executor_id', $user->id))->count(),
            'taskCount'   => ProcedureTask::where('assignee_id', $user->id)->whereIn('status', ['pending', 'returned'])->count()
                + ProcedureTask::where('status', 'submitted')->whereHas('procedure', fn ($q) => $q->where('initiator_id', $user->id))->count(),
            'myTasks'     => $myTasks,
            'reviewTasks' => $reviewTasks,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($this->canStart($request->user()), 403);

        $selected = $request->query('template')
            ? ProcedureTemplate::with('stages')->where('is_active', true)->find($request->query('template'))
            : null;

        return view('procedures.create', [
            'templates' => ProcedureTemplate::where('is_active', true)->orderBy('name')->get(),
            'selected'  => $selected,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->canStart($request->user()), 403);

        $template = ProcedureTemplate::with('stages')->where('is_active', true)->findOrFail($request->integer('template_id'));
        $first    = $template->stages->first();

        $rules = [
            'template_id' => ['required', 'exists:procedure_templates,id'],
            'title'       => ['required', 'string', 'max:255'],
            'form_text'   => ['nullable', 'string', 'max:10000'],
            'files'       => ['nullable', 'array'],
            'files.*'     => ['file', 'max:51200'],
        ];
        if ($first && $first->type === 'form' && $first->require_attachments) {
            $rules['files']   = ['required', 'array', 'min:1'];
            $rules['files.*'] = ['file', 'max:51200'];
        }
        $data = $request->validate($rules);

        $procedure = $this->service->start(
            $template,
            $request->user(),
            $data['title'],
            $data['form_text'] ?? null,
            $request->file('files', []),
        );

        return redirect()->route('procedures.show', $procedure)
            ->with('success', 'Процедура запущена: ' . $procedure->number);
    }

    public function show(Request $request, Procedure $procedure)
    {
        abort_unless($procedure->visibleTo($request->user()), 403);

        $procedure->load([
            'template.checklistItems.executorUser',
            'runs.executor', 'runs.files.uploader',
            'files.uploader', 'checklistEntries.executor',
            'tasks.assignee', 'events.user',
        ]);

        $active = $procedure->activeRun();

        return view('procedures.show', [
            'procedure' => $procedure,
            'active'    => $active,
            'canAct'    => $active && $active->canAct($request->user()),
            'users'     => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function submitStage(Request $request, Procedure $procedure)
    {
        $run = $procedure->activeRun();
        abort_unless($run && $run->canAct($request->user()), 403);
        abort_unless(in_array($run->type, ['form', 'approval'], true), 422);

        $rules = [
            'comment'  => ['nullable', 'string', 'max:5000'],
            'files'    => ['nullable', 'array'],
            'files.*'  => ['file', 'max:51200'],
        ];
        if ($run->stage?->require_attachments) {
            $rules['files']   = ['required', 'array', 'min:1'];
            $rules['files.*'] = ['file', 'max:51200'];
        }
        $request->validate($rules);

        $this->service->submitStage($run, $request->user(), $request->input('comment'), $request->file('files', []));

        return back()->with('success', 'Этап пройден.');
    }

    public function branch(Request $request, Procedure $procedure)
    {
        $run = $procedure->activeRun();
        abort_unless($run && $run->canAct($request->user()), 403);
        abort_unless($run->type === 'branch', 422);

        $data = $request->validate([
            'verdict'  => ['required', 'in:positive,negative'],
            'comment'  => [$request->input('verdict') === 'negative' ? 'required' : 'nullable', 'string', 'max:2000'],
            'files'    => ['nullable', 'array'],
            'files.*'  => ['file', 'max:51200'],
        ]);

        $this->service->branch($run, $request->user(), $data['verdict'], $data['comment'] ?? null, $request->file('files', []));

        return back()->with('success', $data['verdict'] === 'negative' ? 'Процедура остановлена.' : 'Развилка пройдена.');
    }

    public function submitChecklist(Request $request, Procedure $procedure)
    {
        $run = $procedure->activeRun();
        abort_unless($run && $run->canAct($request->user()), 403);
        abort_unless($run->type === 'checklist', 422);

        $this->service->submitChecklist(
            $run,
            $request->user(),
            $request->input('presets', []),
            array_values($request->input('custom', [])),
        );

        return back()->with('success', 'Чек-лист заполнен, задачи распределены.');
    }

    public function file(Request $request, ProcedureFile $file)
    {
        abort_unless($file->procedure->visibleTo($request->user()), 403);
        abort_unless(Storage::exists($file->path), 404);

        return Storage::download($file->path, $file->original_name);
    }

    public function destroy(Request $request, Procedure $procedure)
    {
        abort_unless($procedure->initiator_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $procedure->delete();

        return redirect()->route('procedures.index')->with('success', 'Процедура удалена.');
    }

    private function canStart(User $user): bool
    {
        return $user->isAdmin() || $user->hasMatrixPermission('procedures.start');
    }
}
