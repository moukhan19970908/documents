<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'document_id', 'document_approval_stage_id', 'assignee_id',
        'title', 'status', 'deadline_at', 'completed_at',
    ];

    protected $casts = [
        'deadline_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DocumentApprovalStage::class, 'document_approval_stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function isOverdue(): bool
    {
        return $this->deadline_at && now()->gt($this->deadline_at) && $this->status === 'pending';
    }
}
