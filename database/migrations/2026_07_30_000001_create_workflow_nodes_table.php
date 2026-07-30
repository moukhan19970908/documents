<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Маршрут сценария как граф: узлы вместо плоского списка звеньев.
 *
 * Узел живёт в цепочке (parent_id + branch + sort_order). У ветвящегося узла
 * (условие, согласование, утверждение) есть выходы «Да» и «Нет» — это дочерние
 * цепочки. Когда ветка заканчивается, исполнение возвращается к узлу, следующему
 * за ветвящимся: так одним механизмом выражается и условное включение звена,
 * и расходящиеся цепочки действий, как в конструкторе Битрикс24.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('workflow_nodes')->cascadeOnDelete();
            $table->enum('branch', ['main', 'yes', 'no'])->default('main');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type', 32);
            $table->string('name');
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'parent_id', 'branch', 'sort_order'], 'workflow_nodes_chain_index');
        });

        $this->convertExistingRoutes();
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_nodes');
    }

    /**
     * Переносит маршруты действующих шаблонов в узлы, чтобы новый редактор открыл
     * их не пустыми. Версии не трогаем: они уже опубликованы и продолжают
     * исполняться по своим звеньям.
     */
    private function convertExistingRoutes(): void
    {
        $workflowIds = DB::table('workflows')->where('is_version', false)->pluck('id');

        foreach ($workflowIds as $workflowId) {
            $stages = DB::table('workflow_stages')
                ->where('workflow_id', $workflowId)
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->get();

            if ($stages->isEmpty()) {
                continue;
            }

            $order = 0;

            foreach ($stages as $stage) {
                if (($stage->phase ?: 'approval') === 'branch') {
                    $this->convertBranchStage($workflowId, $stage, null, 'main', $order++);
                    continue;
                }

                $taskConfig = $this->taskConfig($stage);

                // Условное звено — это условие с одним выходом «Да»: если условие не
                // выполнено, маршрут просто идёт дальше, ровно как раньше.
                if ($stage->condition_key) {
                    $conditionId = $this->insert($workflowId, null, 'main', $order++, 'condition', 'Условие', [
                        'condition_key'      => $stage->condition_key,
                        'condition_operator' => $stage->condition_operator ?: '=',
                        'condition_value'    => $stage->condition_value,
                    ]);

                    $this->insert($workflowId, $conditionId, 'yes', 0, $stage->phase ?: 'approval', $stage->name, $taskConfig);
                    continue;
                }

                $this->insert($workflowId, null, 'main', $order++, $stage->phase ?: 'approval', $stage->name, $taskConfig);
            }
        }
    }

    /**
     * Развилка со списком веток разворачивается в лесенку условий:
     * первая подходящая ветка срабатывает — ровно так её исполнял старый движок.
     */
    private function convertBranchStage(int $workflowId, object $stage, ?int $parentId, string $branch, int $order): void
    {
        $branches = DB::table('workflow_stage_branches')
            ->where('workflow_stage_id', $stage->id)
            ->orderBy('sort_order')
            ->get()
            ->values();

        if ($branches->isEmpty()) {
            return;
        }

        $current = ['parent' => $parentId, 'branch' => $branch, 'order' => $order];

        foreach ($branches as $b) {
            $config = [
                'resolver'             => 'user',
                'approver_ids'         => json_decode($b->approver_ids ?? '[]', true) ?: [],
                'group_department_ids' => json_decode($b->department_ids ?? '[]', true) ?: [],
                'group_role'           => null,
                'policy'               => $b->policy ?: 'all',
                'sla_days'             => $stage->sla_days,
                'is_blocking'          => true,
                'on_reject'            => $stage->on_reject ?: 'return_initiator',
            ];

            // Ветка без условия — это «иначе»: отдельный узел-условие ей не нужен.
            if (! $b->condition_key) {
                $this->insert($workflowId, $current['parent'], $current['branch'], $current['order'],
                    'approval', $b->name ?: $stage->name, $config);
                return;
            }

            $conditionId = $this->insert($workflowId, $current['parent'], $current['branch'], $current['order'],
                'condition', $b->name ?: 'Условие', [
                    'condition_key'      => $b->condition_key,
                    'condition_operator' => $b->condition_operator ?: '=',
                    'condition_value'    => $b->condition_value,
                ]);

            $this->insert($workflowId, $conditionId, 'yes', 0, 'approval', $b->name ?: $stage->name, $config);

            // Следующая ветка проверяется в выходе «Нет» текущего условия.
            $current = ['parent' => $conditionId, 'branch' => 'no', 'order' => 0];
        }
    }

    private function taskConfig(object $stage): array
    {
        return [
            'resolver'             => $stage->resolver ?: 'user',
            'approver_ids'         => DB::table('workflow_stage_approvers')
                ->where('workflow_stage_id', $stage->id)
                ->pluck('approver_id')->map('intval')->all(),
            'group_department_ids' => json_decode($stage->group_department_ids ?? '[]', true)
                ?: array_filter([(int) $stage->group_department_id]),
            'group_role'           => $stage->group_role,
            'policy'               => $stage->policy ?: 'all',
            'sla_days'             => $stage->sla_days,
            'is_blocking'          => (bool) $stage->is_blocking,
            'on_reject'            => $stage->on_reject ?: 'return_initiator',
        ];
    }

    private function insert(int $workflowId, ?int $parentId, string $branch, int $order, string $type, ?string $name, array $config): int
    {
        return DB::table('workflow_nodes')->insertGetId([
            'workflow_id' => $workflowId,
            'parent_id'   => $parentId,
            'branch'      => $branch,
            'sort_order'  => $order,
            'type'        => $type,
            'name'        => $name ?: 'Без названия',
            'config'      => json_encode($config, JSON_UNESCAPED_UNICODE),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
};
