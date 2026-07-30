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
        'document_id', 'workflow_id', 'current_node_id', 'parameter_values', 'runtime_data',
        'started_at', 'completed_at', 'status',
    ];

    protected $casts = [
        'parameter_values' => 'array',
        'runtime_data'     => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    /** Чем закончился круг согласования. Возврат на доработку — свой исход, не отклонение. */
    public const STATUSES = [
        'in_progress'      => 'Идёт согласование',
        'approved'         => 'Согласован',
        'rejected'         => 'Отклонён',
        'requires_changes' => 'Отправлен на доработку',
        'cancelled'        => 'Отменён',
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'approved'         => 'bg-green-50 text-green-700 border-green-200',
            'rejected'         => 'bg-red-50 text-red-700 border-red-200',
            'requires_changes' => 'bg-orange-50 text-orange-700 border-orange-200',
            'cancelled'        => 'bg-gray-100 text-gray-600 border-gray-200',
            default            => 'bg-blue-50 text-blue-700 border-blue-200',
        };
    }

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
