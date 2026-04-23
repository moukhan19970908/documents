<?php

namespace App\Services;

use App\Jobs\SendApprovalNotification;
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
        ];

        $bodies = [
            'new_document'      => 'Новый документ на согласование: ' . ($data['title'] ?? ''),
            'document_rejected' => 'Ваш документ отклонён: ' . ($data['title'] ?? '') . '. Причина: ' . ($data['comment'] ?? ''),
            'deadline_soon'     => 'Срок согласования истекает через 2 часа: ' . ($data['title'] ?? ''),
            'document_approved' => 'Документ согласован: ' . ($data['title'] ?? ''),
            'delegated_to_you'  => 'Вам делегировано согласование: ' . ($data['title'] ?? ''),
        ];

        $notification = NotificationLog::create([
            'user_id' => $user->id,
            'type'    => $type,
            'title'   => $titles[$type] ?? $type,
            'body'    => $bodies[$type] ?? '',
            'data'    => $data,
            'channel' => 'push',
        ]);

        SendApprovalNotification::dispatch($notification, $user)->onQueue('notifications');
    }

    public function notifyMany(iterable $users, string $type, array $data): void
    {
        foreach ($users as $user) {
            $this->notify($user, $type, $data);
        }
    }
}
