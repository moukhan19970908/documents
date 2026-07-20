<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureEvent extends Model
{
    protected $fillable = ['procedure_id', 'user_id', 'type', 'comment', 'meta'];

    protected $casts = ['meta' => 'array'];

    public const LABELS = [
        'created'               => 'Процедура создана',
        'stage_submitted'       => 'Этап пройден',
        'approved'              => 'Согласовано',
        'branched'              => 'Развилка пройдена',
        'returned'              => 'Возврат инициатору',
        'checklist_filled'      => 'Чек-лист заполнен',
        'tasks_generated'       => 'Задачи порождены',
        'task_submitted'        => 'Задача сдана на приёмку',
        'task_accepted'         => 'Задача принята',
        'task_returned'         => 'Задача возвращена на доработку',
        'task_deadline_changed' => 'Перенесён срок задачи',
        'stopped'               => 'Процедура остановлена',
        'completed'             => 'Процедура завершена',
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
