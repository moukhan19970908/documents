<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStageApprover extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_stage_id', 'approver_type', 'approver_id', 'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
