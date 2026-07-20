<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionEvent extends Model
{
    protected $fillable = ['inspection_id', 'user_id', 'type', 'comment', 'meta'];

    protected $casts = ['meta' => 'array'];

    public const LABELS = [
        'created'            => 'Проверка создана',
        'started'            => 'Взята в работу',
        'contributed'        => 'Приложены материалы',
        'subrequest_created' => 'Создан запрос данных',
        'submitted'          => 'Акт сдан на приёмку',
        'accepted'           => 'Акт принят',
        'returned'           => 'Возвращена на доработку',
        'assignment_spawned' => 'Порождено поручение по итогам',
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
