<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * После публикации приказ несёт штамп-удостоверение «Документ утвержден», как согласованный
 * документ: на листе бланка — HTML-блоком, в загруженном файле — впечатанным в сам файл.
 * У черновика штампа нет.
 */
class OrderCertificateStampTest extends TestCase
{
    use RefreshDatabase;

    private User $initiator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->initiator = User::factory()->create(['role' => 'admin', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    private function order(string $status, array $attributes = []): Order
    {
        return Order::create($attributes + [
            'title'        => 'О премировании',
            'body_html'    => '<p>Премировать сотрудников</p>',
            'initiator_id' => $this->initiator->id,
            'status'       => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    private function orderWithDocx(string $status): Order
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<w:document><w:body><w:p/></w:body></w:document>');
        $zip->close();
        Storage::put('orders/order.docx', file_get_contents($tmp));
        unlink($tmp);

        return $this->order($status, ['body_html' => null, 'file_path' => 'orders/order.docx', 'file_name' => 'Приказ.docx']);
    }

    private function documentXml(string $docxBytes): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $docxBytes);
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tmp);

        return $xml;
    }

    public function test_published_order_page_shows_the_certificate_stamp(): void
    {
        $order = $this->order('published');

        $this->actingAs($this->initiator)
            ->get(route('orders.show', $order))
            ->assertSee('Документ утвержден');
    }

    public function test_draft_order_print_version_has_no_certificate_stamp(): void
    {
        $order = $this->order('draft');

        $this->actingAs($this->initiator)
            ->get(route('orders.pdf', $order))
            ->assertDontSee('Документ утвержден');
    }

    public function test_published_order_file_is_downloaded_with_the_stamp_inside(): void
    {
        $order = $this->orderWithDocx('published');

        $response = $this->actingAs($this->initiator)->get(route('orders.file', $order));

        $this->assertStringContainsString('ДОКУМЕНТ УТВЕРЖДЕН', $this->documentXml($response->getContent()));
    }

    public function test_draft_order_file_is_downloaded_unchanged(): void
    {
        $order = $this->orderWithDocx('draft');

        $response = $this->actingAs($this->initiator)->get(route('orders.file', $order));

        $this->assertStringNotContainsString('ДОКУМЕНТ УТВЕРЖДЕН', $this->documentXml($response->streamedContent()));
    }
}
