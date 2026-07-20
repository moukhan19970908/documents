<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Material;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Mews\Purifier\Facades\Purifier;

class KnowledgeController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function create()
    {
        return view('admin.knowledge.form', $this->formData(new Material(['type' => 'article'])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $material = Material::create($data + [
            'author_id'    => auth()->id(),
            'is_published' => true,
        ]);

        // Стартовый доступ наследуется из размещения: отдел + его уровень.
        if ($material->department_id) {
            $material->accessDepartments()->sync([$material->department_id]);
            $material->update(['access_level' => $material->level ?? 'employees']);
        }

        $this->auditService->log('material_created', $material);

        return redirect()->route('admin.knowledge.access', $material)
            ->with('success', 'Материал создан. Настройте доступ.');
    }

    public function edit(Material $material)
    {
        return view('admin.knowledge.form', $this->formData($material));
    }

    public function update(Request $request, Material $material)
    {
        $material->update($this->validated($request));

        $this->auditService->log('material_updated', $material);

        return redirect()->route('admin.knowledge.edit', $material)
            ->with('success', 'Материал сохранён.');
    }

    public function destroy(Material $material)
    {
        $this->auditService->log('material_deleted', $material);
        $material->delete();

        return redirect()->route('knowledge.index')->with('success', 'Материал удалён.');
    }

    /** Страница «Управление доступом». */
    public function access(Material $material)
    {
        $material->load(['accessDepartments', 'allowedUsers']);

        $directions = Department::with('children')->whereNull('parent_id')->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        return view('admin.knowledge.access', [
            'material'   => $material,
            'directions' => $directions,
            'users'      => $users,
            'levels'     => Material::LEVELS,
        ]);
    }

    public function updateAccess(Request $request, Material $material)
    {
        $data = $request->validate([
            'is_general'     => ['nullable', 'boolean'],
            'access_level'   => ['required', Rule::in(array_keys(Material::LEVELS))],
            'departments'    => ['nullable', 'array'],
            'departments.*'  => ['integer', 'exists:departments,id'],
            'users'          => ['nullable', 'array'],
            'users.*'        => ['integer', 'exists:users,id'],
        ]);

        $material->update([
            'is_general'   => (bool) ($data['is_general'] ?? false),
            'access_level' => $data['access_level'],
        ]);

        $material->accessDepartments()->sync($data['departments'] ?? []);
        $material->allowedUsers()->sync($data['users'] ?? []);

        $this->auditService->log('material_access_updated', $material);

        return redirect()->route('admin.knowledge.access', $material)
            ->with('success', 'Доступ сохранён.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'type'          => ['required', Rule::in(array_keys(Material::TYPES))],
            'study_minutes' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'body'          => ['nullable', 'string'],
            'direction_id'  => ['nullable', 'exists:departments,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'level'         => ['nullable', Rule::in(array_keys(Material::LEVELS))],
        ]);

        $data['body'] = filled($data['body'] ?? null) ? Purifier::clean($data['body'], 'blank') : null;

        return $data;
    }

    /** Направления с отделами (для зависимых списков размещения) + справочники. */
    private function formData(Material $material): array
    {
        $directions = Department::with('children')->whereNull('parent_id')->orderBy('name')->get()
            ->map(fn (Department $d) => [
                'id'          => $d->id,
                'name'        => $d->name,
                'departments' => $d->children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            ])->values();

        return [
            'material'      => $material,
            'directionsJson' => $directions,
            'types'         => Material::TYPES,
            'levels'        => Material::LEVEL_PLACEMENT,
        ];
    }
}
