<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Приказ, изданный загруженным файлом, показывается на своей странице без скачивания.
 * Inline отдаются только безопасные форматы — иначе загруженный HTML выполнился бы на нашем домене.
 */
class OrderFilePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $initiator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->initiator = User::factory()->create(['role' => 'admin', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    private function orderWithFile(string $fileName): Order
    {
        Storage::put('orders/upload.bin', 'содержимое');

        return Order::create([
            'title'        => 'О премировании',
            'file_path'    => 'orders/upload.bin',
            'file_name'    => $fileName,
            'initiator_id' => $this->initiator->id,
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_order_page_embeds_a_preview_of_the_uploaded_pdf(): void
    {
        $order = $this->orderWithFile('Приказ.pdf');

        $this->actingAs($this->initiator)
            ->get(route('orders.show', $order))
            ->assertSee(route('orders.file.preview', $order));
    }

    public function test_uploaded_pdf_is_served_inline(): void
    {
        $order = $this->orderWithFile('Приказ.pdf');

        $response = $this->actingAs($this->initiator)->get(route('orders.file.preview', $order));

        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_uploaded_html_is_not_served_inline(): void
    {
        $order = $this->orderWithFile('page.html');

        $this->actingAs($this->initiator)
            ->get(route('orders.file.preview', $order))
            ->assertNotFound();
    }
}
