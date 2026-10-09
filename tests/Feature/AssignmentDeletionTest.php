<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Постановщик удаляет своё поручение или подпоручение в любом статусе — вместе со всей веткой
 * под ним. Файлы удалённых узлов уходят и из хранилища, и из «Собранных материалов» предков.
 */
class AssignmentDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private User $executor;

    private Assignment $root;

    private Assignment $child;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->director = $this->user();
        $this->executor = $this->user();

        $this->root  = $this->node(null, $this->director, $this->executor);
        $this->child = $this->node($this->root, $this->executor, $this->user());
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'linear', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    private function node(?Assignment $parent, User $initiator, User $executor): Assignment
    {
        static $seq = 0;
        $seq++;

        $node = Assignment::create([
            'number'       => 'ПОР-2026-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'seq'          => $seq,
            'parent_id'    => $parent?->id,
            'root_id'      => $parent?->id,
            'depth'        => $parent ? 1 : 0,
            'title'        => 'Поручение ' . $seq,
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

    /** Файл исполнителя подпоручения, уже подтянутый в «Собранные материалы» корня. */
    private function acceptedChildFile(): string
    {
        Storage::put('assignments/report.pdf', 'pdf');
        $file = ['uploaded_by' => $this->executor->id, 'original_name' => 'report.pdf', 'path' => 'assignments/report.pdf', 'size' => 3, 'mime' => 'application/pdf'];
        AssignmentFile::create($file + ['assignment_id' => $this->child->id]);
        AssignmentFile::create($file + ['assignment_id' => $this->root->id, 'source_assignment_id' => $this->child->id]);

        return 'assignments/report.pdf';
    }

    public function test_initiator_deletes_assignment_in_progress_together_with_subassignments(): void
    {
        $this->actingAs($this->director)->delete(route('assignments.destroy', $this->root));

        $this->assertSame(0, Assignment::count());
    }

    public function test_executor_cannot_delete_assignment_set_by_someone_else(): void
    {
        $this->actingAs($this->executor)
            ->delete(route('assignments.destroy', $this->root))
            ->assertForbidden();
    }

    public function test_deleting_subassignment_keeps_its_parent(): void
    {
        $this->actingAs($this->executor)->delete(route('assignments.destroy', $this->child));

        $this->assertDatabaseHas('assignments', ['id' => $this->root->id]);
    }

    public function test_deleted_subassignment_files_leave_parents_collected_materials(): void
    {
        $path = $this->acceptedChildFile();

        $this->actingAs($this->executor)->delete(route('assignments.destroy', $this->child));

        $this->assertDatabaseMissing('assignment_files', ['path' => $path]);
    }

    public function test_deleted_subassignment_files_are_removed_from_storage(): void
    {
        $path = $this->acceptedChildFile();

        $this->actingAs($this->executor)->delete(route('assignments.destroy', $this->child));

        Storage::assertMissing($path);
    }
}
