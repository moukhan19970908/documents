<?php

namespace App\Services;

use App\Events\UserNotification;
use App\Jobs\SendApprovalNotification;
use App\Jobs\SendWebPush;
use App\Models\NotificationLog;
use App\Models\User;

class NotificationService
{
    public function notify(User $user, string $type, array $data): void
    {
        $titles = [
            'new_document'      => 'Новый документ на согласование',
            'document_rejected' => 'Документ отклонён',
            'deadline_soon'     => 'Срок согласования истекает',
            'document_approved' => 'Документ согласован',
            'delegated_to_you'  => 'Вам делегировано согласование',
            'trip_approval'     => 'Новое согласование командировки',
            'vacation_approval' => 'Новое согласование отпуска',
            'order_published'   => 'Новый приказ для ознакомления',
            'order_ack_reminder' => 'Напоминание об ознакомлении',
            'order_approval'    => 'Приказ на согласование',
            'order_rejected'    => 'Приказ отклонён',
            'assignment_created'          => 'Новое поручение',
            'assignment_submitted'        => 'Поручение на приёмке',
            'assignment_accepted'         => 'Поручение принято',
            'assignment_returned'         => 'Поручение возвращено на доработку',
            'assignment_deadline_changed'   => 'Изменён срок поручения',
            'assignment_deadline_requested' => 'Запрос на перенос срока',
            'assignment_deadline_rejected'  => 'Перенос срока отклонён',
            'assignment_control'            => 'Вы назначены контролёром',
            'trip_task_assigned'            => 'Задание по командировке',
            'trip_task_done'                => 'Задание по командировке выполнено',
            'procedure_stage_assigned'      => 'Этап процедуры на вас',
            'procedure_returned'            => 'Процедура возвращена вам',
            'procedure_stopped'             => 'Процедура остановлена',
            'procedure_completed'           => 'Процедура завершена',
            'procedure_task_assigned'       => 'Новая задача по процедуре',
            'procedure_task_submitted'      => 'Задача сдана на приёмку',
            'procedure_task_accepted'       => 'Ваша задача принята',
            'procedure_task_returned'       => 'Задача возвращена на доработку',
            'procedure_task_deadline'       => 'Перенесён срок задачи',
            'inspection_created'            => 'Новая проверка',
            'inspection_submitted'          => 'Акт проверки на приёмке',
            'inspection_accepted'           => 'Акт проверки принят',
            'inspection_returned'           => 'Проверка возвращена на доработку',
        ];

        $bodies = [
            'new_document'      => 'Новый документ на согласование: ' . ($data['title'] ?? ''),
            'document_rejected' => 'Ваш документ отклонён: ' . ($data['title'] ?? '') . '. Причина: ' . ($data['comment'] ?? ''),
            'deadline_soon'     => 'Срок согласования истекает через 2 часа: ' . ($data['title'] ?? ''),
            'document_approved' => 'Документ согласован: ' . ($data['title'] ?? ''),
            'delegated_to_you'  => 'Вам делегировано согласование: ' . ($data['title'] ?? ''),
            'trip_approval'     => 'Заявка на командировку на согласование: ' . ($data['title'] ?? ''),
            'vacation_approval' => 'Заявка на отпуск на согласование: ' . ($data['title'] ?? ''),
            'order_published'   => 'Опубликован приказ для ознакомления: ' . ($data['title'] ?? ''),
            'order_ack_reminder' => 'Ознакомьтесь с приказом: ' . ($data['title'] ?? ''),
            'order_approval'    => 'Приказ на согласование: ' . ($data['title'] ?? ''),
            'order_rejected'    => 'Приказ отклонён и возвращён: ' . ($data['title'] ?? ''),
            'assignment_created'          => 'Вам поставлено поручение: ' . ($data['title'] ?? ''),
            'assignment_submitted'        => 'Исполнитель отчитался, требуется приёмка: ' . ($data['title'] ?? ''),
            'assignment_accepted'         => 'Ваше поручение принято: ' . ($data['title'] ?? ''),
            'assignment_returned'         => 'Поручение возвращено на доработку: ' . ($data['title'] ?? '') . '. Причина: ' . ($data['comment'] ?? ''),
            'assignment_deadline_changed' => 'Перенесён срок поручения «' . ($data['title'] ?? '') . '»: ' . ($data['from'] ?? '') . ' → ' . ($data['to'] ?? '') . '. ' . ($data['comment'] ?? ''),
            'assignment_deadline_requested' => 'Исполнитель просит перенести срок поручения «' . ($data['title'] ?? '') . '». Причина: ' . ($data['comment'] ?? ''),
            'assignment_deadline_rejected'  => 'Постановщик отклонил перенос срока поручения: ' . ($data['title'] ?? ''),
            'assignment_control'            => 'Вы назначены контролёром поручения: ' . ($data['title'] ?? ''),
            'trip_task_assigned'            => 'Новое задание по командировке: ' . ($data['title'] ?? ''),
            'trip_task_done'                => 'Задание по вашей командировке выполнено: ' . ($data['title'] ?? ''),
            'procedure_stage_assigned'      => 'Этап «' . ($data['stage'] ?? '') . '» процедуры «' . ($data['title'] ?? '') . '» ждёт вашего действия',
            'procedure_returned'            => 'Процедура «' . ($data['title'] ?? '') . '» возвращена вам для продолжения',
            'procedure_stopped'             => 'Процедура «' . ($data['title'] ?? '') . '» остановлена на этапе «' . ($data['stage'] ?? '') . '». ' . ($data['comment'] ?? ''),
            'procedure_completed'           => 'Процедура завершена: ' . ($data['title'] ?? ''),
            'procedure_task_assigned'       => 'Вам назначена задача по процедуре: ' . ($data['task'] ?? ''),
            'procedure_task_submitted'      => 'Исполнитель сдал задачу «' . ($data['task'] ?? '') . '» по процедуре «' . ($data['title'] ?? '') . '» — требуется приёмка',
            'procedure_task_accepted'       => 'Инициатор принял вашу задачу: ' . ($data['task'] ?? ''),
            'procedure_task_returned'       => 'Задача «' . ($data['task'] ?? '') . '» возвращена на доработку. Причина: ' . ($data['comment'] ?? ''),
            'procedure_task_deadline'       => 'Перенесён срок задачи «' . ($data['task'] ?? '') . '»: ' . ($data['from'] ?? '—') . ' → ' . ($data['to'] ?? '') . '. ' . ($data['comment'] ?? ''),
            'inspection_created'            => 'Вам назначена проверка: ' . ($data['title'] ?? ''),
            'inspection_submitted'          => 'Исполнитель сдал акт проверки, требуется приёмка: ' . ($data['title'] ?? ''),
            'inspection_accepted'           => 'Ваш акт проверки принят: ' . ($data['title'] ?? ''),
            'inspection_returned'           => 'Проверка возвращена на доработку: ' . ($data['title'] ?? '') . '. Причина: ' . ($data['comment'] ?? ''),
        ];

        $title = $titles[$type] ?? $type;
        $body  = $bodies[$type] ?? '';

        $notification = NotificationLog::create([
            'user_id' => $user->id,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
            'channel' => 'push',
        ]);

        SendApprovalNotification::dispatch($notification, $user)->onQueue('notifications');

        // In-page toast (open tab) + Web Push (works even when the site is closed).
        $this->pushToBrowser($user->id, $type, $title, $body, $this->resolveUrl($type, $data));
    }

