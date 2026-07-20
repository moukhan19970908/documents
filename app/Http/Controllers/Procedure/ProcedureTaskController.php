<?php

namespace App\Http\Controllers\Procedure;

use App\Http\Controllers\Controller;
use App\Models\ProcedureTask;
use App\Models\ProcedureTaskFile;
use App\Services\NotificationService;
use App\Services\ProcedureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcedureTaskController extends Controller
{
    public function __construct(
        private ProcedureService $service,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

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

        // Задачи, сданные на приёмку инициатору (текущему пользователю).
        $reviewTasks = ProcedureTask::query()
            ->with('procedure', 'assignee', 'files')
            ->where('status', 'submitted')
            ->whereHas('procedure', fn ($q) => $q->where('initiator_id', $user->id))
            ->latest('submitted_at')
            ->get();

        return view('procedures.tasks.index', compact('myTasks', 'reviewTasks'));
    }

    public function take(Request $request, ProcedureTask $task)
    {
        abort_unless($task->canAct($request->user()) && $task->status === 'pending', 403);

        $task->update(['status' => 'in_progress']);

        return back()->with('success', 'Задача взята в работу.');
    }

    /** Исполнитель сдаёт задачу на приёмку инициатору (не завершает сам). */
    public function submit(Request $request, ProcedureTask $task)
    {
        abort_unless($task->canAct($request->user()) && in_array($task->status, ['pending', 'in_progress', 'returned'], true), 403);

        $request->validate([
            'result_comment' => ['nullable', 'string', 'max:5000'],
            'files'          => ['nullable', 'array'],
            'files.*'        => ['file', 'max:51200'],
        ]);

        DB::transaction(function () use ($request, $task) {
            foreach ($request->file('files', []) as $file) {
                $task->files()->create([
                    'uploaded_by'   => $request->user()->id,
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $file->store("procedure-tasks/{$task->id}"),
                    'size'          => $file->getSize(),
                    'mime'          => $file->getClientMimeType(),
                ]);
            }

            $task->update([
                'status'         => 'submitted',
                'result_comment' => $request->input('result_comment'),
                'submitted_at'   => now(),
            ]);

            $procedure = $task->procedure;
            $procedure->events()->create([
                'user_id' => $request->user()->id,
                'type'    => 'task_submitted',
                'meta'    => ['task' => $task->title],
            ]);
            $this->notifications->notify($procedure->initiator, 'procedure_task_submitted', [
                'title'        => $procedure->title,
                'task'         => $task->title,
                'procedure_id' => $procedure->id,
            ]);
        });

        return back()->with('success', 'Задача сдана на приёмку инициатору.');
    }

    /** Инициатор принимает задачу. Если приняты все — процедура завершается. */
    public function accept(Request $request, ProcedureTask $task)
    {
        abort_unless($task->canReview($request->user()) && $task->status === 'submitted', 403);

        DB::transaction(function () use ($request, $task) {
            $task->update([
                'status'  => 'done',
                'done_by' => $request->user()->id,
                'done_at' => now(),
            ]);

            $procedure = $task->procedure;
            $procedure->events()->create([
                'user_id' => $request->user()->id,
                'type'    => 'task_accepted',
                'meta'    => ['task' => $task->title],
            ]);
            if ($task->assignee) {
                $this->notifications->notify($task->assignee, 'procedure_task_accepted', [
                    'title'        => $procedure->title,
                    'task'         => $task->title,
                    'procedure_id' => $procedure->id,
                ]);
            }

            // Возможно, это была последняя задача — проверим завершение процедуры.
            $this->service->checkCompletion($procedure->fresh());
        });

        return back()->with('success', 'Задача принята.');
    }

    /** Инициатор возвращает задачу исполнителю на доработку (обязательная причина). */
    public function returnTask(Request $request, ProcedureTask $task)
    {
        abort_unless($task->canReview($request->user()) && $task->status === 'submitted', 403);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $task->update(['status' => 'returned', 'return_comment' => $data['comment']]);

        $procedure = $task->procedure;
        $procedure->events()->create([
            'user_id' => $request->user()->id,
            'type'    => 'task_returned',
            'comment' => $data['comment'],
            'meta'    => ['task' => $task->title],
        ]);
        if ($task->assignee) {
            $this->notifications->notify($task->assignee, 'procedure_task_returned', [
                'title'        => $procedure->title,
                'task'         => $task->title,
                'comment'      => $data['comment'],
                'procedure_id' => $procedure->id,
            ]);
        }

        return back()->with('success', 'Задача возвращена исполнителю на доработку.');
    }

    /** Перенос срока с ОБЯЗАТЕЛЬНЫМ комментарием и уведомлением инициатора (ТЗ 19.3). */
    public function changeDeadline(Request $request, ProcedureTask $task)
    {
        abort_unless($task->canAct($request->user()) && $task->status !== 'done', 403);

        $data = $request->validate([
            'due_at'  => ['required', 'date'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $from = $task->due_at?->format('d.m.Y');
        $task->update(['due_at' => $data['due_at']]);
        $to = $task->due_at->format('d.m.Y');

        $procedure = $task->procedure;
        $procedure->events()->create([
            'user_id' => $request->user()->id,
            'type'    => 'task_deadline_changed',
            'comment' => $data['comment'],
            'meta'    => ['task' => $task->title, 'from' => $from, 'to' => $to],
        ]);
        $this->notifications->notify($procedure->initiator, 'procedure_task_deadline', [
            'title'        => $procedure->title,
            'task'         => $task->title,
            'from'         => $from,
            'to'           => $to,
            'comment'      => $data['comment'],
            'procedure_id' => $procedure->id,
        ]);

        return back()->with('success', 'Срок перенесён, инициатор уведомлён.');
    }

    public function file(Request $request, ProcedureTaskFile $file)
    {
        $task = $file->task;
        $user = $request->user();
        $allowed = $task->assignee_id === $user->id
            || $task->procedure->initiator_id === $user->id
            || $user->isAdmin();
        abort_unless($allowed, 403);
        abort_unless(Storage::exists($file->path), 404);

        return Storage::download($file->path, $file->original_name);
    }
}
