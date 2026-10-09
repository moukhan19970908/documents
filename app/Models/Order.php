<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Order extends Model
{
    protected $fillable = [
        'number', 'seq', 'document_subtype_id', 'title', 'body_html', 'blank_template_id',
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

    /**
     * Форматы загруженного файла, которые показываем прямо на странице приказа.
     * Тип отдаём свой, а не пришедший с файлом: загрузить можно что угодно, и HTML/SVG,
     * открытые inline, выполнились бы на нашем домене.
     */
    private const PREVIEW_MIMES = [
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** Виды приказа, из которых выбирает инициатор: активные подтипы типа «Приказ» в классификаторе. */
    public static function availableKinds(): Collection
    {
        return DocumentSubtype::where('is_active', true)
            ->whereHas('type', fn ($q) => $q->where('slug', DocumentType::ORDER_SLUG))
            ->orderBy('sort_order')
            ->pluck('name', 'id');
    }

    /** Вид приказа (ТЗ 16.1) — подтип типа «Приказ». */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(DocumentSubtype::class, 'document_subtype_id');
    }

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

    public function kindLabel(): ?string
    {
        return $this->kind?->name;
    }

    /**
     * Поля штампа-удостоверения опубликованного приказа (подпись => значение) — общие для
     * HTML-штампа на листе бланка и штампа, впечатываемого в загруженный файл.
     */
    public function certificateFields(): array
    {
        $issuer = $this->initiator;

        return [
            'Наименование'    => $this->title,
            'Дата публикации' => $this->published_at?->format('d.m.Y') ?? '—',
            'Приказ издан'    => $issuer ? $issuer->name . ($issuer->position ? " ({$issuer->position})" : '') : '—',
            'ID документа'    => $this->id . ($this->number ? ' · ' . $this->number : ''),
            'Подлинность'     => route('orders.show', $this->id),
        ];
    }

    /** MIME для предпросмотра загруженного файла; null — формат не показываем, только скачивание. */
    public function filePreviewMime(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        $extension = strtolower(pathinfo((string) $this->file_name, PATHINFO_EXTENSION));

        return self::PREVIEW_MIMES[$extension] ?? null;
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
