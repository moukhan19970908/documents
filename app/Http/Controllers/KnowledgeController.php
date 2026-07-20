<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Material;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $user    = $request->user();
        $isAdmin = $user->isAdmin();

        $materials = Material::with(['direction', 'department', 'accessDepartments', 'allowedUsers'])
            ->orderByDesc('created_at')
            ->get();

        // Обычный сотрудник видит только доступные ему опубликованные материалы.
        if (!$isAdmin) {
            $materials = $materials->filter(fn (Material $m) => $m->visibleTo($user))->values();
        }

        // Фильтр по выбранному узлу дерева.
        if ($request->boolean('general')) {
            $materials = $materials->where('is_general', true)->values();
        } elseif ($request->filled('department')) {
            $deptId    = (int) $request->input('department');
            $materials = $materials->where('department_id', $deptId)
                ->when($request->filled('level'), fn ($c) => $c->where('level', $request->input('level')))
                ->values();
        } elseif ($request->filled('direction')) {
            $materials = $materials->where('direction_id', (int) $request->input('direction'))->values();
        }

        // Дерево: направления (корневые отделы) → отделы.
        $directions = Department::with('children')->whereNull('parent_id')->orderBy('name')->get();

        return view('knowledge.index', [
            'materials'  => $materials,
            'directions' => $directions,
            'isAdmin'    => $isAdmin,
            'levels'     => Material::LEVEL_PLACEMENT,
        ]);
    }

    public function show(Request $request, Material $material)
    {
        $material->load(['direction', 'department', 'author', 'accessDepartments', 'allowedUsers']);

        abort_unless($material->visibleTo($request->user()), 403);

        return view('knowledge.show', compact('material'));
    }
}
