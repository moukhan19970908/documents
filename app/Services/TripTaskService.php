<?php

namespace App\Services;

use App\Models\TripRequest;
use App\Models\TripTask;
use App\Models\TripTaskFile;
use App\Models\TripTaskSetting;
use App\Models\User;

/**
 * Порождаемые задания командировок (ТЗ 18.3). Живут в разделе «Заявки», привязаны к заявке.
 * Результат возвращается ИНИЦИАТОРУ командировки.
 */
class TripTaskService
{
    public function __construct(private NotificationService $notifications) {}

    /** Создать задания по правилам 18.3 (идемпотентно — не дублирует при повторном вызове). */
    public function generateFor(TripRequest $trip): void
    {
        if ($trip->tripTasks()->exists()) {
            return;
        }

        // Всегда: деньги (ОК) и тур (офис-менеджер). По транспорту — одно из двух.
        $targets = ['money', 'tour'];
        if ($trip->transport_type === 'own') {
            $targets[] = 'fuel_card';
        } elseif ($trip->transport_type === 'company') {
            $targets[] = 'service_car';
        }

        $settings = TripTaskSetting::current();

        foreach ($targets as $target) {
            $meta       = TripTask::TARGETS[$target];
            $assigneeId = $settings->{$meta['setting']} ?? null;

            $task = TripTask::create([
                'trip_request_id' => $trip->id,
                'target'          => $target,
                'title'           => $meta['title'],
                'assignee_id'     => $assigneeId,
                'status'          => 'pending',
            ]);

            if ($assigneeId && ($assignee = User::find($assigneeId))) {
                $this->notifications->notify($assignee, 'trip_task_assigned', [
                    'title' => $task->title, 'trip_id' => $trip->id,
                ]);
            }
        }
    }

    /** Ключ исполнителя из графа → поле настроек TripTaskSetting. */
    private const FLOW_ASSIGNEE = [
        'hr'             => 'hr_user_id',
        'office_manager' => 'office_manager_id',
        'logistics'      => 'logistics_id',
        'transport'      => 'transport_id',
    ];

    /** Породить задания из плана граф-процесса (узлы «Задание»/«Параллель»). Идемпотентно. */
    public function generateFromFlow(TripRequest $trip): void
    {
        if ($trip->tripTasks()->exists()) {
            return;
        }

        $settings = TripTaskSetting::current();

        foreach (($trip->flow_tasks ?? []) as $t) {
            $field      = self::FLOW_ASSIGNEE[$t['assignee'] ?? ''] ?? null;
            $assigneeId = $field ? ($settings->{$field} ?? null) : null;

            $task = TripTask::create([
                'trip_request_id' => $trip->id,
                'target'          => $t['assignee'] ?? 'flow',
                'title'           => $t['title'] ?? 'Задание',
                'assignee_id'     => $assigneeId,
                'status'          => 'pending',
            ]);

            if ($assigneeId && ($assignee = User::find($assigneeId))) {
                $this->notifications->notify($assignee, 'trip_task_assigned', [
                    'title' => $task->title, 'trip_id' => $trip->id,
                ]);
            }
        }
    }

    public function take(TripTask $task): void
    {
        $task->update(['status' => 'in_progress']);
    }

    /** Исполнитель завершает задание: комментарий + файлы результата → уведомление инициатору. */
    public function complete(TripTask $task, User $performer, ?string $comment, array $files = []): void
    {
        foreach ($files as $file) {
            TripTaskFile::create([
                'trip_task_id'  => $task->id,
                'uploaded_by'   => $performer->id,
                'original_name' => $file->getClientOriginalName(),
                'path'          => $file->store("trip-tasks/{$task->id}"),
                'size'          => $file->getSize(),
                'mime'          => $file->getClientMimeType(),
            ]);
        }

        $task->update([
            'status'         => 'done',
            'result_comment' => $comment,
            'done_by'        => $performer->id,
            'done_at'        => now(),
        ]);

        // Результат возвращается инициатору заявки (ТЗ 18.3).
        $this->notifications->notify($task->trip->user, 'trip_task_done', [
            'title' => $task->title, 'trip_id' => $task->trip_request_id,
        ]);
    }
}
