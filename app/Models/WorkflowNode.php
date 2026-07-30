<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Узел маршрута-графа. Узлы стоят в цепочках: цепочка сценария (parent_id = null,
 * branch = main) и цепочки выходов ветвящегося узла (branch = yes|no).
 *
 * Настройки узла лежат в config — у каждого типа свой набор, поэтому отдельных
 * колонок под них нет.
 */
class WorkflowNode extends Model
{
    protected $fillable = ['workflow_id', 'parent_id', 'branch', 'sort_order', 'type', 'name', 'config'];

    protected $casts = ['config' => 'array'];

    /**
     * Типы узлов и их поведение:
     *  task      — ставит задачи участникам и ждёт их решения;
     *  branching — у узла два выхода, «Да» и «Нет»;
     *  group     — раздел палитры в конструкторе.
     */
    public const TYPES = [
        'approval'  => ['label' => 'Согласование документа', 'group' => 'tasks',    'task' => true,  'branching' => true],
        'approve'   => ['label' => 'Утверждение документа',  'group' => 'tasks',    'task' => true,  'branching' => true],
        'opinion'   => ['label' => 'Заключение',             'group' => 'tasks',    'task' => true,  'branching' => false],
        'ack'       => ['label' => 'Ознакомление с документом', 'group' => 'tasks', 'task' => true,  'branching' => false],
        'intake'    => ['label' => 'Приём к исполнению',     'group' => 'tasks',    'task' => true,  'branching' => false],
        'status'    => ['label' => 'Статус документа',       'group' => 'document', 'task' => false, 'branching' => false],
        'notify'    => ['label' => 'Почтовое сообщение',     'group' => 'document', 'task' => false, 'branching' => false],
        'condition' => ['label' => 'Условие',                'group' => 'flow',     'task' => false, 'branching' => true],
        'end'       => ['label' => 'Завершение процесса',    'group' => 'flow',     'task' => false, 'branching' => false],
    ];

    public const GROUPS = [
        'document' => 'Обработка документа',
        'tasks'    => 'Задания',
        'flow'     => 'Конструкции',
    ];

    /** Статусы, в которые узел «Статус документа» может перевести документ. */
    public const STATUSES = [
        'in_review'        => 'На согласовании',
        'requires_changes' => 'Отправлен на доработку',
        'approved'         => 'Согласован',
        'rejected'         => 'Отклонён',
        'signed'           => 'Подписан',
    ];

    /** Чем завершается процесс в узле «Завершение». */
    public const RESULTS = [
        'approved'         => 'Согласован',
        'rejected'         => 'Отклонён',
        'requires_changes' => 'Возвращён на доработку',
    ];

    /**
     * По чему ветвится условие. Параметр — ответ инициатора при запуске;
     * отдел инициатора — его место в оргструктуре, вопросов при запуске не задаётся.
     */
    public const CONDITION_SOURCES = [
        'parameter'            => 'Параметр запуска',
        'initiator_department' => 'Отдел инициатора',
    ];

    /** Операторы условия по отделу. */
    public const DEPARTMENT_OPERATORS = [
        'in'     => 'принадлежит',
        'not_in' => 'не принадлежит',
    ];

    /** Кому уходит почтовое сообщение. */
    public const RECIPIENTS = [
        'initiator'    => 'Инициатору',
        'participants' => 'Участникам предыдущего звена',
        'users'        => 'Выбранным сотрудникам',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function meta(): array
    {
        return self::TYPES[$this->type] ?? self::TYPES['approval'];
    }

    public function isTask(): bool
    {
        return (bool) $this->meta()['task'];
    }

    public function isBranching(): bool
    {
        return (bool) $this->meta()['branching'];
    }

    public function typeLabel(): string
    {
        return $this->meta()['label'];
    }

    public function cfg(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /** Решения заключения и ознакомления фиксируются, но судьбу документа не решают. */
    public function isAdvisory(): bool
    {
        return in_array($this->type, ['opinion', 'ack'], true);
    }

    /**
     * Проверка условия узла «Условие»: по ответу на параметр запуска либо по тому,
     * в каком отделе работает инициатор.
     */
    public function passesCondition(array $parameterValues, ?User $initiator = null): bool
    {
        if ($this->cfg('source', 'parameter') === 'initiator_department') {
            return $this->initiatorBelongsToDepartments($initiator);
        }

        $key = $this->cfg('condition_key');

        if (! $key) {
            return true;
        }

        return WorkflowStage::evaluate(
            $this->cfg('condition_operator', '='),
            $parameterValues[$key] ?? null,
            $this->cfg('condition_value'),
        );
    }

    /**
     * Отдел инициатора внутри выбранных подразделений. Направление охватывает всё
     * своё поддерево — так же, как при разворачивании групп в исполнителей.
     */
    private function initiatorBelongsToDepartments(?User $initiator): bool
    {
        $selected = array_map('intval', $this->cfg('department_ids', []) ?: []);

        if (! $selected) {
            return true;   // отделы не выбраны — условию нечего проверять
        }

        $scope = [];
        foreach ($selected as $id) {
            $scope = array_merge($scope, Department::getDescendantIds($id));
        }

        $belongs = $initiator?->department_id
            && in_array((int) $initiator->department_id, array_map('intval', $scope), true);

        return $this->cfg('condition_operator', 'in') === 'not_in' ? ! $belongs : $belongs;
    }

    /**
     * Кому уйдёт звено: явно выбранные сотрудники плюс развёрнутая группа
     * (направления и/или роль). Правила разворачивания — общие со звеньями,
     * поэтому предпросмотр маршрута и движок дают один и тот же состав.
     *
     * @return int[]
     */
    public function resolvedApproverIds(): array
    {
        $departmentIds = $this->cfg('group_department_ids', []) ?: [];
        $role = $this->cfg('resolver') === 'group' ? $this->cfg('group_role') : null;

        $fromGroup = ($departmentIds || $role)
            ? WorkflowStage::usersOfGroup(array_map('intval', $departmentIds), $role ?: null)
            : [];

        return array_values(array_unique(array_merge(
            array_map('intval', $this->cfg('approver_ids', []) ?: []),
            $fromGroup,
        )));
    }

    public function resolvedApprovers()
    {
        return User::whereIn('id', $this->resolvedApproverIds())
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'role_title', 'role']);
    }
}
