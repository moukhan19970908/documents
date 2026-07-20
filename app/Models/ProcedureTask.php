<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureTask extends Model
{
    protected $fillable = [
        'procedure_id', 'procedure_checklist_entry_id', 'title', 'description',
        'assignee_id', 'due_at', 'status', 'result_comment', 'return_comment',
        'submitted_at', 'done_by', 'done_at',
    ];

    protected $casts = [
        'due_at'       => 'date',
        'submitted_at' => 'datetime',
        'done_at'      => 'datetime',
    ];

    public const STATUSES = [
        'pending'     => 'Назначено',
        'in_progress' => 'В работе',
        'submitted'   => 'На приёмке',
        'returned'    => 'Возвращена на доработку',
        'done'        => 'Принято',
    ];

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ProcedureChecklistEntry::class, 'procedure_checklist_entry_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProcedureTaskFile::class)->latest('id');
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'     => 'bg-gray-100 text-gray-600',
            'in_progress' => 'bg-blue-100 text-blue-700',
            'submitted'   => 'bg-amber-100 text-amber-700',
            'returned'    => 'bg-red-100 text-red-700',
            'done'        => 'bg-green-100 text-green-700',
            default       => 'bg-gray-100 text-gray-600',
        };
    }

    /** Исполнитель задачи (взять в работу, сдать на приёмку). */
    public function canAct(User $user): bool
    {
        return $this->assignee_id === $user->id || $user->isAdmin();
    }

    /** Инициатор процедуры (принять/вернуть сданную задачу). */
    public function canReview(User $user): bool
    {
        return $this->procedure->initiator_id === $user->id || $user->isAdmin();
    }
}
