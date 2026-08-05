<?php

namespace App\Services;

use App\Models\DocumentApproval;
use App\Models\DocumentApprovalStage;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowNode;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
use Illuminate\Support\Facades\DB;

/**
 * Маршрут-граф: сохранение из конструктора, копирование в версию при публикации
 * и обход при исполнении.
 *
 * Граф — источник правды. Плоский список звеньев (workflow_stages) остаётся его
 * скомпилированным следом: по нему живут предпросмотр маршрута в форме запуска,
 * счётчики и карточка сценария, которым граф целиком не нужен.
 */
class RouteGraphService
{
    /** Глубина вложенности веток, дальше которой конструктор не пускает. */
    private const MAX_DEPTH = 20;

    /**
     * Переписывает маршрут сценария по дереву из конструктора.
     *
     * @param array $tree корневая цепочка: [['type' =>, 'name' =>, 'config' => [], 'yes' => [], 'no' => []], ...]
     */
    public function save(Workflow $scenario, array $tree): void
    {
        DB::transaction(function () use ($scenario, $tree) {
            $scenario->nodes()->whereNull('parent_id')->delete();   // дети уходят каскадом
            $this->insertChain($scenario, $tree, null, 'main', 0);

            $this->compileStages($scenario->refresh());

            // Правка маршрута — снова черновик: чтобы изменения поехали в работу,
            // сценарий надо опубликовать.
            $scenario->update(['engine_version' => 3]);
        });
    }

    /** Копирует граф в версию при публикации — версия неизменяема и живёт своей жизнью. */
    public function copyTo(Workflow $source, Workflow $target): void
    {
        $this->copyChain($source->nodes()->whereNull('parent_id')->get(), $target, null);
    }

    /**
     * Первый узел маршрута — точка входа сразу за «Началом».
     */
    public function firstNode(Workflow $definition): ?WorkflowNode
    {
        return $definition->nodes()->whereNull('parent_id')->where('branch', 'main')->first();
    }

    /**
     * Следующий узел после $node при исходе $branch ('yes' | 'no').
     *
     * Сначала — цепочка нужного выхода. Если она пуста, идём к следующему узлу
     * этой же цепочки; если и цепочка кончилась — поднимаемся к родителю: ветка
     * возвращает исполнение туда, откуда разошлась.
     */
    public function nextNode(WorkflowNode $node, string $branch = 'yes'): ?WorkflowNode
    {
        if ($node->isBranching()) {
            $child = $node->children()->where('branch', $branch)->first();

            if ($child) {
                return $child;
            }
        }

        return $this->nodeAfter($node);
    }

    /** Следующий узел той же цепочки, а если она кончилась — следующий за родителем. */
    private function nodeAfter(WorkflowNode $node): ?WorkflowNode
    {
        $sibling = WorkflowNode::where('workflow_id', $node->workflow_id)
            ->where('parent_id', $node->parent_id)
            ->where('branch', $node->branch)
            ->where(fn ($q) => $q
                ->where('sort_order', '>', $node->sort_order)
                ->orWhere(fn ($w) => $w->where('sort_order', $node->sort_order)->where('id', '>', $node->id)))
            ->orderBy('sort_order')->orderBy('id')
            ->first();

        if ($sibling) {
            return $sibling;
        }

        return $node->parent ? $this->nodeAfter($node->parent) : null;
    }

    /**
     * Ещё не пройденные звенья идущего маршрута-графа — чтобы карточка документа
     * показывала весь план, а не только материализованное на текущий момент.
     *
     * Идём тем же happy-path, что и движок: за задачей — её выход «Да», условие —
     * по ответам инициатора. Уже пройденные и пустые (без исполнителей — движок их
     * пропускает) узлы в план не попадают.
     *
     * @return WorkflowNode[]
     */
    public function plannedNodes(DocumentApproval $approval, ?User $initiator = null): array
    {
        $graphId = $approval->runtime_data['graph_workflow_id'] ?? null;

        if (! $graphId || ! ($definition = Workflow::find($graphId))) {
            return [];
        }

        $done = $approval->stages->pluck('workflow_node_id')->filter()
            ->map(fn ($id) => (int) $id)->all();
        $params = $approval->parameter_values ?? [];
        $initiator ??= $approval->document?->initiator;

        $planned = [];
        $node = $this->firstNode($definition);
        $steps = 0;

        // Тот же предохранитель от кольца, что и в движке.
        while ($node && ++$steps <= 200) {
            if ($node->isTask()) {
                if (! in_array($node->id, $done, true) && $node->resolvedApproverIds()) {
                    $planned[] = $node;
                }

                $node = $this->nextNode($node, 'yes');
                continue;
            }

            $node = match ($node->type) {
                'condition' => $this->nextNode($node, $node->passesCondition($params, $initiator) ? 'yes' : 'no'),
                'end'       => null,
                default     => $this->nextNode($node),
            };
        }

        return $planned;
    }

