<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentApprovalStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_approval_id', 'workflow_stage_id', 'status',
        'is_overdue', 'started_at', 'completed_at', 'deadline_at',
    ];

    protected $casts = [
        'is_overdue'   => 'boolean',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'deadline_at'  => 'datetime',
    ];

    public function documentApproval(): BelongsTo
    {
        return $this->belongsTo(DocumentApproval::class);
    }

    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(DocumentApprovalDecision::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DocumentNote::class);
    }

    public function approvers(): HasMany
    {
        return $this->workflowStage->approvers();
    }
}
