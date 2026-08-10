<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRoute;
use App\Models\ApprovalRouteStep;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class ApprovalRouteController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalRoute::with(['department', 'steps.approverUser'])
            ->withCount('steps')
            ->where('is_ephemeral', false)   // одноразовые маршруты из графа не показываем
            ->orderBy('request_type')
            ->orderBy('name');

        if ($type = $request->get('request_type')) {
            $query->where('request_type', $type);
        }

        if ($department = $request->get('department')) {
            if ($department === 'none') {
                $query->whereNull('department_id');
            } else {
                $query->where('department_id', $department);
            }
        }

        $routes      = $query->get();
        $departments = Department::orderBy('name')->get();

        return view('admin.approval-routes.index', compact('routes', 'departments'));
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get();
        $users       = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role_level', 'role_title', 'position']);
        return view('admin.approval-routes.create', compact('departments', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'request_type'  => ['required', 'in:trip,vacation,vacation_registry'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'applies_to_role_level' => ['nullable', 'integer', 'min:1', 'max:7'],
            'is_active'     => ['boolean'],
            'steps'         => ['required', 'array', 'min:1'],
            'steps.*.approver_role_level' => ['nullable', 'integer', 'min:1', 'max:7'],
            'steps.*.approver_user_id'   => ['nullable', 'exists:users,id'],
        ]);

        $route = ApprovalRoute::create([
            'name'          => $data['name'],
            'request_type'  => $data['request_type'],
            'department_id' => $data['department_id'] ?? null,
            'applies_to_role_level' => $data['applies_to_role_level'] ?? null,
            'is_active'     => $request->boolean('is_active', true),
        ]);

        foreach ($data['steps'] as $index => $step) {
            $route->steps()->create([
                'step_order'          => $index + 1,
                'approver_role_level' => $step['approver_role_level'] ?? null,
                'approver_user_id'    => $step['approver_user_id'] ?? null,
            ]);
        }

        return redirect()->route('admin.approval-routes.index')->with('success', 'Маршрут создан.');
    }

    public function edit(ApprovalRoute $approvalRoute)
    {
        $approvalRoute->load('steps.approverUser');
        $departments = Department::orderBy('name')->get();
        $users       = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role_level', 'role_title', 'position']);
        return view('admin.approval-routes.edit', compact('approvalRoute', 'departments', 'users'));
    }

    public function update(Request $request, ApprovalRoute $approvalRoute)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'request_type'  => ['required', 'in:trip,vacation,vacation_registry'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'applies_to_role_level' => ['nullable', 'integer', 'min:1', 'max:7'],
            'steps'         => ['required', 'array', 'min:1'],
            'steps.*.approver_role_level' => ['nullable', 'integer', 'min:1', 'max:7'],
            'steps.*.approver_user_id'   => ['nullable', 'exists:users,id'],
        ]);

        $approvalRoute->update([
            'name'          => $data['name'],
            'request_type'  => $data['request_type'],
            'department_id' => $data['department_id'] ?? null,
            'applies_to_role_level' => $data['applies_to_role_level'] ?? null,
            'is_active'     => $request->boolean('is_active', true),
        ]);

        $approvalRoute->steps()->delete();
        foreach ($data['steps'] as $index => $step) {
            $approvalRoute->steps()->create([
                'step_order'          => $index + 1,
                'approver_role_level' => $step['approver_role_level'] ?? null,
                'approver_user_id'    => $step['approver_user_id'] ?? null,
            ]);
        }

        return redirect()->route('admin.approval-routes.index')->with('success', 'Маршрут обновлён.');
    }

    public function destroy(ApprovalRoute $approvalRoute)
    {
        $approvalRoute->delete();
        return redirect()->route('admin.approval-routes.index')->with('success', 'Маршрут удалён.');
    }

    public function toggle(ApprovalRoute $approvalRoute)
    {
        $approvalRoute->update(['is_active' => !$approvalRoute->is_active]);
        return back();
    }
}
