<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AccessControlController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function __invoke()
    {
        $users = User::with('department')->get()->map(fn(User $u) => [
            'id'                              => $u->id,
            'name'                            => $u->name,
            'email'                           => $u->email,
            'role'                            => $u->role_label ?? $u->role,
            'department'                      => $u->department?->name ?? '—',
            'is_active'                       => $u->is_active,
            'workflow_access_level'           => $u->workflow_access_level,
            'effective_access_level'          => $u->resolveWorkflowAccess(),
            'tasks_access_level'              => $u->tasks_access_level,
            'effective_tasks_access_level'    => $u->resolveTasksAccess(),
            'archive_access_level'            => $u->archive_access_level,
            'effective_archive_access_level'  => $u->resolveArchiveAccess(),
        ])->values()->toArray();

        $departments = Department::withCount('users')->get()->map(fn(Department $d) => [
            'id'                    => $d->id,
            'name'                  => $d->name,
            'member_count'          => $d->users_count,
            'workflow_access_level' => $d->workflow_access_level,
            'tasks_access_level'    => $d->tasks_access_level,
            'archive_access_level'  => $d->archive_access_level,
        ])->values()->toArray();

        $logs = AuditLog::with('user')
            ->where('action', 'workflow_access_updated')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn($log) => [
                'id'        => $log->id,
                'actor'     => $log->user?->name ?? 'Система',
                'target'    => $log->model_type === User::class
                    ? (User::find($log->model_id)?->name ?? "Пользователь #{$log->model_id}")
                    : (Department::find($log->model_id)?->name ?? "Департамент #{$log->model_id}"),
                'new_level' => $log->new_values['workflow_access_level'] ?? null,
                'timestamp' => $log->created_at->format('d.m.Y H:i'),
            ])->toArray();

        return view('admin.access-control.index', compact('users', 'departments', 'logs'));
    }

    public function updateUserWorkflowAccess(Request $request, User $user)
    {
        $validated = $request->validate([
            'access_level' => ['required', 'string', 'in:full,department,none'],
        ]);

        $old = $user->workflow_access_level;
        $user->update(['workflow_access_level' => $validated['access_level']]);

        $this->auditService->log(
            'workflow_access_updated',
            $user,
            ['workflow_access_level' => $old],
            ['workflow_access_level' => $validated['access_level']]
        );

        return response()->json(['ok' => true]);
    }

    public function updateDeptWorkflowAccess(Request $request, Department $department)
    {
        $validated = $request->validate([
            'access_level' => ['required', 'string', 'in:full,department,none'],
        ]);

        $old = $department->workflow_access_level;
        $department->update(['workflow_access_level' => $validated['access_level']]);

        $this->auditService->log(
            'workflow_access_updated',
            $department,
            ['workflow_access_level' => $old],
            ['workflow_access_level' => $validated['access_level']]
        );

        return response()->json(['ok' => true]);
    }

    public function updateUserTasksAccess(Request $request, User $user)
    {
        $validated = $request->validate([
            'access_level' => ['required', 'string', 'in:full,department,own,none'],
        ]);

        $old = $user->tasks_access_level;
        $user->update(['tasks_access_level' => $validated['access_level']]);

        $this->auditService->log(
            'workflow_access_updated',
            $user,
            ['tasks_access_level' => $old],
            ['tasks_access_level' => $validated['access_level']]
        );

        return response()->json(['ok' => true]);
    }

    public function updateDeptTasksAccess(Request $request, Department $department)
    {
        $validated = $request->validate([
            'access_level' => ['required', 'string', 'in:full,department,own,none'],
        ]);

        $old = $department->tasks_access_level;
        $department->update(['tasks_access_level' => $validated['access_level']]);

        $this->auditService->log(
            'workflow_access_updated',
            $department,
            ['tasks_access_level' => $old],
            ['tasks_access_level' => $validated['access_level']]
        );

        return response()->json(['ok' => true]);
    }

    public function updateUserArchiveAccess(Request $request, User $user)
    {
        $validated = $request->validate([
            'access_level' => ['required', 'string', 'in:full,department,own,none'],
        ]);

        $old = $user->archive_access_level;
        $user->update(['archive_access_level' => $validated['access_level']]);

        $this->auditService->log(
            'workflow_access_updated',
            $user,
            ['archive_access_level' => $old],
            ['archive_access_level' => $validated['access_level']]
        );

        return response()->json(['ok' => true]);
    }

    public function updateDeptArchiveAccess(Request $request, Department $department)
    {
        $validated = $request->validate([
            'access_level' => ['required', 'string', 'in:full,department,own,none'],
        ]);

        $old = $department->archive_access_level;
        $department->update(['archive_access_level' => $validated['access_level']]);

        $this->auditService->log(
            'workflow_access_updated',
            $department,
            ['archive_access_level' => $old],
            ['archive_access_level' => $validated['access_level']]
        );

        return response()->json(['ok' => true]);
    }
}
