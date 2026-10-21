<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentField;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Документ вернули на доработку: инициатор правит не только файл, но и поля карточки
 * «О документе» (сумма, ИНН, срок отсрочки…). Чужие ключи в data не пишутся.
 */
class DocumentFieldsReworkEditTest extends TestCase
{
    use RefreshDatabase;

    private User $initiator;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initiator = User::factory()->create(['role' => 'linear', 'is_active' => true, 'agreement_accepted_at' => now()]);

        $type = DocumentType::create(['name' => 'Кредитный комитет', 'code' => 'КК', 'slug' => 'kk', 'is_active' => true]);
        DocumentField::create(['document_type_id' => $type->id, 'label' => 'Сумма', 'field_key' => 'amount', 'field_type' => 'number', 'is_required' => true]);
        DocumentField::create(['document_type_id' => $type->id, 'label' => 'Комментарий', 'field_key' => 'comment', 'field_type' => 'textarea']);

        $this->document = Document::create([
            'title' => 'КК', 'document_type_id' => $type->id, 'initiator_id' => $this->initiator->id,
            'status' => 'requires_changes', 'data' => ['amount' => '70000000', 'comment' => 'Поставка 70 млн'],
        ]);
    }

    public function test_show_offers_field_form_on_rework(): void
    {
        $this->actingAs($this->initiator)
            ->get(route('documents.show', $this->document))
            ->assertOk()
            ->assertSee('name="data[amount]"', false)
            ->assertSee('name="data[comment]"', false);
    }

    public function test_initiator_updates_fields_on_rework(): void
    {
        $this->actingAs($this->initiator)
            ->put(route('documents.update', $this->document), [
                'data' => ['amount' => '50000000', 'comment' => 'Поставка 50 млн', 'injected' => 'x'],
            ])
            ->assertRedirect(route('documents.show', $this->document));

        $this->assertSame(
            ['amount' => '50000000', 'comment' => 'Поставка 50 млн'],
            $this->document->refresh()->data,
        );
    }

    public function test_required_field_cannot_be_cleared(): void
    {
        $this->actingAs($this->initiator)
            ->put(route('documents.update', $this->document), ['data' => ['amount' => '']])
            ->assertSessionHasErrors('data.amount');

        $this->assertSame('70000000', $this->document->refresh()->data['amount']);
    }

    public function test_fields_are_locked_while_in_review(): void
    {
        $this->document->update(['status' => 'in_review']);

        $this->actingAs($this->initiator)
            ->put(route('documents.update', $this->document), ['data' => ['amount' => '1']])
            ->assertForbidden();

        $this->actingAs($this->initiator)
            ->get(route('documents.show', $this->document))
            ->assertDontSee('name="data[amount]"', false);
    }
}
