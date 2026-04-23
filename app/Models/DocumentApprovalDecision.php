<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApprovalDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_approval_stage_id', 'user_id', 'action',
        'comment', 'delegated_to', 'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DocumentApprovalStage::class, 'document_approval_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delegatee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to');
    }
}
