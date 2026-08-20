<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Впечатывает штамп-удостоверение «Документ согласован» в байты скачиваемого файла.
 *
 * PDF: штамп-оверлей генерируется чистым PHP (TCPDF, кириллица) и накладывается на
 * исходник нативной утилитой qpdf — векторный текст оригинала сохраняется, работают
 * любые версии PDF. Растровые картинки (PNG/WebP/GIF) штампуются через GD.
 * Всё, что не поддаётся (JPEG в этой сборке GD, DOCX и т.п.), возвращается без
 * изменений — скачивание не должно ломаться из-за штампа.
 */
class FileWatermarkService
{
    /** MIME, которые умеет открыть и сохранить текущая сборка GD. */
    private const IMAGE_MIME = ['image/png', 'image/webp', 'image/gif'];

    private const A4 = [595.28, 841.89];

    /** Можно ли (и нужно ли) штамповать этот файл. */
    public function canStamp(DocumentFile $file, Document $document): bool
    {
        if (! $this->isCertified($document)) {
            return false;
        }

        $mime = $this->mime($file);

        if ($mime === 'application/pdf') {
            return $this->qpdfBin() !== null;
        }

        return in_array($mime, self::IMAGE_MIME, true) && $this->fontPath() !== null;
    }

    /** Возвращает байты со штампом либо исходные — если формат не поддержан или что-то пошло не так. */
    public function stamp(string $bytes, DocumentFile $file, Document $document): string
    {
        if (! $this->canStamp($file, $document)) {
            return $bytes;
        }

        try {
            $mime = $this->mime($file);

            return $mime === 'application/pdf'
                ? ($this->stampPdf($bytes, $document) ?? $bytes)
                : ($this->stampImage($bytes, $mime, $document) ?? $bytes);
        } catch (\Throwable $e) {
            Log::warning("Watermark: файл {$file->id} не проштампован: {$e->getMessage()}");

            return $bytes;
        }
    }

    private function isCertified(Document $document): bool
    {
        return in_array($document->status, ['approved', 'signed', 'archived'], true);
    }

    private function mime(DocumentFile $file): string
    {
        return strtolower((string) $file->mime_type);
    }

    // ── PDF (TCPDF + qpdf) ───────────────────────────────────────────────────

