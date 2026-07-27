<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Обращение обратной связи (ТЗ 29). */
class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = ['user_id', 'category', 'subject', 'body', 'status'];

    /** Категории обращения. */
    public const CATEGORIES = [
        'bug'      => 'Ошибка',
        'wish'     => 'Пожелание',
        'question' => 'Вопрос',
    ];

    /** Статусы: Новое → В работе → Отвечено → Закрыто. */
    public const STATUSES = [
        'new'         => 'Новое',
        'in_progress' => 'В работе',
        'answered'    => 'Отвечено',
        'closed'      => 'Закрыто',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FeedbackMessage::class)->oldest('id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'new'         => 'blue',
            'in_progress' => 'amber',
            'answered'    => 'violet',
            'closed'      => 'gray',
            default       => 'gray',
        };
    }

    public function categoryColor(): string
    {
        return match ($this->category) {
            'bug'      => 'red',
            'wish'     => 'emerald',
            'question' => 'blue',
            default    => 'gray',
        };
    }
}
