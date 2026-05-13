<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'document_type_id', 'created_by', 'is_system', 'is_active',
        'approval_type', 'allowed_departments', 'process_fields',
    ];

    protected $casts = [
        'is_system'           => 'boolean',
        'is_active'           => 'boolean',
        'allowed_departments' => 'array',
        'process_fields'      => 'array',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('sort_order');
    }
}