    /** @param array $chain узлы одной цепочки */
    private function insertChain(Workflow $scenario, array $chain, ?int $parentId, string $branch, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        foreach (array_values($chain) as $i => $raw) {
            $type = (string) ($raw['type'] ?? '');

            if (! isset(WorkflowNode::TYPES[$type])) {
                continue;
            }

            $node = WorkflowNode::create([
                'workflow_id' => $scenario->id,
                'parent_id'   => $parentId,
                'branch'      => $branch,
                'sort_order'  => $i,
                'type'        => $type,
                'name'        => trim((string) ($raw['name'] ?? '')) ?: WorkflowNode::TYPES[$type]['label'],
                'config'      => $this->sanitizeConfig($type, (array) ($raw['config'] ?? [])),
            ]);

            if (WorkflowNode::TYPES[$type]['branching']) {
                foreach (['yes', 'no'] as $side) {
                    $this->insertChain($scenario, (array) ($raw[$side] ?? []), $node->id, $side, $depth + 1);
                }
            }
        }
    }

    /**
     * Настройки узла приходят из браузера, поэтому берём только те ключи, которые
     * тип узла действительно понимает, и приводим их к нужному виду.
     */
    private function sanitizeConfig(string $type, array $config): array
    {
        $ints = fn ($value) => array_values(array_unique(array_map('intval', array_filter((array) $value, 'is_numeric'))));
        $text = fn ($value, int $max = 255) => mb_substr(trim((string) $value), 0, $max) ?: null;

        return match ($type) {
            'approval', 'approve', 'opinion', 'ack', 'intake' => [
                'resolver'             => in_array($config['resolver'] ?? null, ['user', 'group'], true) ? $config['resolver'] : 'user',
                'approver_ids'         => $ints($config['approver_ids'] ?? []),
                'group_department_ids' => $ints($config['group_department_ids'] ?? []),
                'group_role'           => $text($config['group_role'] ?? null, 64),
                'policy'               => ($config['policy'] ?? 'all') === 'any' ? 'any' : 'all',
                'sla_days'             => ($days = (int) ($config['sla_days'] ?? 0)) > 0 ? min($days, 365) : null,
                // Не держать маршрут вправе только совещательное звено: согласование,
                // утверждение и приём ждут решения всегда.
                'is_blocking'          => in_array($type, ['ack', 'opinion'], true) ? (bool) ($config['is_blocking'] ?? true) : true,
                'on_reject'            => ($config['on_reject'] ?? 'return_initiator') === 'reject' ? 'reject' : 'return_initiator',
            ],
            // Условие ветвится либо по ответу на параметр запуска, либо по отделу
            // инициатора — у каждого источника свой набор настроек.
            'condition' => ($config['source'] ?? 'parameter') === 'initiator_department'
                ? [
                    'source'             => 'initiator_department',
                    'department_ids'     => $ints($config['department_ids'] ?? []),
                    'condition_operator' => ($config['condition_operator'] ?? 'in') === 'not_in' ? 'not_in' : 'in',
                ]
                : [
                    'source'             => 'parameter',
                    'condition_key'      => $text($config['condition_key'] ?? null, 64),
                    'condition_operator' => array_key_exists((string) ($config['condition_operator'] ?? '='), WorkflowStage::OPERATORS)
                        ? $config['condition_operator'] : '=',
                    'condition_value'    => $text($config['condition_value'] ?? null),
                ],
            'status' => [
                'status' => array_key_exists((string) ($config['status'] ?? ''), WorkflowNode::STATUSES)
                    ? $config['status'] : 'in_review',
            ],
            'notify' => [
                'recipients' => array_key_exists((string) ($config['recipients'] ?? ''), WorkflowNode::RECIPIENTS)
                    ? $config['recipients'] : 'initiator',
                'user_ids'   => $ints($config['user_ids'] ?? []),
                'text'       => $text($config['text'] ?? null, 1000),
            ],
            'end' => [
                'result' => array_key_exists((string) ($config['result'] ?? ''), WorkflowNode::RESULTS)
                    ? $config['result'] : 'approved',
            ],
            default => [],
        };
    }

