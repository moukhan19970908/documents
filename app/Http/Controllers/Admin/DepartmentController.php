<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index()
    {
        $departments = Department::with(['parent', 'head'])->get();
        return view('admin.departments.index', compact('departments'));
    }

    public function create()
    {
        $departments = Department::all();
        $users = User::where('is_active', true)->get();
        return view('admin.departments.form', compact('departments', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'parent_id'    => ['nullable', 'exists:departments,id'],
            'head_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $department = Department::create($validated);
        $this->auditService->log('department_created', $department);

        return redirect()->route('admin.departments.index')->with('success', 'Отдел создан.');
    }

    public function edit(Department $department)
    {
        $departments = Department::where('id', '!=', $department->id)->get();
        $users = User::where('is_active', true)->get();
        return view('admin.departments.form', compact('department', 'departments', 'users'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'parent_id'    => ['nullable', 'exists:departments,id'],
            'head_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $department->update($validated);
        $this->auditService->log('department_updated', $department);

        return redirect()->route('admin.departments.index')->with('success', 'Отдел обновлён.');
    }

    public function destroy(Department $department)
    {
        $this->auditService->log('department_deleted', $department);
        $department->delete();
        return redirect()->route('admin.departments.index')->with('success', 'Отдел удалён.');
    }
}
