<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmployeesController extends Controller
{
    public function index()
    {
        // Load full department tree with head and users.
        // Контейнеры направлений (is_direction) — это слой группировки, а не отдел
        // оргструктуры, поэтому в схему Битрикса их не показываем.
        $allDepartments = Department::with([
            'head',
            'users' => fn($q) => $q->where('is_active', true)->orderBy('name'),
        ])->where('is_direction', false)->get();

        // Build nested tree: root nodes (no parent)
        $tree = $this->buildTree($allDepartments);

        $totalUsers = User::where('is_active', true)->where('role', '!=', 'external')->count();

        // Админ правит оргструктуру прямо со схемы: справочники для формы отдела.
        $canManage       = auth()->user()->isAdmin();
        $formDepartments = $canManage ? $allDepartments->sortBy('name')->values() : collect();
        $formUsers       = $canManage
            ? User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();
        $formRoles       = $canManage
            ? Role::orderByDesc('level')->orderBy('name')->get(['code', 'name'])
            : collect();

        return view('employees.index', compact('tree', 'totalUsers', 'canManage', 'formDepartments', 'formUsers', 'formRoles'));
    }

    public function storeDepartment(Request $request, AuditService $auditService)
    {
        $department = Department::create($this->validateDepartment($request));
        $auditService->log('department_created', $department);

        return back()->with('success', 'Отдел «' . $department->name . '» создан.');
    }

    public function updateDepartment(Request $request, Department $department, AuditService $auditService)
    {
        $department->update($this->validateDepartment($request, $department));
        $auditService->log('department_updated', $department);

        return back()->with('success', 'Отдел «' . $department->name . '» обновлён.');
    }

    public function destroyDepartment(Department $department, AuditService $auditService)
    {
        // Внешние ключи стоят на nullOnDelete: удаление тихо оторвало бы
        // подчинённые отделы в корень, а сотрудников оставило бы без отдела.
        if ($department->children()->exists()) {
            return back()->with('error', 'У отдела есть подчинённые отделы — сначала перенесите или удалите их.');
        }

        if ($department->users()->exists()) {
            return back()->with('error', 'В отделе есть сотрудники — сначала переведите их в другой отдел.');
        }

        $auditService->log('department_deleted', $department);
        $department->delete();

        return back()->with('success', 'Отдел удалён.');
    }

    public function storeUser(Request $request, AuditService $auditService)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'role'          => ['required', 'exists:roles,code'],
            'position'      => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $user = User::create(array_merge($validated, ['password' => Hash::make($validated['password'])]));
        $auditService->log('user_created', $user);

        return back()->with('success', 'Сотрудник «' . $user->name . '» добавлен.');
    }

    public function updateUserPassword(Request $request, User $user, AuditService $auditService)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($request->input('password'))]);
        // Сам пароль в старых/новых значениях не пишем.
        $auditService->log('user_password_changed', $user);

        return back()->with('success', 'Пароль сотрудника «' . $user->name . '» изменён.');
    }

    public function deactivateUser(User $user, AuditService $auditService)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Нельзя деактивировать собственную учётную запись.');
        }

        $auditService->log('user_deleted', $user);
        $user->update(['is_active' => false]);

        return back()->with('success', 'Сотрудник «' . $user->name . '» деактивирован.');
    }

    public function updateUser(Request $request, User $user, AuditService $auditService)
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $old = $user->toArray();
        $user->update($validated);
        $auditService->log('user_updated', $user, $old, $user->toArray());

        return back()->with('success', 'Сотрудник «' . $user->name . '» переведён.');
    }

    private function validateDepartment(Request $request, ?Department $department = null): array
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'parent_id'    => ['nullable', 'exists:departments,id'],
            'head_user_id' => ['nullable', 'exists:users,id'],
        ]);

        // Отдел не может быть подчинён самому себе или своему потомку —
        // такая ветка выпала бы из дерева целиком.
        if ($department && ($validated['parent_id'] ?? null) !== null
            && in_array((int) $validated['parent_id'], Department::getDescendantIds($department->id), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Нельзя подчинить отдел самому себе или своему подразделению.',
            ]);
        }

        return $validated;
    }

    private function buildTree($departments, $parentId = null): \Illuminate\Support\Collection
    {
        return $departments
            ->where('parent_id', $parentId)
            ->values()
            ->map(function ($dept) use ($departments) {
                $dept->setRelation('children_tree', $this->buildTree($departments, $dept->id));
                return $dept;
            });
    }
}
