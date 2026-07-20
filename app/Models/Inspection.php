<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    protected $fillable = [
        'number', 'seq', 'parent_id', 'root_id', 'depth',
        'title', 'body_html', 'initiator_id', 'executor_id',
        'object_type', 'object_id', 'object_label', 'period_from', 'period_to', 'kind',
        'due_at', 'status', 'is_mandatory', 'result_comment', 'return_comment',
        'act_verdict', 'act_violations',
        'started_at', 'submitted_at', 'accepted_at', 'returned_at',
    ];

    protected $casts = [
        'period_from'  => 'date',
        'period_to'    => 'date',
        'due_at'       => 'date',
        'is_mandatory' => 'boolean',
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'returned_at'  => 'datetime',
    ];

    public const STATUSES = [
        'assigned'    => 'Назначена',
        'in_progress' => 'В работе',
        'submitted'   => 'На приёмке',
        'done'        => 'Принята',
        'returned'    => 'Возвращена на доработку',
    ];

    public const OBJECT_TYPES = [
        'department' => 'Отдел',
        'direction'  => 'Направление',
        'employee'   => 'Сотрудник',
    ];

    public const KINDS = [
        'planned'   => 'Плановая',
        'unplanned' => 'Внеплановая',
    ];

    public const VERDICTS = [
        'not_found' => 'Нарушений не выявлено',
        'found'     => 'Выявлены нарушения',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function root(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(InspectionFile::class)->latest('id');
    }

    public function ownFiles(): HasMany
    {
        return $this->hasMany(InspectionFile::class)->whereNull('source_inspection_id')->latest('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InspectionEvent::class)->latest('id');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->status !== 'done' && $this->due_at->isPast();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'assigned'    => 'bg-gray-100 text-gray-600',
            'in_progress' => 'bg-blue-100 text-blue-700',
            'submitted'   => 'bg-amber-100 text-amber-700',
            'done'        => 'bg-green-100 text-green-700',
            'returned'    => 'bg-red-100 text-red-700',
            default       => 'bg-gray-100 text-gray-600',
        };
    }

    public function objectTypeLabel(): ?string
    {
        return $this->object_type ? (self::OBJECT_TYPES[$this->object_type] ?? $this->object_type) : null;
    }

    public function kindLabel(): ?string
    {
        return $this->kind ? (self::KINDS[$this->kind] ?? $this->kind) : null;
    }

    public function verdictLabel(): ?string
    {
        return $this->act_verdict ? (self::VERDICTS[$this->act_verdict] ?? $this->act_verdict) : null;
    }

    /** Обязательные незакрытые подпроверки — блокируют сдачу родителя. */
    public function openMandatoryChildren()
    {
        return $this->children()->where('is_mandatory', true)->where('status', '!=', 'done');
    }

    /** Послойная видимость: admin/view_all — всё; участники узла; постановщик корня — всё дерево. */
    public function visibleTo(User $user): bool
    {
        if ($user->isAdmin() || $user->hasMatrixPermission('inspections.view_all')) {
            return true;
        }
        if (in_array($user->id, [$this->initiator_id, $this->executor_id], true)) {
            return true;
        }

        return $this->root && $this->root->initiator_id === $user->id;
    }

    public function isParticipant(User $user): bool
    {
        return $this->executor_id === $user->id;
    }
}
