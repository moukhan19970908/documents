<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Рендерит тело документа по бланку (HTML из body_html с подставленными токенами) в PDF.
 *
 * DomPDF в сборке нет — используем TCPDF (writeHTML), тот же движок, что и штамп-удостоверение.
 * Готовый PDF затем штампуется через FileWatermarkService, как и загруженные файлы.
 * Лист имитирует экранный вид бланка: A4, серифный шрифт (кириллица), поля как в .blank-sheet.
 */
class BlankPdfService
{
    /** Растровые форматы, которые TCPDF рендерит без внешних зависимостей. */
    private const SUPPORTED_IMAGE_MIME = ['image/png', 'image/jpeg', 'image/gif'];

    /** @param string $bodyHtml тело бланка с уже подставленными токенами (DocumentNamingService::fillBlank) */
    public function render(Document $document, string $bodyHtml): string
    {
        // Картинки готовим заранее: data-URI TCPDF рисует сам, внешние ссылки скачиваем и
        // встраиваем, недоступные — убираем (иначе TCPDF валит всю генерацию исключением).
        try {
            return $this->build($this->inlineImages($bodyHtml));
        } catch (\Throwable $e) {
            // Подстраховка: если картинка всё же уронила рендер — собираем без картинок,
            // чтобы скачивание не ломалось.
            Log::warning("BlankPdf: рендер документа {$document->id} с картинками не удался: {$e->getMessage()}");

            return $this->build($this->stripImages($bodyHtml));
        }
    }

    private function build(string $bodyHtml): string
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 18, 20);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetFont('dejavuserif', '', 12);
        $pdf->AddPage();

        // Times-подобный лист: серифный шрифт и те же правила оформления, что и на экране
        // (.blank-sheet). Таблицы-«шапки» бланка помечены классом table-borderless.
        $html = '<style>'
            . 'body { font-family: dejavuserif; font-size: 12pt; line-height: 1.5; color: #111827; }'
            . 'h1 { font-size: 18pt; font-weight: bold; } h2 { font-size: 15pt; font-weight: bold; } h3 { font-size: 13pt; font-weight: bold; }'
            . 'table { border-collapse: collapse; width: 100%; }'
            . 'th, td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }'
            . 'th { background-color: #f3f4f6; font-weight: bold; text-align: left; }'
            . 'table.table-borderless th, table.table-borderless td { border: none; }'
            . 'table.table-borderless th { background-color: transparent; font-weight: normal; }'
            . '</style>'
            . $bodyHtml;

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('blank.pdf', 'S');
    }

    /** Готовит <img> к рендеру: data-URI оставляем, внешние скачиваем в data-URI, остальное убираем. */
    private function inlineImages(string $html): string
    {
        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
            $tag = $m[0];

            if (! preg_match('/\bsrc\s*=\s*"([^"]*)"/i', $tag, $s)
                && ! preg_match("/\bsrc\s*=\s*'([^']*)'/i", $tag, $s)) {
                return ''; // <img> без src — TCPDF всё равно его не покажет
            }

            $src = html_entity_decode($s[1], ENT_QUOTES);

            if (stripos($src, 'data:image/') === 0) {
                return $tag; // локальный data-URI — рендерится как есть
            }

            if (preg_match('#^https?://#i', $src) && ($encoded = $this->fetchAsDataUri($src)) !== null) {
                return str_replace($s[1], $encoded, $tag);
            }

            return ''; // внешняя картинка недоступна / относительный путь — убираем
        }, $html) ?? $html;
    }

    private function stripImages(string $html): string
    {
        return preg_replace('/<img\b[^>]*>/i', '', $html) ?? $html;
    }

    /** Скачивает картинку и возвращает её как data-URI, либо null при любой неудаче. */
    private function fetchAsDataUri(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $bytes = $response->body();
            $info  = @getimagesizefromstring($bytes);

            if ($info === false || ! in_array($info['mime'] ?? '', self::SUPPORTED_IMAGE_MIME, true)) {
                return null;
            }

            return 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
