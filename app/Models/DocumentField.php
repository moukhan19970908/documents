<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentField extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type_id', 'document_subtype_id', 'label', 'field_key', 'field_type',
        'options', 'reference_to', 'is_required', 'sort_order',
        'use_in_name', 'use_in_filter', 'use_in_archive',
    ];

    protected $casts = [
        'options'        => 'array',
        'is_required'    => 'boolean',
        'use_in_name'    => 'boolean',
        'use_in_filter'  => 'boolean',
        'use_in_archive' => 'boolean',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function subtype(): BelongsTo
    {
        return $this->belongsTo(DocumentSubtype::class, 'document_subtype_id');
    }
}