    /**
     * Deliver a browser notification without persisting a log or sending email:
     *  - broadcasts over websockets for any open tab (in-page toast);
     *  - queues a Web Push so it also arrives when the site is closed.
     * Used both by notify() and for high-frequency events like chat messages.
     */
    public function pushToBrowser(int $userId, string $type, string $title, string $body, ?string $url = null): void
    {
        event(new UserNotification($userId, $type, $title, $body, $url));

        SendWebPush::dispatch($userId, [
            'type'  => $type,
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
        ])->onQueue('notifications');
    }

    private function resolveUrl(string $type, array $data): ?string
    {
        return match ($type) {
            'trip_approval', 'trip_task_assigned', 'trip_task_done' => isset($data['trip_id']) ? route('trips.show', $data['trip_id']) : null,
            'vacation_approval' => isset($data['vacation_id']) ? route('vacations.show', $data['vacation_id']) : null,
            'order_published', 'order_ack_reminder', 'order_approval', 'order_rejected' => isset($data['order_id']) ? route('orders.show', $data['order_id']) : null,
            'assignment_created', 'assignment_submitted', 'assignment_accepted', 'assignment_returned', 'assignment_deadline_changed', 'assignment_deadline_requested', 'assignment_deadline_rejected', 'assignment_control' => isset($data['assignment_id']) ? route('assignments.show', $data['assignment_id']) : null,
            'procedure_task_assigned', 'procedure_task_submitted', 'procedure_task_accepted', 'procedure_task_returned', 'procedure_task_deadline' => route('procedures.tasks.index'),
            'procedure_stage_assigned', 'procedure_returned', 'procedure_stopped', 'procedure_completed' => isset($data['procedure_id']) ? route('procedures.show', $data['procedure_id']) : null,
            'inspection_created', 'inspection_submitted', 'inspection_accepted', 'inspection_returned' => isset($data['inspection_id']) ? route('inspections.show', $data['inspection_id']) : null,
            default             => isset($data['document_id']) ? route('documents.show', $data['document_id']) : null,
        };
    }

    public function notifyMany(iterable $users, string $type, array $data): void
    {
        foreach ($users as $user) {
            $this->notify($user, $type, $data);
        }
    }
}
