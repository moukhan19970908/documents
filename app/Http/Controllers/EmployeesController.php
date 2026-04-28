<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;

class EmployeesController extends Controller
{
    public function index()
    {
        // Load full department tree with head and users
        $allDepartments = Department::with([
            'head',
            'users' => fn($q) => $q->where('is_active', true)->orderBy('name'),
        ])->get();

        // Build nested tree: root nodes (no parent)
        $tree = $this->buildTree($allDepartments);

        $totalUsers = User::where('is_active', true)->count();

        return view('employees.index', compact('tree', 'totalUsers'));
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
