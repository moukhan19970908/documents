<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'document_type_id', 'initiator_id',
        'current_stage_id', 'status', 'data', 'bitrix24_task_id', 'deadline_at',
    ];

    protected $casts = [
        'data'        => 'array',
        'deadline_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function currentFile(): HasOne
    {
        return $this->hasOne(DocumentFile::class)->where('is_current', true)->latestOfMany();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    public function activeApproval(): HasOne
    {
        return $this->hasOne(DocumentApproval::class)->where('status', 'in_progress')->latestOfMany();
    }

    public function latestApproval(): HasOne
    {
        return $this->hasOne(DocumentApproval::class)->latestOfMany();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DocumentNote::class)->latest();
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'            => 'Черновик',
            'in_review'        => 'На одобрении',
            'requires_changes' => 'Требует изменений',
            'approved'         => 'Одобрено',
            'signed'           => 'Подписано',
            'archived'         => 'Архив',
            default            => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft'            => 'gray',
            'in_review'        => 'blue',
            'requires_changes' => 'red',
            'approved'         => 'green',
            'signed'           => 'indigo',
            'archived'         => 'gray',
            default            => 'gray',
        };
    }
}
