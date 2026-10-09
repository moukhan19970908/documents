<?php

namespace Tests\Feature;

use App\Models\Inspection;
use App\Models\InspectionFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Проверки устроены как поручения: страница показывает дерево подпроверок послойно,
 * постановщик удаляет проверку в любом статусе вместе со всей веткой и её файлами.
 */
class InspectionTreeTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private User $auditor;

    private Inspection $root;

    private Inspection $child;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->director = $this->user();
        $this->auditor  = $this->user();
        $accountant     = $this->user();

        $this->root  = $this->node(null, $this->director, $this->auditor, 'Проверка кадровой документации');
        $this->child = $this->node($this->root, $this->auditor, $accountant, 'Сверка табелей');
        $this->node($this->child, $accountant, $this->user(), 'Табели склада');
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'linear', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    private function node(?Inspection $parent, User $initiator, User $executor, string $title): Inspection
    {
        static $seq = 0;
        $seq++;

        $node = Inspection::create([
            'number'       => 'ПРВ-2026-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
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

    public function test_root_initiator_sees_subinspections_down_to_grandchildren(): void
    {
        $this->actingAs($this->director)
            ->get(route('inspections.show', $this->root))
            ->assertSee('Табели склада');
    }

    public function test_auditor_does_not_see_subinspection_set_by_someone_else(): void
    {
        $this->actingAs($this->auditor)
            ->get(route('inspections.show', $this->root))
            ->assertDontSee('Табели склада');
    }

    public function test_initiator_deletes_inspection_in_progress_with_all_subinspections(): void
    {
        $this->actingAs($this->director)->delete(route('inspections.destroy', $this->root));

        $this->assertSame(0, Inspection::count());
    }

    public function test_auditor_cannot_delete_inspection_set_by_someone_else(): void
    {
        $this->actingAs($this->auditor)
            ->delete(route('inspections.destroy', $this->root))
            ->assertForbidden();
    }

    public function test_deleted_subinspection_files_leave_parents_collected_materials(): void
    {
        $file = ['uploaded_by' => $this->auditor->id, 'original_name' => 'tabel.xlsx', 'path' => 'inspections/tabel.xlsx', 'size' => 1, 'mime' => 'application/vnd.ms-excel'];
        InspectionFile::create($file + ['inspection_id' => $this->child->id]);
        InspectionFile::create($file + ['inspection_id' => $this->root->id, 'source_inspection_id' => $this->child->id]);

        $this->actingAs($this->auditor)->delete(route('inspections.destroy', $this->child));

        $this->assertDatabaseMissing('inspection_files', ['path' => 'inspections/tabel.xlsx']);
    }
}
