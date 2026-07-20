<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'number', 'seq', 'parent_id', 'root_id', 'depth',
        'title', 'body_html', 'initiator_id', 'executor_id', 'controller_id',
        'due_at', 'pending_due_at', 'pending_due_comment', 'status', 'is_mandatory',
        'result_comment', 'return_comment',
        'started_at', 'submitted_at', 'accepted_at', 'returned_at',
    ];

    protected $casts = [
        'due_at'         => 'date',
        'pending_due_at' => 'date',
        'is_mandatory'   => 'boolean',
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'returned_at'  => 'datetime',
    ];

    /** Статусы узла (ТЗ 17.4). */
    public const STATUSES = [
        'assigned'    => 'Назначено',
        'in_progress' => 'В работе',
        'submitted'   => 'На приёмке',
        'done'        => 'Выполнено',
        'returned'    => 'Возвращено на доработку',
    ];

    // ── связи ────────────────────────────────────────────────────────────
    public function parent(): BelongsTo    { return $this->belongsTo(self::class, 'parent_id'); }
    public function root(): BelongsTo       { return $this->belongsTo(self::class, 'root_id'); }
    public function children(): HasMany     { return $this->hasMany(self::class, 'parent_id')->orderBy('id'); }
    public function initiator(): BelongsTo  { return $this->belongsTo(User::class, 'initiator_id'); }
    public function executor(): BelongsTo   { return $this->belongsTo(User::class, 'executor_id'); }
    public function controller(): BelongsTo { return $this->belongsTo(User::class, 'controller_id'); }
    public function coExecutors(): BelongsToMany { return $this->belongsToMany(User::class, 'assignment_co_executors'); }
    public function files(): HasMany        { return $this->hasMany(AssignmentFile::class)->latest('id'); }
    public function events(): HasMany       { return $this->hasMany(AssignmentEvent::class)->latest('id'); }

    /** Файлы, загруженные прямо на узел (не подтянутые снизу). */
    public function ownFiles(): HasMany
    {
        return $this->files()->whereNull('source_assignment_id');
    }

    // ── статус ───────────────────────────────────────────────────────────
    public function isRoot(): bool     { return $this->parent_id === null; }
    public function isDone(): bool     { return $this->status === 'done'; }

    /** Просрочка — производная: срок прошёл, узел не выполнен. */
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
            'assigned'    => 'gray',
            'in_progress' => 'blue',
            'submitted'   => 'amber',
            'done'        => 'green',
            'returned'    => 'red',
            default       => 'gray',
        };
    }

    /** Обязательные дочерние узлы, ещё не закрытые: корень нельзя закрыть, пока они открыты. */
    public function openMandatoryChildren()
    {
        return $this->children()->where('is_mandatory', true)->where('status', '!=', 'done');
    }

    /** Исполнитель может отчитаться: узел в работе/возврате и все обязательные дети приняты. */
    public function canSubmit(): bool
    {
        return in_array($this->status, ['in_progress', 'returned'], true)
            && $this->openMandatoryChildren()->doesntExist();
    }

    /**
     * Послойная видимость (ТЗ 17.2):
     *   — постановщик корня видит ВСЁ дерево;
     *   — исполнитель видит свой узел;
     *   — постановщик узла видит подпоручения, которые создал сам.
     */
    public function visibleTo(User $user): bool
    {
        if ($user->isAdmin() || $user->hasMatrixPermission('assignments.view_all')) {
            return true;
        }

        // Постановщик и исполнитель узла, а также контролёр/соисполнители видят узел.
        if (in_array($user->id, [$this->initiator_id, $this->executor_id, $this->controller_id], true)) {
            return true;
        }

        if ($this->coExecutors()->whereKey($user->id)->exists()) {
            return true;
        }

        $rootInitiatorId = $this->root_id
            ? self::where('id', $this->root_id)->value('initiator_id')
            : $this->initiator_id;

        return $rootInitiatorId === $user->id;
    }

    /** Участник исполнения: основной исполнитель или соисполнитель. */
    public function isParticipant(User $user): bool
    {
        return $this->executor_id === $user->id
            || $this->coExecutors()->whereKey($user->id)->exists();
    }
}
