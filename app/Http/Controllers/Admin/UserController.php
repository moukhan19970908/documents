<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
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
        return view('admin.users.form', compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'role'          => ['required', 'in:admin,director,linear,archiver'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $user = User::create(array_merge($validated, ['password' => Hash::make($validated['password'])]));

        $this->auditService->log('user_created', $user);

        return redirect()->route('admin.users.index')->with('success', 'Пользователь создан.');
    }

    public function edit(User $user)
    {
        $departments = Department::all();
        return view('admin.users.form', compact('user', 'departments'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email,' . $user->id],
            'role'          => ['required', 'in:admin,director,linear,archiver'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active'     => ['boolean'],
        ]);

        $old = $user->toArray();
        $user->update($validated);

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
