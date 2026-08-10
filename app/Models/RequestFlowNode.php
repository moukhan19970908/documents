<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Узел граф-процесса заявки. Узлы стоят цепочками: корневая (parent_id=null, branch=main)
 * и цепочки веток ветвящегося узла (branch = ключ ветки из config родителя).
 */
class RequestFlowNode extends Model
{
    protected $fillable = ['flow_id', 'parent_id', 'branch', 'sort_order', 'type', 'name', 'config'];

    protected $casts = ['config' => 'array'];

    /**
     * Каталог узлов конструктора заявок (палитра). branching — у узла N именованных веток.
     * group — раздел палитры.
     */
    public const TYPES = [
        // Согласование
        'approver_role' => ['label' => 'Согласующий (роль)',            'group' => 'approval', 'branching' => false],
        'approver_org'  => ['label' => 'Согласующий (по оргструктуре)', 'group' => 'approval', 'branching' => false],
        'registry'      => ['label' => 'Массовое согласование (реестр)', 'group' => 'approval', 'branching' => false],
        // Условия
        'cond_field'    => ['label' => 'Условие по полю',               'group' => 'flow',     'branching' => true],
        'cond_role'     => ['label' => 'Условие по инициатору',         'group' => 'flow',     'branching' => true],
        'parallel'      => ['label' => 'Параллельная ветка',            'group' => 'flow',     'branching' => true],
        // Задачи
        'task'          => ['label' => 'Задание исполнителю',           'group' => 'tasks',    'branching' => false],
        'notify'        => ['label' => 'Уведомление',                   'group' => 'tasks',    'branching' => false],
        'auto'          => ['label' => 'Автоматическое действие',        'group' => 'tasks',    'branching' => false],
        // Завершение
        'success'       => ['label' => 'Успешное завершение',           'group' => 'end',      'branching' => false],
        'reject'        => ['label' => 'Отклонение',                    'group' => 'end',      'branching' => false],
    ];

    public const GROUPS = [
        'approval' => 'Согласование',
        'flow'     => 'Условия',
        'tasks'    => 'Задачи',
        'end'      => 'Завершение',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(RequestFlow::class, 'flow_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function meta(): array
    {
        return self::TYPES[$this->type] ?? [];
    }

    public function isBranching(): bool
    {
        return (bool) ($this->meta()['branching'] ?? false);
    }

    public function cfg(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}
