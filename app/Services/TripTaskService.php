<?php

namespace App\Services;

use App\Models\TripRequest;
use App\Models\TripTask;
use App\Models\TripTaskFile;
use App\Models\TripTaskSetting;
use App\Models\User;
use App\Models\VacationRequest;

/**
 * Порождаемые задания заявок (ТЗ 18.3): командировок — по правилам 18.3 или по графу,
 * отпусков — только по графу. Живут в разделе «Заявки», привязаны к заявке.
 * Результат возвращается ИНИЦИАТОРУ заявки.
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

    /**
     * Запасной способ: ключ исполнителя из графа → поле настроек TripTaskSetting.
     * Нужен только для ключей, которым не соответствует роль в справочнике
     * (офис-менеджер, логистика, транспорт).
     */
    private const FLOW_ASSIGNEE = [
        'hr'             => 'hr_user_id',
        'office_manager' => 'office_manager_id',
        'logistics'      => 'logistics_id',
        'transport'      => 'transport_id',
    ];

    /**
     * Исполнитель задания из графа. Ключ узла — это код роли: берём активного носителя
     * роли, как узел «Согласующий (роль)». Если роли с таким кодом нет, падаем на
     * прежние настройки исполнителей командировок.
     */
    private function resolveAssignee(string $key, TripTaskSetting $settings): ?User
    {
        if ($key === '') {
            return null;
        }

        $byRole = User::where('is_active', true)
            ->where(fn ($q) => $q->where('role', $key)->orWhereHas('roles', fn ($r) => $r->where('code', $key)))
            ->orderBy('id')
            ->first();

        if ($byRole) {
            return $byRole;
        }

        $field = self::FLOW_ASSIGNEE[$key] ?? null;

        return $field ? User::find($settings->{$field} ?? null) : null;
    }

    /**
     * Породить задания из плана граф-процесса (узлы «Задание»/«Параллель») для
     * командировки или отпуска. Идемпотентно.
     */
    public function generateFromFlow(TripRequest|VacationRequest $request): void
    {
        $isVacation = $request instanceof VacationRequest;

        if (($isVacation ? $request->tasks() : $request->tripTasks())->exists()) {
            return;
        }

        $settings = TripTaskSetting::current();

        foreach (($request->flow_tasks ?? []) as $t) {
            $assignee = $this->resolveAssignee((string) ($t['assignee'] ?? ''), $settings);

            $task = TripTask::create([
                'trip_request_id'     => $isVacation ? null : $request->id,
                'vacation_request_id' => $isVacation ? $request->id : null,
                'target'              => $t['assignee'] ?? 'flow',
                'title'               => $t['title'] ?? 'Задание',
                'assignee_id'         => $assignee?->id,
                'status'              => 'pending',
            ]);

            if ($assignee) {
                $this->notifications->notify($assignee, 'trip_task_assigned', [
                    'title'   => $task->title,
                    'trip_id' => $isVacation ? null : $request->id,
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

        // Результат возвращается инициатору заявки (ТЗ 18.3) — командировки или отпуска.
        if ($initiator = $task->initiator()) {
            $this->notifications->notify($initiator, 'trip_task_done', [
                'title' => $task->title, 'trip_id' => $task->trip_request_id,
            ]);
        }
    }
}
