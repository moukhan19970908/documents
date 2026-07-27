<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Document;
use App\Models\Order;
use App\Services\ArchiveService;
use Illuminate\Console\Command;

class ArchiveBackfill extends Command
{
    protected $signature = 'archive:backfill {--chunk=100 : Размер пачки}';

    protected $description = 'Заархивировать уже завершённые документы, приказы и поручения в неизменяемый архив';

    public function handle(ArchiveService $archive): int
    {
        $created = 0;
        $skipped = 0;
        $failed = 0;
        $chunk = (int) $this->option('chunk');

        $tally = function ($record) use (&$created, &$skipped) {
            $record->wasRecentlyCreated ? $created++ : $skipped++;
        };

        // Документы: завершённые процессы.
        Document::whereIn('status', ['approved', 'signed', 'archived'])->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($archive, $tally, &$failed) {
                foreach ($rows as $r) {
                    try { $tally($archive->archiveDocument($r)); }
                    catch (\Throwable $e) { $failed++; $this->warn("Документ {$r->id}: {$e->getMessage()}"); }
                }
            });

        // Приказы: опубликованные, где все ознакомились.
        Order::where('status', 'published')->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($archive, $tally, &$failed) {
                foreach ($rows as $r) {
                    if (! $r->ackCompleted()) { continue; }
                    try { $tally($archive->archiveOrder($r)); }
                    catch (\Throwable $e) { $failed++; $this->warn("Приказ {$r->id}: {$e->getMessage()}"); }
                }
            });

        // Поручения: закрытые корневые дела.
        Assignment::where('status', 'done')->whereNull('parent_id')->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($archive, $tally, &$failed) {
                foreach ($rows as $r) {
                    try { $tally($archive->archiveAssignment($r)); }
                    catch (\Throwable $e) { $failed++; $this->warn("Поручение {$r->id}: {$e->getMessage()}"); }
                }
            });

        $this->info("Готово. Заархивировано: {$created}. Уже в архиве: {$skipped}. Ошибок: {$failed}.");

        return self::SUCCESS;
    }
}