    private function stampPdf(string $bytes, Document $document): ?string
    {
        $qpdf = $this->qpdfBin();
        if (! $qpdf) {
            return null;
        }

        $in    = tempnam(sys_get_temp_dir(), 'wm_in_');
        $stamp = tempnam(sys_get_temp_dir(), 'wm_st_');
        $out   = tempnam(sys_get_temp_dir(), 'wm_out_');
        @unlink($out); // qpdf создаёт файл вывода сам

        try {
            file_put_contents($in, $bytes);

            $pages = min(3000, max(1, $this->pdfPageCount($qpdf, $in)));
            [$w, $h] = $this->pdfPageSize($qpdf, $in, $bytes);

            file_put_contents($stamp, $this->buildStampPdf($this->stampLines($document), $w, $h, $pages));

            $proc = new Process([$qpdf, '--overlay', $stamp, '--', $in, $out]);
            $proc->setTimeout(60);
            $proc->run();

            if (! $proc->isSuccessful() || ! is_file($out) || filesize($out) === 0) {
                Log::warning('Watermark: qpdf overlay не удался: ' . trim($proc->getErrorOutput() ?: $proc->getOutput()));

                return null;
            }

            return file_get_contents($out) ?: null;
        } finally {
            foreach ([$in, $stamp, $out] as $tmp) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }

    private function pdfPageCount(string $qpdf, string $in): int
    {
        $proc = new Process([$qpdf, '--show-npages', $in]);
        $proc->setTimeout(30);
        $proc->run();

        return $proc->isSuccessful() ? (int) trim($proc->getOutput()) : 1;
    }

    /** @return array{0: float, 1: float} размер страницы в пунктах [ширина, высота] */
    private function pdfPageSize(string $qpdf, string $in, string $bytes): array
    {
        // 1) MediaBox прямо в байтах (несжатые/простые PDF).
        if ($box = $this->matchMediaBox($bytes)) {
            return $box;
        }

        // 2) Раскодируем структуру через qpdf и ищем MediaBox в начале QDF.
        try {
            $qdf = tempnam(sys_get_temp_dir(), 'wm_qdf_');
            $proc = new Process([$qpdf, '--qdf', '--object-streams=disable', $in, $qdf]);
            $proc->setTimeout(60);
            $proc->run();

            if ($proc->isSuccessful() && is_file($qdf)) {
                $head = (string) file_get_contents($qdf, false, null, 0, 524288);
                @unlink($qdf);
                if ($box = $this->matchMediaBox($head)) {
                    return $box;
                }
            }
            @unlink($qdf);
        } catch (\Throwable $e) {
            // молча падаем к A4
        }

        return self::A4;
    }

    /** @return array{0: float, 1: float}|null */
    private function matchMediaBox(string $s): ?array
    {
        if (preg_match('/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/', $s, $m)) {
            $w = (float) $m[3] - (float) $m[1];
            $h = (float) $m[4] - (float) $m[2];
            if ($w > 1 && $h > 1) {
                return [$w, $h];
            }
        }

        return null;
    }

    /** Прозрачная страница-оверлей со штампом; повторяется на каждую страницу документа. */
    private function buildStampPdf(array $lines, float $w, float $h, int $pages): string
    {
        $pdf = new \TCPDF('P', 'pt', [$w, $h], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);

        for ($p = 0; $p < $pages; $p++) {
            $pdf->AddPage('P', [$w, $h]);

            // Диагональный водяной знак «СОГЛАСОВАНО».
            $big = max(24.0, $w / 9);
            $pdf->SetFont('dejavusans', 'B', $big);
            $pdf->SetTextColor(10, 125, 60);
            $pdf->SetAlpha(0.10);
            $text = 'СОГЛАСОВАНО';
            $tw = $pdf->GetStringWidth($text);
            $pdf->StartTransform();
            $pdf->Rotate(28, $w / 2, $h / 2);
            $pdf->Text($w / 2 - $tw / 2, $h / 2 - $big / 2, $text);
            $pdf->StopTransform();
            $pdf->SetAlpha(1);

            // Компактный штамп-бокс в нижнем правом углу.
            $fs  = max(8.0, $w / 58);
            $pad = $fs;
            $lh  = $fs * 1.5;
            $pdf->SetFont('dejavusans', '', $fs);
            $boxW = 0;
            foreach ($lines as $ln) {
                $boxW = max($boxW, $pdf->GetStringWidth($ln));
            }
            $boxW += $pad * 2;
            $boxH = $lh * count($lines) + $pad * 1.4;
            $x0 = $w - $boxW - $pad;
            $y0 = $h - $boxH - $pad;

            $pdf->SetAlpha(0.9);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(10, 125, 60);
            $pdf->SetLineWidth(1.4);
            $pdf->Rect($x0, $y0, $boxW, $boxH, 'DF');
            $pdf->SetAlpha(1);

            $pdf->SetTextColor(10, 125, 60);
            $y = $y0 + $pad * 0.7;
            $first = true;
            foreach ($lines as $ln) {
                $pdf->SetFont('dejavusans', $first ? 'B' : '', $fs);
                $pdf->Text($x0 + $pad, $y, $ln);
                $y += $lh;
                $first = false;
            }
        }

        return $pdf->Output('stamp.pdf', 'S');
    }

    // ── Картинки (GD) ────────────────────────────────────────────────────────

    private function stampImage(string $bytes, string $mime, Document $document): ?string
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, true);

        $w = imagesx($img);
        $h = imagesy($img);
        $font = $this->fontPath();

        $green = imagecolorallocate($img, 10, 125, 60);
        $faint = imagecolorallocatealpha($img, 10, 125, 60, 100);

        // 1) Крупная полупрозрачная надпись «СОГЛАСОВАНО» по диагонали через центр.
        $big  = max(14, (int) ($w / 12));
        $text = 'СОГЛАСОВАНО';
        $bb   = imagettfbbox($big, 30, $font, $text);
        $tw   = abs($bb[2] - $bb[0]);
        $thh  = abs($bb[5] - $bb[1]);
        imagettftext($img, $big, 30, (int) (($w - $tw) / 2), (int) (($h + $thh) / 2), $faint, $font, $text);

