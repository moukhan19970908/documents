<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureChecklistItem extends Model
{
    protected $fillable = [
        'procedure_template_id', 'position', 'department', 'title',
        'field_type', 'options', 'executor_mode', 'executor_user_id', 'spawns_task',
    ];

    protected $casts = [
        'options'     => 'array',
        'spawns_task' => 'boolean',
    ];

    /** Типы полей пункта чек-листа (ТЗ 19.2). */
    public const FIELD_TYPES = [
        'checkbox'     => 'Чекбокс',
        'boolean'      => 'Да/Нет',
        'boolean_text' => 'Да/Нет + текст',
        'select'       => 'Выпадающий список',
        'text'         => 'Текст',
    ];

    public const EXECUTOR_MODES = [
        'user'            => 'Конкретный пользователь',
        'department_head' => 'Руководитель отдела инициатора',
        'initiator'       => 'Инициатор',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_template_id');
    }

    public function executorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_user_id');
    }

    public function fieldTypeLabel(): string
    {
        return self::FIELD_TYPES[$this->field_type] ?? $this->field_type;
    }
}
