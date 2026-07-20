<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\TripTask;
use App\Models\TripTaskFile;
use App\Services\TripTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TripTaskController extends Controller
{
    public function __construct(private TripTaskService $service) {}

    /** «Задания» — задания командировок, назначенные мне (для админа — и неназначенные). */
    public function index()
    {
        $user = auth()->user();

        $tasks = TripTask::with(['trip.user', 'files', 'assignee'])
            ->where(function ($q) use ($user) {
                $q->where('assignee_id', $user->id);
                if ($user->isAdmin()) {
                    $q->orWhereNull('assignee_id');
                }
            })
            ->orderByRaw("FIELD(status, 'pending', 'in_progress', 'done')")
            ->latest('id')
            ->get();

        return view('trips.tasks.index', compact('tasks'));
    }

    public function take(TripTask $task)
    {
        abort_unless($this->canAct($task), 403);
        abort_unless($task->status === 'pending', 403);

        $this->service->take($task);

        return back()->with('success', 'Задание взято в работу.');
    }

    public function complete(Request $request, TripTask $task)
    {
        abort_unless($this->canAct($task), 403);
        abort_unless($task->status !== 'done', 403);

        $data = $request->validate([
            'result_comment' => ['nullable', 'string', 'max:5000'],
            'files'          => ['nullable', 'array'],
            'files.*'        => ['file', 'max:51200'],
        ]);

        $this->service->complete($task, auth()->user(), $data['result_comment'] ?? null, $request->file('files', []));

        return back()->with('success', 'Задание выполнено, инициатор уведомлён.');
    }

    public function file(TripTaskFile $file)
    {
        $task = $file->task;
        $uid  = auth()->id();
        $allowed = $task->assignee_id === $uid
            || $task->trip->user_id === $uid
            || auth()->user()->isAdmin();

        abort_unless($allowed, 403);
        abort_unless(Storage::exists($file->path), 404);

        return Storage::download($file->path, $file->original_name);
    }

    private function canAct(TripTask $task): bool
    {
        return $task->assignee_id === auth()->id() || auth()->user()->isAdmin();
    }
}
