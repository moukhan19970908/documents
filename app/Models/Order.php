<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'number', 'seq', 'kind', 'title', 'body_html', 'blank_template_id',
        'file_path', 'file_name', 'initiator_id', 'effective_at', 'ack_deadline',
        'audience', 'requires_approval', 'status', 'published_at',
    ];

    protected $casts = [
        'audience'          => 'array',
        'requires_approval' => 'boolean',
        'effective_at'      => 'date',
        'ack_deadline'      => 'date',
        'published_at'      => 'datetime',
    ];

    /** Виды приказа (подтипы из ТЗ 16.1). */
    public const KINDS = [
        'operational' => 'По основной деятельности',
        'personnel'   => 'Кадровый',
        'approval'    => 'Об утверждении',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function blank(): BelongsTo
    {
        return $this->belongsTo(BlankTemplate::class, 'blank_template_id');
    }

    public function acknowledgments(): HasMany
    {
        return $this->hasMany(OrderAcknowledgment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(OrderApproval::class)->orderBy('position');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Удалять приказ может админ (любой) или издающий его инициатор (свой, в любом статусе). */
    public function canBeDeletedBy(User $user): bool
    {
        return $user->isAdmin() || $this->initiator_id === $user->id;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /** Тело бланка с подставленными токенами {номер} и {дата}. */
    public function renderedBody(): string
    {
        return strtr($this->body_html ?? '', [
            '{номер}' => $this->seq ? (string) $this->seq : '___',
            '{дата}'  => $this->effective_at?->format('d.m.Y') ?? '__.__.____',
        ]);
    }

    public function recipientsCount(): int
    {
        return $this->acknowledgments()->count();
    }

    public function acknowledgedCount(): int
    {
        return $this->acknowledgments()->whereNotNull('acknowledged_at')->count();
    }

    public function progressPercent(): int
    {
        $total = $this->recipientsCount();
        return $total === 0 ? 0 : (int) round($this->acknowledgedCount() / $total * 100);
    }

    /** Ознакомление полностью завершено (есть адресаты и все ознакомились). */
    public function ackCompleted(): bool
    {
        $total = $this->recipientsCount();
        return $total > 0 && $this->acknowledgedCount() === $total;
    }

    /** Просрочка ознакомления: срок прошёл и не все ознакомились. */
    public function ackOverdue(): bool
    {
        return $this->isPublished()
            && $this->recipientsCount() > 0
            && $this->ack_deadline !== null
            && $this->ack_deadline->isPast()
            && ! $this->ackCompleted();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'draft'       => 'Черновик',
            $this->status === 'on_approval' => 'На согласовании',
            $this->isPublished() && $this->ackCompleted() => 'Ознакомление завершено',
            $this->isPublished()            => 'Опубликован',
            default                         => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match (true) {
            $this->status === 'draft'       => 'gray',
            $this->status === 'on_approval' => 'blue',
            default                         => 'green',
        };
    }
}
