<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRegistration extends Model
{
    protected $fillable = [
        'document_id', 'numerator_id', 'scope_key', 'number',
        'registered_at', 'registered_by', 'is_manual',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'is_manual'     => 'boolean',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
