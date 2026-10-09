<?php

namespace Tests\Feature;

use App\Models\DocumentSubtype;
use App\Models\DocumentType;
use App\Models\Numerator;
use App\Models\NumeratorBinding;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Виды приказа задаются в классификаторе: это подтипы типа «Приказ».
 * Администратор правит их там, а инициатор выбирает из активных.
 */
class OrderKindClassifierTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role'                  => 'admin',
            'is_active'             => true,
            'agreement_accepted_at' => now(),
        ]);
    }

    private function orderType(): DocumentType
    {
        return DocumentType::create([
            'name' => 'Приказ', 'code' => 'ПРК', 'slug' => DocumentType::ORDER_SLUG, 'icon' => 'order', 'is_active' => true,
        ]);
    }

    private function kind(DocumentType $type, string $name, bool $isActive = true): DocumentSubtype
    {
        return DocumentSubtype::create(['document_type_id' => $type->id, 'name' => $name, 'is_active' => $isActive]);
    }

    private function orderPayload(int $kindId): array
    {
        return ['document_subtype_id' => $kindId, 'title' => 'О премировании', 'source' => 'blank'];
    }

    public function test_order_form_offers_an_active_subtype_of_the_order_type_as_a_kind(): void
    {
        $this->kind($this->orderType(), 'Дисциплинарный');

        $this->actingAs($this->admin())
            ->get(route('orders.create'))
            ->assertSee('Дисциплинарный');
    }

    public function test_order_form_hides_a_deactivated_kind(): void
    {
        $this->kind($this->orderType(), 'Устаревший вид', isActive: false);

        $this->actingAs($this->admin())
            ->get(route('orders.create'))
            ->assertDontSee('Устаревший вид');
    }

    public function test_order_is_saved_with_the_chosen_kind(): void
    {
        $kind = $this->kind($this->orderType(), 'Кадровый');

        $this->actingAs($this->admin())->post(route('orders.store'), $this->orderPayload($kind->id));

        $this->assertSame($kind->id, Order::firstOrFail()->document_subtype_id);
    }

    public function test_subtype_of_another_type_is_rejected_as_an_order_kind(): void
    {
        $this->orderType();
        $foreign = $this->kind(
            DocumentType::create(['name' => 'Договор', 'code' => 'ДОГ', 'slug' => 'dogovor', 'is_active' => true]),
            'На поставку',
        );

        $this->actingAs($this->admin())
            ->post(route('orders.store'), $this->orderPayload($foreign->id))
            ->assertSessionHasErrors('document_subtype_id');
    }

    public function test_kind_bound_to_a_numerator_is_numbered_by_it(): void
    {
        $kind = $this->kind($this->orderType(), 'Кадровый');
        $numerator = Numerator::create(['name' => 'Кадровые', 'mask' => 'К-{N}', 'reset_period' => 'none', 'padding' => 1, 'start_value' => 0]);
        NumeratorBinding::create([
            'numerator_id' => $numerator->id, 'classifier_type' => 'document_subtype', 'classifier_id' => (string) $kind->id,
        ]);
        $order = Order::create(['document_subtype_id' => $kind->id, 'title' => 'О приёме', 'initiator_id' => $this->admin()->id]);

        $this->assertSame('К-1', app(OrderNumberService::class)->assign($order));
    }
}
