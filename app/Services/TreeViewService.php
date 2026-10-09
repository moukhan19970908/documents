<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Дерево на странице поручения или проверки. Оба — свободные деревья (parent_id / root_id /
 * depth, постановщик и исполнитель узла) с послойной видимостью visibleTo() и файлами ownFiles.
 */
class TreeViewService
{
    /**
     * Каждому узлу поддерева — отношения children (все дети) и visibleChildren (видимые
     * пользователю; скрытый узел пропадает вместе со своей веткой).
     *
     * @param  string[]  $with  отношения, которые нужны карточкам узлов
     * @return array{0: int, 1: int} [видимых узлов, всего узлов] в поддереве, включая сам узел
     */
    public function attachVisibleTree(Model $node, User $user, array $with): array
    {
        $byParent = $node::query()
            ->where('root_id', $node->root_id ?? $node->id)
            ->with($with)
            ->withCount('ownFiles')
            ->orderBy('id')
            ->get()
            ->groupBy('parent_id');
        $childrenOf = fn (Model $n) => collect($byParent[$n->id] ?? []);

        $visible = 1;
        $attach = function (Model $parent) use (&$attach, &$visible, $childrenOf, $user) {
            // Все дети (и скрытые) — от них зависят приёмка и предупреждение при удалении ветки.
            $parent->setRelation('children', $childrenOf($parent));
            $parent->setRelation('visibleChildren', $childrenOf($parent)->filter(fn ($c) => $c->visibleTo($user))->values());
            foreach ($parent->visibleChildren as $child) {
                $visible++;
                $attach($child);
            }
        };
        $attach($node);

        $count = function (Model $n) use (&$count, $childrenOf): int {
            return 1 + $childrenOf($n)->sum($count);
        };

        return [$visible, $count($node)];
    }

    /**
     * Вкладки «что видит участник»: постановщик узла и исполнители подпорученных узлов по уровню
     * (обход в ширину — человек получает свой самый высокий уровень). Ожидает visibleChildren.
     *
     * @param  string  $executorRole  «Исполнитель» / «Проверяющий»
     * @return Collection<int, string> id => подпись
     */
    public function perspectives(Model $node, string $executorRole): Collection
    {
        $perspectives = collect([$node->initiator_id => 'Инициатор (' . $node->initiator->name . ')']);

        for ($queue = [$node]; $queue; ) {
            $n = array_shift($queue);
            $level = $n->depth - $node->depth + 1;
            if (! $perspectives->has($n->executor_id)) {
                $perspectives->put($n->executor_id, "{$executorRole} {$level}-го уровня (" . $n->executor->name . ')');
            }
            array_push($queue, ...$n->visibleChildren);
        }

        return $perspectives;
    }

    /** Постановщик корня — он видит всё дерево, поэтому входит в число видящих каждый узел. */
    public function rootInitiatorId(Model $node): int
    {
        return $node->root_id && $node->root_id !== $node->id
            ? (int) $node::query()->whereKey($node->root_id)->value('initiator_id')
            : (int) $node->initiator_id;
    }
}
