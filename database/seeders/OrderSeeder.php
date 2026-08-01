<?php

namespace Database\Seeders;

use App\Models\BlankTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Тип «Приказ» существует, чтобы к нему привязать бланки приказа.
        $type = DocumentType::firstOrCreate(
            ['slug' => DocumentType::ORDER_SLUG],
            ['name' => 'Приказ', 'code' => 'ПРК', 'icon' => 'order', 'is_active' => true]
        );

        $title = '<h2 style="text-align:center;margin:0"><strong>ПРИКАЗ</strong> № {номер} от {дата}</h2>'
            . '<p style="text-align:center;color:#9ca3af;font-size:12px;margin:2px 0 0">Номер присваивается при публикации</p>'
            . '<hr><p></p><p></p>';

        $letterhead = fn (string $org, string $req) =>
            '<table style="width:100%;border:none"><tbody><tr>'
            . '<td style="width:64px;border:none;vertical-align:top"><div style="width:48px;height:48px;border-radius:10px;background:#5B4FE8"></div></td>'
            . '<td style="border:none;vertical-align:top"><strong>' . $org . '</strong><br>'
            . '<span style="color:#6b7280;font-size:12px">' . $req . '</span></td>'
            . '</tr></tbody></table>';

        $blanks = [
            [
                'name'    => 'ООО «ТД Вамин»',
                'content' => $letterhead('ООО «ТД Вамин Татарстан»', '420000, г. Казань, ул. Портовая, д. 27 · ИНН 1655123456') . $title,
            ],
            [
                'name'    => 'ГК «Вамин»',
                'content' => $letterhead('Группа компаний «Вамин»', 'Республика Татарстан · vamin.ru') . $title,
            ],
            [
                'name'    => 'Без шапки',
                'content' => $title,
            ],
        ];

        foreach ($blanks as $b) {
            BlankTemplate::firstOrCreate(
                ['document_type_id' => $type->id, 'name' => $b['name']],
                ['content' => $b['content'], 'is_active' => true]
            );
        }
    }
}
