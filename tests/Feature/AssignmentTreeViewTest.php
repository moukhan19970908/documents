<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Страница поручения показывает всё дерево под ним — послойно: постановщик корня видит все
 * узлы, исполнитель — свой узел и подпоручения, которые поставил сам. Переключатель
 * «что видит участник» получает только тот, кто сам видит всё дерево.
 */
class AssignmentTreeViewTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private User $security;

    private Assignment $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->director = $this->user('Ильдар Минниханов');
        $this->security = $this->user('Ринат Хабибуллин');
        $sales = $this->user('Диляра Салихова');

        $this->root = $this->node(null, $this->director, $this->security, 'Проверка контрагента');
        $child = $this->node($this->root, $this->security, $sales, 'Разъяснение отдела продаж');
        $this->node($child, $sales, $this->user('Максим Петров'), 'История сотрудничества');
    }

    private function user(string $name): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'linear', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    private function node(?Assignment $parent, User $initiator, User $executor, string $title): Assignment
    {
        static $seq = 0;
        $seq++;

        $node = Assignment::create([
            'number'       => 'ПОР-2026-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'seq'          => $seq,
            'parent_id'    => $parent?->id,
            'root_id'      => $parent ? ($parent->root_id ?? $parent->id) : null,
            'depth'        => $parent ? $parent->depth + 1 : 0,
            'title'        => $title,
            'initiator_id' => $initiator->id,
            'executor_id'  => $executor->id,
            'status'       => 'in_progress',
            'is_mandatory' => true,
        ]);

        if (! $parent) {
            $node->update(['root_id' => $node->id]);
        }

        return $node;
    }

    public function test_root_initiator_sees_the_whole_tree_down_to_grandchildren(): void
    {
        $this->actingAs($this->director)
            ->get(route('assignments.show', $this->root))
            ->assertSee('История сотрудничества');
    }

    public function test_executor_does_not_see_subassignment_set_by_someone_else(): void
    {
        $this->actingAs($this->security)
            ->get(route('assignments.show', $this->root))
            ->assertDontSee('История сотрудничества');
    }

    public function test_root_initiator_can_switch_to_what_each_participant_sees(): void
    {
        $this->actingAs($this->director)
            ->get(route('assignments.show', $this->root))
            ->assertSee('Исполнитель 3-го уровня (Максим Петров)');
    }

    public function test_executor_seeing_part_of_the_tree_gets_no_participant_switcher(): void
    {
        $this->actingAs($this->security)
            ->get(route('assignments.show', $this->root))
            ->assertDontSee('переключите роль');
    }
}
