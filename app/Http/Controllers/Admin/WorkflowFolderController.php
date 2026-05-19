<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowFolder;
use Illuminate\Http\Request;

class WorkflowFolderController extends Controller
{
    public function index()
    {
        $folders = WorkflowFolder::with(['children' => fn($q) => $q->withCount('workflows'), 'parent'])
            ->whereNull('parent_id')
            ->withCount('workflows')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.workflow-folders.index', compact('folders'));
    }

    public function create()
    {
        $rootFolders = WorkflowFolder::whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('admin.workflow-folders.create', compact('rootFolders'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:workflow_folders,id'],
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = WorkflowFolder::find($validated['parent_id']);
            if ($parent && $parent->parent_id !== null) {
                return back()->withErrors(['parent_id' => 'Вложенность не может быть больше 2 уровней.'])->withInput();
            }
        }

        WorkflowFolder::create($validated);

        return redirect()->route('admin.workflow-folders.index')
            ->with('success', 'Папка создана.');
    }

    public function edit(WorkflowFolder $workflowFolder)
    {
        $rootFolders = WorkflowFolder::whereNull('parent_id')
            ->where('id', '!=', $workflowFolder->id)
            ->orderBy('name')
            ->get();

        return view('admin.workflow-folders.edit', compact('workflowFolder', 'rootFolders'));
    }

    public function update(Request $request, WorkflowFolder $workflowFolder)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:workflow_folders,id'],
        ]);

        if (!empty($validated['parent_id'])) {
            // Prevent circular reference
            if ($validated['parent_id'] == $workflowFolder->id) {
                return back()->withErrors(['parent_id' => 'Папка не может быть родителем самой себя.'])->withInput();
            }
            $parent = WorkflowFolder::find($validated['parent_id']);
            if ($parent && $parent->parent_id !== null) {
                return back()->withErrors(['parent_id' => 'Вложенность не может быть больше 2 уровней.'])->withInput();
            }
        }

        $workflowFolder->update($validated);

        return redirect()->route('admin.workflow-folders.index')
            ->with('success', 'Папка обновлена.');
    }

    public function destroy(WorkflowFolder $workflowFolder)
    {
        $workflowFolder->delete();

        return redirect()->route('admin.workflow-folders.index')
            ->with('success', 'Папка удалена.');
    }
}
