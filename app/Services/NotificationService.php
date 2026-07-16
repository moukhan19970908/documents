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
            'trip_approval'     => isset($data['trip_id']) ? route('trips.show', $data['trip_id']) : null,
            'vacation_approval' => isset($data['vacation_id']) ? route('vacations.show', $data['vacation_id']) : null,
            'order_published', 'order_ack_reminder', 'order_approval', 'order_rejected' => isset($data['order_id']) ? route('orders.show', $data['order_id']) : null,
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
