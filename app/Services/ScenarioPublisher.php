<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
use App\Models\WorkflowStageBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishing freezes the scenario into an immutable version copy. Running processes point at the
 * version they started with, so editing the template never rewrites a route mid-flight.
 *
 * Group resolvers are expanded into concrete users right here — that keeps the approval engine
 * unchanged: it still reads plain user approvers off a stage.
 */
class ScenarioPublisher
{
    public function __construct(private AuditService $auditService) {}

    public function publish(Workflow $scenario): Workflow
    {
        $scenario->load(['stages.approvers', 'stages.branches', 'parameters']);

        $this->assertPublishable($scenario);

        $version = DB::transaction(function () use ($scenario) {
            $version = $scenario->replicate(['parent_workflow_id', 'version_label', 'published_at']);
            $version->is_version     = true;
            $version->parent_workflow_id = $scenario->id;
            $version->version_label  = $this->nextVersionLabel($scenario);
            $version->published_at   = now();
            $version->is_active      = false;   // a version is never offered for launch on its own
            $version->status         = 'published';
            $version->save();

            $order = 0;

            foreach ($scenario->stages as $stage) {
                // Развилка — не звено, а контейнер: каждая ветка становится условным звеном
                // одной группы, и движок выберет первую подходящую по ответам сотрудника.
                if ($stage->kind() === 'branch') {
                    foreach ($stage->branches as $branch) {
                        $this->copyStage($version, $stage, $order++, [
                            'name'               => $branch->name ?: $stage->name,
                            'phase'              => 'approval',
                            'policy'             => $branch->policy,
                            'condition_key'      => $branch->condition_key,
                            'condition_operator' => $branch->condition_operator,
                            'condition_value'    => $branch->condition_value,
                            'branch_group'       => 'branch-' . $stage->id,
                        ], $this->resolveBranchApprovers($branch));
                    }
                    continue;
                }

                $this->copyStage($version, $stage, $order++, [], $this->resolveApprovers($stage));
            }

            $scenario->update([
                'status'       => 'published',
                'is_active'    => true,
                'published_at' => now(),
            ]);

            return $version;
        });

        $this->auditService->log('scenario_published', $scenario, null, [
            'version_id'    => $version->id,
            'version_label' => $version->version_label,
        ]);

        return $version;
    }

    /** @param int[] $approverIds */
    private function copyStage(Workflow $version, WorkflowStage $stage, int $order, array $overrides, array $approverIds): void
    {
        $copy = $stage->replicate();
        $copy->workflow_id = $version->id;
        $copy->sort_order  = $order;

        foreach ($overrides as $field => $value) {
            $copy->{$field} = $value;
        }

        // «Любой из группы» завершается одним решением — это ровно sequential-этап движка;
        // «все участники» — его parallel-этап.
        $copy->stage_type = ($overrides['policy'] ?? $stage->policy) === 'any' ? 'sequential' : 'parallel';
        $copy->save();

        foreach ($approverIds as $userId) {
            WorkflowStageApprover::create([
                'workflow_stage_id' => $copy->id,
                'approver_type'     => 'user',
                'approver_id'       => $userId,
                'is_required'       => true,
                'participant_type'  => 'signatory',
            ]);
        }
    }

    /** Conditions must point at a parameter that exists and at a value it can actually take. */
    private function assertPublishable(Workflow $scenario): void
    {
        if ($scenario->stages->isEmpty()) {
            throw ValidationException::withMessages([
                'stages' => 'В сценарии нет ни одного звена — публиковать нечего.',
            ]);
        }

        $parameters = $scenario->parameters->keyBy('key');

        foreach ($scenario->stages as $stage) {
            if ($stage->kind() === 'branch') {
                if ($stage->branches->isEmpty()) {
                    throw ValidationException::withMessages([
                        'stages' => "Развилка «{$stage->name}»: не задано ни одной ветки.",
                    ]);
                }

                foreach ($stage->branches as $branch) {
                    $this->assertCondition($parameters, $branch->condition_key, $branch->condition_operator,
                        $branch->condition_value, "Развилка «{$stage->name}», ветка «{$branch->name}»");

                    if (empty($this->resolveBranchApprovers($branch))) {
                        throw ValidationException::withMessages([
                            'stages' => "Развилка «{$stage->name}», ветка «{$branch->name}»: не назначен ни один согласующий.",
                        ]);
                    }
                }

                continue;
            }

            $this->assertCondition($parameters, $stage->condition_key, $stage->condition_operator,
                $stage->condition_value, "Звено «{$stage->name}»");

            if (empty($this->resolveApprovers($stage))) {
                throw ValidationException::withMessages([
                    'stages' => "Звено «{$stage->name}»: не назначен ни один исполнитель.",
                ]);
            }
        }
    }

    private function assertCondition($parameters, ?string $key, ?string $operator, ?string $value, string $where): void
    {
        if (!$key) {
            return;
        }

        $parameter = $parameters->get($key);

        if (!$parameter) {
            throw ValidationException::withMessages([
                'stages' => "{$where}: условие ссылается на параметр «{$key}», которого нет.",
            ]);
        }

        $options = $parameter->options ?? [];

        if ($options && $operator === '=' && !in_array($value, $options, true)) {
            throw ValidationException::withMessages([
                'stages' => "{$where}: у параметра «{$parameter->label}» нет варианта «{$value}».",
            ]);
        }
    }

    /** @return int[] user ids */
    private function resolveApprovers(WorkflowStage $stage): array
    {
        $departmentIds = $stage->group_department_ids
            ?: array_filter([$stage->group_department_id]);

        // Параллельная группа: конкретные участники и целые отделы складываются.
        // Роль сужает только отделы — сам по себе список людей уже конкретен.
        $fromGroup = ($departmentIds || $stage->group_role)
            ? $this->usersOf($departmentIds, $stage->resolver === 'group' ? $stage->group_role : null)
            : [];

        return array_values(array_unique(array_merge(
            $stage->approvers->pluck('approver_id')->all(),
            $fromGroup,
        )));
    }

    /** @return int[] user ids */
    private function resolveBranchApprovers(WorkflowStageBranch $branch): array
    {
        return array_values(array_unique(array_merge(
            $branch->approver_ids ?? [],
            $branch->department_ids ? $this->usersOf($branch->department_ids, null) : [],
        )));
    }

    /** @return int[] user ids */
    private function usersOf(array $departmentIds, ?string $role): array
    {
        return User::where('is_active', true)
            ->when($departmentIds, fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->when($role, fn ($q) => $q->where('role', $role))
            ->pluck('id')
            ->all();
    }

    private function nextVersionLabel(Workflow $scenario): string
    {
        $count = $scenario->versions()->count();

        return '1.' . $count;
    }
}
