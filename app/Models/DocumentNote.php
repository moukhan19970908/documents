<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'document_approval_stage_id', 'user_id', 'body', 'attachment_path',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DocumentApprovalStage::class, 'document_approval_stage_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
