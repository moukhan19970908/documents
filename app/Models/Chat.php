<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    protected $fillable = ['document_id', 'document_approval_id'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(DocumentApproval::class, 'document_approval_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->latest();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants');
    }
}
