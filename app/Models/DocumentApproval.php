<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'workflow_id', 'parameter_values', 'started_at', 'completed_at', 'status',
    ];

    protected $casts = [
        'parameter_values' => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(DocumentApprovalStage::class);
    }

    public function activeStage()
    {
        // «accepted» — звено приёма, уже принятое к исполнению: оно всё ещё активно,
        // исполнитель должен нажать «Исполнено».
        $active = ['in_progress', 'accepted'];

        // Use loaded collection if available to avoid extra query
        if ($this->relationLoaded('stages')) {
            return $this->stages->whereIn('status', $active)->first();
        }
        return $this->stages()->whereIn('status', $active)->first();
    }
}
