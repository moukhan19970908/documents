<?php

namespace App\Console\Commands;

use App\Models\RequestFlow;
use App\Models\VacationRequest;
use App\Services\RequestFlowExecutor;
use App\Services\TripTaskService;
use Illuminate\Console\Command;

/**
 * Дозаполняет задания у отпусков, согласованных до того, как VacationService научился
 * их порождать. План у таких заявок не сохранён, поэтому он восстанавливается прогоном
 * ТЕКУЩЕГО опубликованного графа: если граф с тех пор правили, задания будут по новой
 * редакции. Идемпотентна — заявки с заданиями пропускаются.
 */
class VacationTasksBackfill extends Command
{
    protected $signature = 'vacations:backfill-tasks {--dry-run : Только показать, ничего не создавать}';

    protected $description = 'Создать задания графа у уже согласованных отпусков';

    public function handle(RequestFlowExecutor $executor, TripTaskService $tasks): int
    {
        $flow = RequestFlow::where('request_type', 'vacation')->where('status', 'published')->first();

        if (! $flow) {
            $this->error('Опубликованного граф-процесса для отпусков нет — восстанавливать задания не из чего.');

            return self::FAILURE;
        }

        $dryRun  = $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        VacationRequest::whereIn('status', ['approved', 'in_registry'])
            ->whereDoesntHave('tasks')
            ->with('user', 'route')
            ->orderBy('id')
            ->chunkById(100, function ($vacations) use ($executor, $tasks, $flow, $dryRun, &$created, &$skipped) {
                foreach ($vacations as $vacation) {
                    // Заявка шла по графу, если её маршрут — одноразовый (материализованный).
                    if (! $vacation->route?->is_ephemeral || ! $vacation->user) {
                        $skipped++;
                        continue;
                    }

                    $plan = $executor->resolve($flow, $vacation->user, [
                        'vacation_type' => $vacation->vacation_type,
                    ]);

                    if (empty($plan['tasks'])) {
                        $skipped++;
                        continue;
                    }

                    $titles = implode(', ', array_column($plan['tasks'], 'title'));
                    $this->line("V-{$vacation->id} ({$vacation->user->name}): {$titles}");

                    if (! $dryRun) {
                        $vacation->update(['flow_tasks' => $plan['tasks']]);
                        $tasks->generateFromFlow($vacation->fresh());
                    }

                    $created++;
                }
            });

        $this->newLine();
        $this->info($dryRun
            ? "Будет обработано заявок: {$created}. Пропущено: {$skipped}. Ничего не создано (--dry-run)."
            : "Обработано заявок: {$created}. Пропущено: {$skipped}.");

        return self::SUCCESS;
    }
}
