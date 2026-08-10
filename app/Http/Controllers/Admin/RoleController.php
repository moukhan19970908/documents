<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DocumentWatcher;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function matrix()
    {
        $roles = Role::orderByDesc('level')->orderBy('name')->get();
        $groups = config('permissions');

        // Состояние галочек берём из БД: permission => [role_id, ...].
        $granted = DB::table('role_permissions')
            ->get(['permission', 'role_id'])
            ->groupBy('permission')
            ->map(fn ($rows) => $rows->pluck('role_id')->all())
            ->all();

        return view('admin.roles.matrix', compact('roles', 'groups', 'granted'));
    }

    public function updateMatrix(Request $request)
    {
        $submitted = $request->input('permissions', []);
        $keys = Permissions::allKeys();

        DB::transaction(function () use ($keys, $submitted) {
            DB::table('role_permissions')->whereIn('permission', $keys)->delete();

            $rows = [];
            foreach ($keys as $key) {
                foreach ($submitted[$key] ?? [] as $roleId) {
                    $rows[] = ['role_id' => (int) $roleId, 'permission' => $key];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('role_permissions')->insert($chunk);
            }
        });

        Permissions::clearCache();
        $this->auditService->log('roles_matrix_updated');

        return redirect()->route('admin.roles.matrix')->with('success', 'Матрица прав сохранена.');
    }

    public function watchers()
    {
        $users = User::where('is_active', true)->orderBy('name')->get();

        $rules = DocumentWatcher::with(['watcher', 'target'])
            ->orderByDesc('id')
            ->get();

        return view('admin.roles.watchers', compact('rules', 'users'));
    }

    public function storeWatcher(Request $request)
    {
        $data = $this->validateWatcher($request);

        DocumentWatcher::updateOrCreate(
            ['watcher_id' => $data['watcher_id'], 'target_id' => $data['target_id']],
            ['scope' => $data['scope']]
        );

        $this->auditService->log('watcher_created');

        return redirect()->route('admin.roles.watchers')->with('success', 'Правило наблюдения сохранено.');
    }

    public function updateWatcher(Request $request, DocumentWatcher $watcher)
    {
        $data = $this->validateWatcher($request, $watcher);

        // Не даём столкнуться с уникальным ключом (watcher_id, target_id).
        DocumentWatcher::where('watcher_id', $data['watcher_id'])
            ->where('target_id', $data['target_id'])
            ->where('id', '!=', $watcher->id)
            ->delete();

        $watcher->update($data);

        $this->auditService->log('watcher_updated');

        return redirect()->route('admin.roles.watchers')->with('success', 'Правило наблюдения обновлено.');
    }

    public function destroyWatcher(DocumentWatcher $watcher)
    {
        $watcher->delete();

        $this->auditService->log('watcher_deleted');

        return redirect()->route('admin.roles.watchers')->with('success', 'Правило наблюдения удалено.');
    }

    private function validateWatcher(Request $request, ?DocumentWatcher $watcher = null): array
    {
        return $request->validate([
            'watcher_id' => ['required', 'integer', 'exists:users,id', 'different:target_id'],
            'target_id'  => ['required', 'integer', 'exists:users,id'],
            'scope'      => ['required', Rule::in(array_keys(DocumentWatcher::SCOPES))],
        ], [
            'watcher_id.different' => 'Наблюдатель и наблюдаемый должны быть разными пользователями.',
        ]);
    }

    public function personal()
    {
        $users = User::where('is_active', true)->orderBy('name')->get();

        $permissions = collect(config('permissions'))
            ->flatMap(fn (array $group) => $group['items'])
            ->map(fn (array $item) => ['key' => $item['key'], 'label' => $item['label']]);

        // Верстка: персональных прав в БД пока нет — строки собраны как заглушка.
        $grants = collect([
            ['scope' => 'Отдел дистрибуции',      'permission' => 'Формировать реестры заявок', 'until' => '31.12.2026'],
            ['scope' => 'Направление «Продажи»',  'permission' => 'Видеть все приказы',         'until' => null],
            ['scope' => 'Ключевые клиенты',       'permission' => 'Инициировать проверки',      'until' => '01.10.2026'],
        ])->map(fn (array $grant, int $i) => $grant + [
            'user'      => $users->skip($i)->first(),
            'granted_by' => $users->skip(4 + $i)->first() ?? $users->last(),
        ]);

        return view('admin.roles.personal', compact('grants', 'users', 'permissions'));
    }

    public function directions()
    {
        $directions = Department::whereNull('parent_id')
            ->with(['head', 'members' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        // Отделы-кандидаты для добавления в направление (все департаменты).
        $allDepartments = Department::orderBy('name')->get(['id', 'name', 'parent_id']);

        return view('admin.roles.directions', compact('directions', 'allDepartments'));
    }

    public function storeDirection(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'departments'   => ['array'],
            'departments.*' => ['integer', 'exists:departments,id'],
        ]);

        $direction = Department::create(['name' => $data['name'], 'is_direction' => true]);

        if (!empty($data['departments'])) {
            // Членство в направлении — отдельный слой (direction_id). parent_id
            // (дерево Битрикса) не трогаем, чтобы не ломать оргструктуру.
            Department::whereIn('id', $data['departments'])
                ->where('id', '!=', $direction->id)
                ->update(['direction_id' => $direction->id]);
        }

        $this->auditService->log('direction_created', $direction);

        return redirect()->route('admin.roles.directions')->with('success', 'Направление создано.');
    }

    public function addDirectionDepartment(Request $request, Department $department)
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);

        $childId = (int) $data['department_id'];

        if ($childId === $department->id) {
            return back()->with('error', 'Нельзя добавить направление само в себя.');
        }

        Department::whereKey($childId)->update(['direction_id' => $department->id]);

        $this->auditService->log('direction_department_added', $department);

        return redirect()->route('admin.roles.directions')->with('success', 'Отдел добавлен в направление.');
    }

    public function removeDirectionDepartment(Department $department, Department $child)
    {
        if ((int) $child->direction_id === $department->id) {
            $child->update(['direction_id' => null]);
            $this->auditService->log('direction_department_removed', $department);
        }

        return redirect()->route('admin.roles.directions')->with('success', 'Отдел откреплён от направления.');
    }

    public function destroyDirection(Department $department)
    {
        if ($department->members()->exists()) {
            return back()->with('error', 'Сначала открепите все отделы направления.');
        }

        $this->auditService->log('direction_deleted', $department);
        $department->delete();

        return redirect()->route('admin.roles.directions')->with('success', 'Направление удалено.');
    }

    public function updateDirectionCrossVisibility(Request $request, Department $department)
    {
        $department->update([
            'cross_visibility' => $request->boolean('cross_visibility'),
        ]);

        $this->auditService->log('direction_cross_visibility_updated', $department);

        return redirect()->route('admin.roles.directions')
            ->with('success', 'Настройка направления сохранена.');
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
        $this->seedDefaultPermissions($role);

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

        // Копируем гранты исходной роли.
        $rows = DB::table('role_permissions')
            ->where('role_id', $role->id)
            ->pluck('permission')
            ->map(fn ($key) => ['role_id' => $copy->id, 'permission' => $key])
            ->all();
        if ($rows) {
            DB::table('role_permissions')->insert($rows);
        }

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

    /** Засеять новой роли гранты по дефолтам каталога (only/except). */
    private function seedDefaultPermissions(Role $role): void
    {
        $rows = [];
        foreach (Permissions::items() as $item) {
            if (Permissions::defaultAllows($item, $role->code)) {
                $rows[] = ['role_id' => $role->id, 'permission' => $item['key']];
            }
        }

        if ($rows) {
            DB::table('role_permissions')->insert($rows);
        }
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
