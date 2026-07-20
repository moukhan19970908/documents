<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureStage extends Model
{
    protected $fillable = [
        'procedure_template_id', 'position', 'type', 'title',
        'executor_mode', 'executor_role', 'executor_user_id',
        'require_attachments', 'config',
    ];

    protected $casts = [
        'require_attachments' => 'boolean',
        'config'              => 'array',
    ];

    /** Типы этапов сценария (ТЗ 19). */
    public const TYPES = [
        'form'                => 'Форма (инициатор)',
        'approval'            => 'Согласование',
        'branch'              => 'Развилка',
        'return_to_initiator' => 'Возврат инициатору',
        'checklist'           => 'Чек-лист',
        'fanout'              => 'Порождение задач',
        'completion'          => 'Завершение',
    ];

    /** Этапы, которые движок проходит автоматически, не требуя действия исполнителя. */
    public const AUTO_TYPES = ['return_to_initiator', 'fanout', 'completion'];

    public const EXECUTOR_MODES = [
        'initiator' => 'Инициатор',
        'user'      => 'Конкретный пользователь',
        'role'      => 'Роль',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_template_id');
    }

    public function executorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_user_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isAuto(): bool
    {
        return in_array($this->type, self::AUTO_TYPES, true);
    }
}
