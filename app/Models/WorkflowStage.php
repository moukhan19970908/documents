<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id', 'name', 'stage_type', 'sort_order', 'deadline_hours',
        'phase', 'resolver', 'group_department_id', 'group_role', 'policy',
        'sla_days', 'is_blocking', 'on_reject',
        'condition_key', 'condition_operator', 'condition_value',
    ];

    protected $casts = [
        'is_blocking' => 'boolean',
    ];

    public const PHASES = [
        'approval' => 'Фаза согласования',
        'approve'  => 'Фаза утверждения',
        'ack'      => 'Фаза ознакомления',
        'intake'   => 'Фаза приёма',
    ];

    public const ON_REJECT = [
        'return_initiator' => 'Вернуть инициатору',
        'reject'           => 'Отклонить документ',
    ];

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

        return match ($this->condition_operator) {
            '!='    => (string) $actual !== (string) $expected,
            'in'    => in_array((string) $actual, array_map('trim', explode(',', (string) $expected)), true),
            '>'     => is_numeric($actual) && $actual > $expected,
            '<'     => is_numeric($actual) && $actual < $expected,
            default => (string) $actual === (string) $expected,
        };
    }
}
