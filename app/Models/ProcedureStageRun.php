<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureStageRun extends Model
{
    protected $fillable = [
        'procedure_id', 'procedure_stage_id', 'position', 'type', 'title',
        'executor_id', 'status', 'verdict', 'comment', 'acted_by', 'acted_at',
    ];

    protected $casts = ['acted_at' => 'datetime'];

    public const STATUSES = [
        'pending'  => 'Ожидает',
        'active'   => 'В работе',
        'done'     => 'Пройден',
        'rejected' => 'Отклонён',
        'returned' => 'Возврат',
        'skipped'  => 'Пропущен',
    ];

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProcedureStage::class, 'procedure_stage_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /** Вложения, приложенные на этом этапе. */
    public function files(): HasMany
    {
        return $this->hasMany(ProcedureFile::class)->latest('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active'   => 'bg-blue-100 text-blue-700',
            'done'     => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700',
            'returned' => 'bg-amber-100 text-amber-700',
            'skipped'  => 'bg-gray-100 text-gray-500',
            default    => 'bg-gray-100 text-gray-600',
        };
    }

    public function canAct(User $user): bool
    {
        return $this->status === 'active' && ($this->executor_id === $user->id || $user->isAdmin());
    }
}
