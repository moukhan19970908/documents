<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    protected $fillable = [
        'number', 'seq', 'procedure_template_id', 'title', 'initiator_id',
        'status', 'current_position', 'data', 'stopped_reason',
    ];

    protected $casts = ['data' => 'array'];

    /** Статусы карточки процедуры. */
    public const STATUSES = [
        'draft'          => 'Черновик',
        'in_progress'    => 'В работе',
        'awaiting_tasks' => 'Ожидание задач',
        'stopped'        => 'Остановлена',
        'completed'      => 'Завершена',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_template_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ProcedureStageRun::class)->orderBy('position')->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProcedureFile::class)->latest('id');
    }

    public function checklistEntries(): HasMany
    {
        return $this->hasMany(ProcedureChecklistEntry::class)->orderBy('position')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProcedureTask::class)->latest('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProcedureEvent::class)->latest('id');
    }

    /** Активный сейчас этап (тот, что ждёт действия исполнителя). */
    public function activeRun(): ?ProcedureStageRun
    {
        return $this->runs->firstWhere('status', 'active');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'draft'          => 'bg-gray-100 text-gray-600',
            'in_progress'    => 'bg-blue-100 text-blue-700',
            'awaiting_tasks' => 'bg-amber-100 text-amber-700',
            'stopped'        => 'bg-red-100 text-red-700',
            'completed'      => 'bg-green-100 text-green-700',
            default          => 'bg-gray-100 text-gray-600',
        };
    }

    public function isDone(): bool
    {
        return in_array($this->status, ['completed', 'stopped'], true);
    }

    /** Видимость карточки: инициатор, исполнитель любого этапа/задачи, admin или право view_all. */
    public function visibleTo(User $user): bool
    {
        if ($user->isAdmin() || $user->hasMatrixPermission('procedures.view_all')) {
            return true;
        }
        if ($this->initiator_id === $user->id) {
            return true;
        }
        if ($this->runs->contains('executor_id', $user->id)) {
            return true;
        }

        return $this->tasks->contains('assignee_id', $user->id);
    }
}
