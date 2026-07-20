<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProcedureChecklistEntry extends Model
{
    protected $fillable = [
        'procedure_id', 'procedure_checklist_item_id', 'source', 'position',
        'department', 'title', 'field_type', 'options', 'value', 'executor_id', 'spawns_task',
    ];

    protected $casts = [
        'options'     => 'array',
        'value'       => 'array',
        'spawns_task' => 'boolean',
    ];

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcedureChecklistItem::class, 'procedure_checklist_item_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function task(): HasOne
    {
        return $this->hasOne(ProcedureTask::class);
    }

    /**
     * Активен ли ответ (даёт основание породить задачу):
     * чекбокс/да-нет отмечены, список/текст заполнены.
     */
    public function isActive(): bool
    {
        $value = $this->value['answer'] ?? null;

        return match ($this->field_type) {
            'checkbox', 'boolean', 'boolean_text' => (bool) $value === true,
            'select', 'text'                      => filled($value),
            default                               => filled($value),
        };
    }

    /** Человекочитаемое представление ответа для карточки. */
    public function answerLabel(): string
    {
        $answer = $this->value['answer'] ?? null;
        $text   = $this->value['text'] ?? null;

        return match ($this->field_type) {
            'checkbox'     => $answer ? 'Отмечено' : '—',
            'boolean'      => $answer ? 'Да' : 'Нет',
            'boolean_text' => ($answer ? 'Да' : 'Нет') . ($text ? ' — ' . $text : ''),
            'select'       => (string) ($answer ?? '—'),
            'text'         => (string) ($answer ?? '—'),
            default        => (string) ($answer ?? '—'),
        };
    }
}
