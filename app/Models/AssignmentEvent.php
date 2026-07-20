<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentEvent extends Model
{
    protected $fillable = ['assignment_id', 'user_id', 'type', 'comment', 'meta'];

    protected $casts = ['meta' => 'array'];

    /** Подписи событий для таймлайна. */
    public const LABELS = [
        'created'                => 'Поручение создано',
        'started'                => 'Взято в работу',
        'submitted'              => 'Отчёт отправлен на приёмку',
        'accepted'               => 'Принято',
        'returned'               => 'Возвращено на доработку',
        'deadline_changed'       => 'Изменён срок',
        'deadline_requested'     => 'Запрошен перенос срока',
        'deadline_rejected'      => 'Перенос срока отклонён',
        'subassignment_created'  => 'Создано подпоручение',
        'contributed'            => 'Приложены материалы',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }
}
