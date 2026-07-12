<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSubtype extends Model
{
    protected $fillable = [
        'document_type_id', 'code', 'name', 'name_template',
        'numerator_id', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function numerator(): BelongsTo
    {
        return $this->belongsTo(Numerator::class);
    }

    /** Scenarios serving this subtype. */
    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'document_subtype_workflow');
    }

    /** Extra attributes on top of the parent type's ones. */
    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** The mask actually used: subtype override wins, otherwise the type's. */
    public function effectiveNameTemplate(): ?string
    {
        return $this->name_template ?: $this->type?->name_template;
    }

    public function effectiveNumerator(): ?Numerator
    {
        return $this->numerator ?: $this->type?->numerator;
    }
}
