<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
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
        $scenario->load(['stages.approvers', 'parameters']);

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

            foreach ($scenario->stages as $stage) {
                $copy = $stage->replicate();
                $copy->workflow_id = $version->id;
                // «Любой из группы» completes on a single approve — that is exactly what the engine
                // does with a sequential stage; «все» is its parallel stage.
                $copy->stage_type = $stage->policy === 'any' ? 'sequential' : 'parallel';
                $copy->save();

                foreach ($this->resolveApprovers($stage) as $userId) {
                    WorkflowStageApprover::create([
                        'workflow_stage_id' => $copy->id,
                        'approver_type'     => 'user',
                        'approver_id'       => $userId,
                        'is_required'       => true,
                        'participant_type'  => 'signatory',
                    ]);
                }
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
            if (!$stage->condition_key) {
                continue;
            }

            $parameter = $parameters->get($stage->condition_key);

            if (!$parameter) {
                throw ValidationException::withMessages([
                    'stages' => "Звено «{$stage->name}»: условие ссылается на параметр «{$stage->condition_key}», которого нет.",
                ]);
            }

            $options = $parameter->options ?? [];

            if ($options && $stage->condition_operator === '=' && !in_array($stage->condition_value, $options, true)) {
                throw ValidationException::withMessages([
                    'stages' => "Звено «{$stage->name}»: у параметра «{$parameter->label}» нет варианта «{$stage->condition_value}».",
                ]);
            }
        }

        foreach ($scenario->stages as $stage) {
            if (empty($this->resolveApprovers($stage))) {
                throw ValidationException::withMessages([
                    'stages' => "Звено «{$stage->name}»: не назначен ни один исполнитель.",
                ]);
            }
        }
    }

    /** @return int[] user ids */
    private function resolveApprovers(WorkflowStage $stage): array
    {
        if ($stage->resolver === 'group') {
            if (!$stage->group_department_id && !$stage->group_role) {
                return [];   // an unbound group would resolve to the whole company
            }

            return User::where('is_active', true)
                ->when($stage->group_department_id, fn ($q) => $q->where('department_id', $stage->group_department_id))
                ->when($stage->group_role, fn ($q) => $q->where('role', $stage->group_role))
                ->pluck('id')
                ->all();
        }

        return $stage->approvers->pluck('approver_id')->all();
    }

    private function nextVersionLabel(Workflow $scenario): string
    {
        $count = $scenario->versions()->count();

        return '1.' . $count;
    }
}
