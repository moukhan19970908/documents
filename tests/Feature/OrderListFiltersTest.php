<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DocumentSubtype;
use App\Models\DocumentType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Фильтры списка приказов зависят от роли: издающий ищет по статусу и направлению-адресату,
 * знакомящийся — по виду приказа.
 */
class OrderListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    private function order(string $title, array $attributes = []): Order
    {
        return Order::create($attributes + [
            'title'        => $title,
            'initiator_id' => $this->user->id,
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    private function addressedTo(Department $department, string $title): Order
    {
        return $this->order($title, ['audience' => ['department_ids' => [$department->id], 'user_ids' => []]]);
    }

    /** Приказ вида $kind, который пользователь должен прочитать. */
    private function toAcknowledge(DocumentSubtype $kind, string $title): Order
    {
        $order = $this->order($title, ['document_subtype_id' => $kind->id]);
        $order->acknowledgments()->create(['user_id' => $this->user->id]);

        return $order;
    }

    private function kinds(): array
    {
        $type = DocumentType::create(['name' => 'Приказ', 'code' => 'ПРК', 'slug' => DocumentType::ORDER_SLUG, 'is_active' => true]);

        return [
            DocumentSubtype::create(['document_type_id' => $type->id, 'name' => 'Кадровый', 'is_active' => true]),
            DocumentSubtype::create(['document_type_id' => $type->id, 'name' => 'Об утверждении', 'is_active' => true]),
        ];
    }

    public function test_direction_filter_finds_order_addressed_to_its_subdepartment(): void
    {
        $direction = Department::create(['name' => 'Продажи']);
        $this->addressedTo(Department::create(['name' => 'Отдел опта', 'parent_id' => $direction->id]), 'Об отгрузках опта');

        $this->actingAs($this->user)
            ->get(route('orders.index', ['as' => 'issuer', 'direction' => $direction->id]))
            ->assertSee('Об отгрузках опта');
    }

    public function test_direction_filter_hides_order_addressed_to_another_direction(): void
    {
        $sales = Department::create(['name' => 'Продажи']);
        $this->addressedTo(Department::create(['name' => 'Бухгалтерия']), 'О закрытии квартала');

        $this->actingAs($this->user)
            ->get(route('orders.index', ['as' => 'issuer', 'direction' => $sales->id]))
            ->assertDontSee('О закрытии квартала');
    }

    public function test_kind_filter_hides_orders_of_another_kind(): void
    {
        [$personnel, $approval] = $this->kinds();
        $this->toAcknowledge($personnel, 'О приёме на работу');
        $this->toAcknowledge($approval, 'Об утверждении регламента');

        $this->actingAs($this->user)
            ->get(route('orders.index', ['as' => 'employee', 'kind' => $personnel->id]))
            ->assertDontSee('Об утверждении регламента');
    }

    public function test_acknowledging_employee_is_not_offered_a_status_filter(): void
    {
        $this->actingAs($this->user)
            ->get(route('orders.index', ['as' => 'employee']))
            ->assertDontSee('Статус: Все');
    }
}
