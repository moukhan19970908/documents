<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowFolder extends Model
{
    protected $fillable = ['name', 'parent_id', 'sort_order'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorkflowFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WorkflowFolder::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'workflow_folder_workflow')->where('is_active', true);
    }

    /** Nesting level of this folder: 1 = root, 2 = sub-folder, 3 = sub-sub-folder. */
    public function depth(): int
    {
        $depth = 1;
        $node  = $this;
        while ($node->parent_id !== null) {
            $node = $node->parent;
            $depth++;
        }
        return $depth;
    }

    /** Height of the subtree rooted at this folder: 1 = no children, 2 = has children, etc. */
    public function subtreeHeight(): int
    {
        if ($this->children->isEmpty()) {
            return 1;
        }
        return 1 + $this->children->max(fn($child) => $child->subtreeHeight());
    }

    /** Ids of all descendants (children, grandchildren, …). */
    public function descendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids   = array_merge($ids, $child->descendantIds());
        }
        return $ids;
    }
}
