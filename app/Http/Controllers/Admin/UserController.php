<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index()
    {
        $users = User::with('department')->paginate(25);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $departments = Department::all();
        $roles = Role::orderByDesc('level')->orderBy('name')->get();
        return view('admin.users.form', compact('departments', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'role'          => ['required', 'exists:roles,code'],
            'roles'         => ['array'],
            'roles.*'       => ['integer', 'exists:roles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $extraRoles = $validated['roles'] ?? [];
        unset($validated['roles']);

        $user = User::create(array_merge($validated, ['password' => Hash::make($validated['password'])]));
        $user->roles()->sync($extraRoles);

        $this->auditService->log('user_created', $user);

        return redirect()->route('admin.users.index')->with('success', 'Пользователь создан.');
    }

    public function edit(User $user)
    {
        $departments = Department::all();
        $roles = Role::orderByDesc('level')->orderBy('name')->get();
        $user->load('roles');
        return view('admin.users.form', compact('user', 'departments', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email,' . $user->id],
            'role'          => ['required', 'exists:roles,code'],
            'roles'         => ['array'],
            'roles.*'       => ['integer', 'exists:roles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active'     => ['boolean'],
        ]);

        $extraRoles = $validated['roles'] ?? [];
        unset($validated['roles']);

        $old = $user->toArray();
        $user->update($validated);
        $user->roles()->sync($extraRoles);

        $this->auditService->log('user_updated', $user, $old, $user->toArray());

        return redirect()->route('admin.users.index')->with('success', 'Пользователь обновлён.');
    }

    public function destroy(User $user)
    {
        $this->auditService->log('user_deleted', $user);
        $user->update(['is_active' => false]);
        return redirect()->route('admin.users.index')->with('success', 'Пользователь деактивирован.');
    }
}
