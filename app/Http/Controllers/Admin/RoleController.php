<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index()
    {
        $roles = Role::orderByDesc('level')->orderBy('name')->get();
        $users = User::where('is_active', true)->with(['roles', 'department'])->orderBy('name')->get();

        // A user holds a role either as primary (users.role) or through the pivot.
        $holders = $roles->mapWithKeys(fn (Role $role) => [
            $role->id => $users->filter(fn (User $u) => $u->hasRole($role->code))->values(),
        ]);

        return view('admin.roles.index', compact('roles', 'holders'));
    }

    public function create()
    {
        $users = User::where('is_active', true)->with(['roles', 'department'])->orderBy('name')->get();
        return view('admin.roles.form', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRole($request);
        $userIds = $validated['users'] ?? [];
        unset($validated['users']);

        $role = Role::create($validated);
        $role->users()->sync($userIds);

        $this->auditService->log('role_created', $role);

        return redirect()->route('admin.roles.index')->with('success', 'Роль создана.');
    }

    public function edit(Role $role)
    {
        $users = User::where('is_active', true)->with(['roles', 'department'])->orderBy('name')->get();
        $role->load('users');
        return view('admin.roles.form', compact('role', 'users'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $this->validateRole($request, $role);
        $userIds = $validated['users'] ?? [];
        unset($validated['users']);

        $old = $role->toArray();
        // The code of a system role is referenced from application code — freeze it.
        if ($role->is_system) {
            unset($validated['code']);
        }
        $role->update($validated);
        $role->users()->sync($userIds);

        $this->auditService->log('role_updated', $role, $old, $role->toArray());

        return redirect()->route('admin.roles.index')->with('success', 'Роль обновлена.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'Системную роль удалить нельзя.');
        }

        $primaryCount = User::where('role', $role->code)->count();
        if ($primaryCount > 0) {
            return back()->with('error', "Роль назначена основной у {$primaryCount} польз. — сначала смените им основную роль.");
        }

        $this->auditService->log('role_deleted', $role);
        $role->delete(); // pivot rows cascade

        return redirect()->route('admin.roles.index')->with('success', 'Роль удалена.');
    }

    public function duplicate(Role $role)
    {
        $copy = $role->replicate();
        $copy->name = $role->name . ' (копия)';
        $copy->code = $this->uniqueCode($role->code);
        $copy->is_system = false;
        $copy->save();

        $copy->users()->sync($role->users()->pluck('users.id')->all());

        $this->auditService->log('role_duplicated', $copy);

        return redirect()->route('admin.roles.edit', $copy)->with('success', 'Роль продублирована.');
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'code'        => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')->ignore($role)],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon'        => ['required', Rule::in(array_keys(Role::ICONS))],
            'color'       => ['required', Rule::in(array_keys(Role::COLORS))],
            'level'       => ['required', 'integer', 'min:1', 'max:9'],
            'users'       => ['array'],
            'users.*'     => ['integer', 'exists:users,id'],
        ], [
            'code.regex' => 'Код может содержать только латиницу в нижнем регистре, цифры и «_», и должен начинаться с буквы.',
        ]);
    }

    private function uniqueCode(string $base): string
    {
        $code = $base . '_copy';
        $i = 2;
        while (Role::where('code', $code)->exists()) {
            $code = $base . '_copy' . $i++;
        }
        return $code;
    }
}
