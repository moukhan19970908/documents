<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlankTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'document_type_id', 'document_subtype_id',
        'content', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function subtype(): BelongsTo
    {
        return $this->belongsTo(DocumentSubtype::class, 'document_subtype_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
