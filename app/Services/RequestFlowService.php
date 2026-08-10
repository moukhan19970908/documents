<?php

namespace App\Services;

use App\Models\RequestFlow;
use App\Models\RequestFlowNode;
use Illuminate\Support\Facades\DB;

/**
 * Сборка/сохранение граф-процесса заявки. Дерево: корневая цепочка + у ветвящегося узла
 * N именованных веток (branches: { ключ: [дочерние узлы] }). Ключи веток задаёт config узла.
 */
class RequestFlowService
{
    /** Дерево графа для редактора: массив узлов, у ветвящихся — branches по ключу ветки. */
    public function tree(RequestFlow $flow): array
    {
        $byChain = $flow->nodes()->orderBy('sort_order')->orderBy('id')->get()
            ->groupBy(fn ($n) => $n->parent_id . '|' . $n->branch);

        return $this->buildChain($byChain, null, 'main');
    }

    private function buildChain($byChain, ?int $parentId, string $branch): array
    {
        $chain = $byChain[$parentId . '|' . $branch] ?? collect();

        return $chain->map(function (RequestFlowNode $node) use ($byChain) {
            $out = [
                'type'   => $node->type,
                'name'   => $node->name,
                'config' => $node->config ?? [],
            ];

            if ($node->isBranching()) {
                // Ветки узла — по ключам из config (или из уже сохранённых дочерних цепочек).
                $out['branches'] = [];
                foreach ($this->branchKeys($node, $byChain) as $key) {
                    $out['branches'][$key] = $this->buildChain($byChain, $node->id, $key);
                }
            }

            return $out;
        })->values()->all();
    }

    /** Ключи веток узла: объявленные в config плюс те, под которыми уже есть дочерние узлы. */
    private function branchKeys(RequestFlowNode $node, $byChain): array
    {
        $declared = collect($node->cfg('branches', []))->pluck('key')->filter()->all();

        $present = $byChain->keys()
            ->filter(fn ($k) => str_starts_with($k, $node->id . '|'))
            ->map(fn ($k) => explode('|', $k, 2)[1])
            ->all();

        return collect($declared)->merge($present)->unique()->values()->all();
    }

    /** Сохранить дерево из редактора: полностью пересобираем узлы графа. */
    public function save(RequestFlow $flow, array $tree): void
    {
        DB::transaction(function () use ($flow, $tree) {
            $flow->nodes()->delete();
            $this->insertChain($flow, $tree, null, 'main');
        });
    }

    private function insertChain(RequestFlow $flow, array $chain, ?int $parentId, string $branch): void
    {
        $order = 0;

        foreach ($chain as $raw) {
            $type = $raw['type'] ?? null;
            if (! isset(RequestFlowNode::TYPES[$type])) {
                continue; // незнакомый тип — пропускаем
            }

            $node = $flow->nodes()->create([
                'parent_id'  => $parentId,
                'branch'     => $branch,
                'sort_order' => $order++,
                'type'       => $type,
                'name'       => (string) ($raw['name'] ?? RequestFlowNode::TYPES[$type]['label']),
                'config'     => is_array($raw['config'] ?? null) ? $raw['config'] : [],
            ]);

            if ($node->isBranching() && is_array($raw['branches'] ?? null)) {
                foreach ($raw['branches'] as $key => $subChain) {
                    $this->insertChain($flow, is_array($subChain) ? $subChain : [], $node->id, (string) $key);
                }
            }
        }
    }
}
