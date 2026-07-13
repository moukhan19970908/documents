<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ветка развилки: условие + свой состав согласующих. При публикации каждая ветка
 * разворачивается в обычное условное звено; в маршрут документа входит первая подходящая.
 */
class WorkflowStageBranch extends Model
{
    protected $fillable = [
        'workflow_stage_id', 'name', 'condition_key', 'condition_operator', 'condition_value',
        'approver_ids', 'department_ids', 'policy', 'sort_order',
    ];

    protected $casts = [
        'approver_ids'   => 'array',
        'department_ids' => 'array',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }
}