        // 2) Компактный штамп-бокс в нижнем правом углу.
        $lines = $this->stampLines($document);
        $fs    = max(9, (int) ($w / 55));
        $pad   = (int) ($fs * 0.9);
        $lineH = (int) ($fs * 1.55);

        $boxW = 0;
        foreach ($lines as $ln) {
            $b = imagettfbbox($fs, 0, $font, $ln);
            $boxW = max($boxW, abs($b[2] - $b[0]));
        }
        $boxW += $pad * 2;
        $boxH  = $lineH * count($lines) + $pad * 2;

        $x0 = max($pad, $w - $boxW - $pad);
        $y0 = max($pad, $h - $boxH - $pad);

        $bg = imagecolorallocatealpha($img, 255, 255, 255, 45);
        imagefilledrectangle($img, $x0, $y0, $x0 + $boxW, $y0 + $boxH, $bg);
        imagerectangle($img, $x0, $y0, $x0 + $boxW, $y0 + $boxH, $green);

        $y = $y0 + $pad + $fs;
        foreach ($lines as $ln) {
            imagettftext($img, $fs, 0, $x0 + $pad, $y, $green, $font, $ln);
            $y += $lineH;
        }

        ob_start();
        match ($mime) {
            'image/webp' => imagewebp($img),
            'image/gif'  => imagegif($img),
            default      => imagepng($img),
        };
        $out = ob_get_clean();
        imagedestroy($img);

        return $out ?: null;
    }

    // ── общее ────────────────────────────────────────────────────────────────

    /** Строки штампа — те же данные согласования, что и на листе согласования. */
    private function stampLines(Document $document): array
    {
        $approval = $document->approvals()
            ->with(['stages.decisions.user', 'stages.workflowStage'])
            ->latest()
            ->first();

        $approver = $this->approver($approval);
        $date = optional($approval?->completed_at)->format('d.m.Y')
            ?: optional($document->registered_at)->format('d.m.Y');

        return [
            'ДОКУМЕНТ СОГЛАСОВАН',
            'Лист согл. № ' . ($approval?->id ?? '—') . '   ·   ' . ($date ?: '—'),
            'Утвердил: ' . ($approver?->name ?? '—'),
            'ID ' . $document->id . ($document->number ? '   ·   ' . $document->number : ''),
            'Действителен, изменения запрещены',
        ];
    }

    /** «Утвердил»: решение в фазе утверждения, иначе последнее «Согласовать» по маршруту. */
    private function approver(?DocumentApproval $approval)
    {
        if (! $approval) {
            return null;
        }

        $decision = null;
        foreach ($approval->stages->sortByDesc(fn ($s) => $s->workflowStage?->sort_order ?? 0) as $s) {
            if (($s->workflowStage?->phase) === 'approve') {
                $decision = $s->decisions->where('action', 'approve')->sortByDesc('decided_at')->first();
                if ($decision) {
                    break;
                }
            }
        }

        $decision ??= $approval->stages
            ->flatMap(fn ($s) => $s->decisions)
            ->where('action', 'approve')
            ->sortByDesc('decided_at')
            ->first();

        return $decision?->user;
    }

    /** Первый доступный Unicode-TTF (нужен для кириллицы в GD). На проде-Linux — DejaVu. */
    private function fontPath(): ?string
    {
        static $resolved = false;
        static $path = null;

        if ($resolved) {
            return $path;
        }
        $resolved = true;

        foreach ([
            storage_path('fonts/DejaVuSans.ttf'),
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/Library/Fonts/Arial.ttf',
        ] as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        return $path;
    }

    /** Путь к qpdf: конфиг/ENV → бандл в storage/tools → qpdf в PATH. */
    private function qpdfBin(): ?string
    {
        static $resolved = false;
        static $bin = null;

        if ($resolved) {
            return $bin;
        }
        $resolved = true;

        $candidates = array_filter([
            config('watermark.qpdf'),
            storage_path('tools/qpdf/bin/qpdf.exe'),
            storage_path('tools/qpdf/bin/qpdf'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $bin = $candidate;
            }
        }

        if ($this->commandAvailable('qpdf')) {
            return $bin = 'qpdf';
        }

        return $bin;
    }

    private function commandAvailable(string $cmd): bool
    {
        try {
            $proc = new Process([$cmd, '--version']);
            $proc->setTimeout(5);
            $proc->run();

            return $proc->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
