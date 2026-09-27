<?php

namespace App\Services;

use App\Mail\TaskAssigned;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Единая точка постановки задачи: создаёт запись в «Моих задачах» и ставит в очередь
 * письмо исполнителю. Раньше на этом же месте задача дублировалась в Битрикс24 —
 * теперь вместо неё уходит почта, а постановщиком значится инициатор процесса.
 */
class TaskDispatchService
{
    /**
     * @param array $attributes поля Task (document_id / order_id / stage / deadline_at …)
     * @param User|null $assigner постановщик — инициатор документа, приказа или заявки
     */
    public function dispatchTask(User $assignee, ?User $assigner, array $attributes, ?string $url = null, ?string $context = null): Task
    {
        $task = Task::create(array_merge($attributes, [
            'assignee_id' => $assignee->id,
            'created_by'  => $assigner?->id,
            'status'      => $attributes['status'] ?? 'pending',
        ]));

        $this->mail($assignee, $assigner, $task->title, (string) $task->description, $url, $task->deadline_at, $context);

        return $task;
    }

    /**
     * Письмо о задаче без записи в «Моих задачах» — для заданий, которые живут в своём
     * разделе (командировки, отпуска), но для исполнителя это такая же поставленная работа.
     */
    public function mail(
        User $assignee,
        ?User $assigner,
        string $title,
        string $description = '',
        ?string $url = null,
        ?\DateTimeInterface $deadline = null,
        ?string $context = null,
    ): void {
        // Уважаем ту же настройку, что и остальные письма, и не пишем на технические адреса,
        // которыми Bitrix24Service затыкает пользователей без почты.
        if (! $assignee->notification_email || ! $assignee->email || str_ends_with($assignee->email, '@noemail.local')) {
            return;
        }

        Mail::to($assignee->email)->queue(
            (new TaskAssigned($assignee, $assigner, $title, $description, $url, $deadline, $context))
                ->onQueue('notifications')
        );
    }
}
