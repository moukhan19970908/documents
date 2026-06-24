<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowFolder;
use Illuminate\Http\Request;

class WorkflowFolderController extends Controller
{
    /** Maximum nesting depth: root → sub-folder → sub-sub-folder. */
    private const MAX_DEPTH = 3;

    public function index()
    {
        $folders = WorkflowFolder::with([
                'children'          => fn($q) => $q->withCount('workflows'),
                'children.children' => fn($q) => $q->withCount('workflows'),
                'parent',
            ])
            ->whereNull('parent_id')
            ->withCount('workflows')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.workflow-folders.index', compact('folders'));
    }

    public function create()
    {
        $parentOptions = $this->parentOptions();

        return view('admin.workflow-folders.create', compact('parentOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:workflow_folders,id'],
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = WorkflowFolder::find($validated['parent_id']);
            // A new folder adds one level under its parent.
            if ($parent && $parent->depth() + 1 > self::MAX_DEPTH) {
                return back()->withErrors(['parent_id' => 'Максимальная вложенность — ' . self::MAX_DEPTH . ' уровня.'])->withInput();
            }
        }

        WorkflowFolder::create($validated);

        return redirect()->route('admin.workflow-folders.index')
            ->with('success', 'Папка создана.');
    }

    public function edit(WorkflowFolder $workflowFolder)
    {
        $parentOptions = $this->parentOptions($workflowFolder);

        return view('admin.workflow-folders.edit', compact('workflowFolder', 'parentOptions'));
    }

    public function update(Request $request, WorkflowFolder $workflowFolder)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:workflow_folders,id'],
        ]);

        if (!empty($validated['parent_id'])) {
            if ($validated['parent_id'] == $workflowFolder->id) {
                return back()->withErrors(['parent_id' => 'Папка не может быть родителем самой себя.'])->withInput();
            }
            $parent = WorkflowFolder::find($validated['parent_id']);
            if ($parent) {
                // Prevent moving a folder into one of its own descendants.
                if (in_array($parent->id, $workflowFolder->descendantIds())) {
                    return back()->withErrors(['parent_id' => 'Нельзя переместить папку в её собственную подпапку.'])->withInput();
                }
                // The folder (with its own subtree) must still fit within the depth limit.
                if ($parent->depth() + $workflowFolder->subtreeHeight() > self::MAX_DEPTH) {
                    return back()->withErrors(['parent_id' => 'Максимальная вложенность — ' . self::MAX_DEPTH . ' уровня.'])->withInput();
                }
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

    /**
     * Flat, hierarchically-ordered list of folders eligible to be a parent.
     * Only folders shallow enough to hold a child are included; when editing,
     * the folder itself and its descendants are excluded.
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, name:string, depth:int}>
     */
    private function parentOptions(?WorkflowFolder $exclude = null): \Illuminate\Support\Collection
    {
        $excludeIds = $exclude ? array_merge([$exclude->id], $exclude->descendantIds()) : [];

        $roots = WorkflowFolder::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $options = collect();

        $walk = function ($folders, int $depth) use (&$walk, $options, $excludeIds) {
            foreach ($folders as $folder) {
                if (in_array($folder->id, $excludeIds)) {
                    continue;
                }
                $options->push(['id' => $folder->id, 'name' => $folder->name, 'depth' => $depth]);
                // A folder can be a parent only if a child placed under it stays within the limit.
                if ($depth + 1 < self::MAX_DEPTH) {
                    $walk($folder->children, $depth + 1);
                }
            }
        };

        $walk($roots, 1);

        return $options;
    }
}
