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
}
