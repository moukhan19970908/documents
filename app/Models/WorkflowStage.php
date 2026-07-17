<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowStage extends Model
{
    // Мягкое удаление: звено, по которому уже шли согласования, убирается из
    // маршрута, но строка остаётся — на неё ссылается история (document_approval_stages).
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workflow_id', 'name', 'stage_type', 'sort_order', 'deadline_hours',
        'phase', 'resolver', 'group_department_id', 'group_department_ids', 'group_role', 'policy',
        'sla_days', 'is_blocking', 'on_reject',
        'condition_key', 'condition_operator', 'condition_value', 'branch_group',
    ];

    protected $casts = [
        'is_blocking'          => 'boolean',
        'group_department_ids' => 'array',
    ];

    public const PHASES = [
        'approval' => 'Фаза согласования',
        'approve'  => 'Фаза утверждения',
        'opinion'  => 'Фаза заключений',
        'ack'      => 'Фаза ознакомления',
        'intake'   => 'Фаза приёма',
        'branch'   => 'Развилка',
    ];

    public const ON_REJECT = [
        'return_initiator' => 'Вернуть инициатору',
        'reject'           => 'Отклонить документ',
    ];

    /**
     * Кнопки, доступные участнику звена. Набор задаётся видом звена — на экране документа
     * ничего не «додумывается».
     */
    public const ACTIONS = [
        'approval' => ['approve', 'reject', 'request_changes', 'delegate'],
        'approve'  => ['approve', 'reject', 'request_changes'],
        'opinion'  => ['opinion_yes', 'opinion_no'],
        'ack'      => ['acknowledge'],
        'intake'   => ['accept', 'execute'],
    ];

    public const ACTION_LABELS = [
        'approve'         => 'Согласовать',
        'reject'          => 'Отклонить',
        'request_changes' => 'Отправить на доработку',
        'delegate'        => 'Делегировать',
        'opinion_yes'     => 'Одобрить',
        'opinion_no'      => 'Не одобрить',
        'acknowledge'     => 'Ознакомлен',
        'accept'          => 'Принять к исполнению',
        'execute'         => 'Исполнено',
    ];

    public const OPERATORS = [
        '='            => 'равно',
        '!='           => 'не равно',
        '>'            => 'больше',
        '<'            => 'меньше',
        'contains'     => 'содержит',
        'not_contains' => 'не содержит',
    ];

    public function kind(): string
    {
        return $this->phase ?: 'approval';
    }

    /** Решения заключений и ознакомления не меняют судьбу документа — они только фиксируются. */
    public function isAdvisory(): bool
    {
        return in_array($this->kind(), ['opinion', 'ack'], true);
    }

    public function actions(): array
    {
        return self::ACTIONS[$this->kind()] ?? self::ACTIONS['approval'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(WorkflowStageApprover::class);
    }

    public function groupDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'group_department_id');
    }

    /** Ветки развилки: у каждой своё условие и свой набор согласующих. */
    public function branches(): HasMany
    {
        return $this->hasMany(WorkflowStageBranch::class)->orderBy('sort_order');
    }

    /**
     * Whether the stage joins the route for these launch answers.
     * Simple mode by design: one stage — one condition on one parameter.
     */
    public function passesCondition(array $parameterValues): bool
    {
        if (!$this->condition_key) {
            return true;
        }

        $actual = $parameterValues[$this->condition_key] ?? null;
        $expected = $this->condition_value;

        return self::evaluate($this->condition_operator, $actual, $expected);
    }

    public static function evaluate(?string $operator, $actual, $expected): bool
    {
        return match ($operator) {
            '!='           => (string) $actual !== (string) $expected,
            'in'           => in_array((string) $actual, array_map('trim', explode(',', (string) $expected)), true),
            '>'            => is_numeric($actual) && $actual > $expected,
            '<'            => is_numeric($actual) && $actual < $expected,
            'contains'     => $expected !== null && str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            'not_contains' => $expected !== null && !str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            default        => (string) $actual === (string) $expected,
        };
    }

    /**
     * Кому фактически уйдёт звено: явно назначенные участники плюс развёрнутая группа
     * (роль и/или отделы). Единственный источник правды — им пользуется и публикация
     * сценария (ScenarioPublisher), и предпросмотр маршрута в форме запуска, чтобы список
     * на экране совпадал с тем, что запустит движок.
     *
     * @return int[]
     */
    public function resolvedApproverIds(): array
    {
        $departmentIds = $this->group_department_ids ?: array_filter([$this->group_department_id]);

        // Параллельная группа: конкретные участники и целые отделы складываются.
        // Роль сужает только отделы — сам по себе список людей уже конкретен.
        $fromGroup = ($departmentIds || $this->group_role)
            ? self::usersOfGroup($departmentIds, $this->resolver === 'group' ? $this->group_role : null)
            : [];

        return array_values(array_unique(array_merge(
            $this->approvers->pluck('approver_id')->all(),
            $fromGroup,
        )));
    }

    /** Те же участники, что resolvedApproverIds(), но моделями — для показа в интерфейсе. */
    public function resolvedApprovers()
    {
        return User::whereIn('id', $this->resolvedApproverIds())
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'role_title', 'role']);
    }

    /**
     * Разворачивает группу (отделы + роль) в id пользователей.
     * Направление (корневой департамент) разворачивается во всё своё поддерево;
     * роль учитывает и основную (users.role), и назначенные через pivot.
     *
     * @param  int[]  $departmentIds
     * @return int[]
     */
    public static function usersOfGroup(array $departmentIds, ?string $role): array
    {
        $deptIds = [];
        foreach ($departmentIds as $id) {
            $deptIds = array_merge($deptIds, Department::getDescendantIds((int) $id));
        }
        $deptIds = array_values(array_unique($deptIds));

        return User::where('is_active', true)
            ->when($deptIds, fn ($q) => $q->whereIn('department_id', $deptIds))
            ->when($role, fn ($q) => $q->where(fn ($w) => $w
                ->where('role', $role)
                ->orWhereHas('roles', fn ($r) => $r->where('code', $role))))
            ->pluck('id')
            ->all();
    }
}