    /** @param \Illuminate\Support\Collection<WorkflowNode> $nodes */
    private function copyChain($nodes, Workflow $target, ?int $parentId): void
    {
        foreach ($nodes as $node) {
            $copy = WorkflowNode::create([
                'workflow_id' => $target->id,
                'parent_id'   => $parentId,
                'branch'      => $node->branch,
                'sort_order'  => $node->sort_order,
                'type'        => $node->type,
                'name'        => $node->name,
                // Состав участников разворачивается при публикации: движок получает
                // готовый список людей, как и для звеньев.
                'config'      => $node->isTask()
                    ? ['approver_ids' => $node->resolvedApproverIds()] + $node->config
                    : $node->config,
            ]);

            $this->copyChain($node->children, $target, $copy->id);
        }
    }

    /**
     * Плоский след графа в звеньях — для предпросмотра маршрута и карточки сценария.
     * Порядок обхода — как у исполнения: узел, затем ветка «Да», затем ветка «Нет».
     */
    public function compileStages(Workflow $scenario): void
    {
        foreach ($scenario->stages()->get() as $stage) {
            // По звену уже шли согласования — физически удалить нельзя, на него
            // ссылается история. Убираем из маршрута мягко.
            if (DocumentApprovalStage::where('workflow_stage_id', $stage->id)->exists()) {
                $stage->delete();
                continue;
            }

            $stage->approvers()->delete();
            $stage->forceDelete();
        }

        $order = 0;
        $this->compileChain($scenario->nodes()->whereNull('parent_id')->get(), $scenario, null, $order);
    }

    /** @param \Illuminate\Support\Collection<WorkflowNode> $nodes */
    private function compileChain($nodes, Workflow $scenario, ?array $condition, int &$order): void
    {
        foreach ($nodes as $node) {
            if ($node->isTask()) {
                $stage = WorkflowStage::create([
                    'workflow_id'          => $scenario->id,
                    'name'                 => $node->name,
                    'phase'                => $node->type,
                    'stage_type'           => $node->cfg('policy', 'all') === 'any' ? 'sequential' : 'parallel',
                    'resolver'             => $node->cfg('resolver', 'user'),
                    'group_department_ids' => $node->cfg('group_department_ids') ?: null,
                    'group_role'           => $node->cfg('group_role'),
                    'policy'               => $node->cfg('policy', 'all'),
                    'sla_days'             => $node->cfg('sla_days'),
                    'is_blocking'          => (bool) $node->cfg('is_blocking', true),
                    'on_reject'            => $node->cfg('on_reject', 'return_initiator'),
                    'condition_key'        => $condition['key'] ?? null,
                    'condition_operator'   => $condition['operator'] ?? null,
                    'condition_value'      => $condition['value'] ?? null,
                    'sort_order'           => $order++,
                ]);

                foreach ($node->cfg('approver_ids', []) ?: [] as $userId) {
                    WorkflowStageApprover::create([
                        'workflow_stage_id' => $stage->id,
                        'approver_type'     => 'user',
                        'approver_id'       => $userId,
                        'is_required'       => true,
                        'participant_type'  => 'signatory',
                    ]);
                }
            }

            foreach ($node->children->groupBy('branch') as $branch => $children) {
                $this->compileChain(
                    $children,
                    $scenario,
                    $node->type === 'condition' ? $this->conditionFor($node, $branch) : $condition,
                    $order,
                );
            }
        }
    }

    /**
     * Условие, при котором звено внутри ветки вообще участвует в маршруте.
     * Ветка «Нет» — это отрицание условия; операторы без пары отрицания
     * предпросмотр не описывает, поэтому условие для них не проставляется.
     */
    private function conditionFor(WorkflowNode $node, string $branch): ?array
    {
        $key = $node->cfg('condition_key');
        $operator = $node->cfg('condition_operator', '=');

        // Условие по отделу инициатора предпросмотр не описывает: он строится на
        // ответах при запуске, а отдел от них не зависит.
        if ($node->cfg('source', 'parameter') === 'initiator_department' || ! $key) {
            return null;
        }

        if ($branch === 'no') {
            $operator = match ($operator) {
                '='  => '!=',
                '!=' => '=',
                default => null,
            };

            if (! $operator) {
                return null;
            }
        }

        return ['key' => $key, 'operator' => $operator, 'value' => $node->cfg('condition_value')];
    }
}
