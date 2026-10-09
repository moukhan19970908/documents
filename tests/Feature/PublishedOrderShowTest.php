<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Карточка опубликованного приказа: адресат ознакамливается прямо на ней,
 * поэтому текст приказа должен быть виден и после публикации, а не только у черновика.
 */
class PublishedOrderShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_order_page_shows_the_order_text(): void
    {
        $initiator = User::factory()->create(['role' => 'admin', 'is_active' => true, 'agreement_accepted_at' => now()]);
        $order = Order::create([
            'title'        => 'О премировании',
            'body_html'    => '<p>Премировать сотрудников отдела продаж</p>',
            'initiator_id' => $initiator->id,
            'status'       => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($initiator)
            ->get(route('orders.show', $order))
            ->assertSee('Премировать сотрудников отдела продаж');
    }
}
