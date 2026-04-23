<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon', 'default_workflow_id'];

    public function defaultWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'default_workflow_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
