<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\DocumentApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(private NotificationService $notifications) {}

    public function createForProcess(DocumentApproval $approval): Chat
    {
        $existing = Chat::where('document_approval_id', $approval->id)->first();
        if ($existing) {
            return $existing;
        }

        $chat = Chat::create([
            'document_id'          => $approval->document_id,
            'document_approval_id' => $approval->id,
        ]);

        // Collect participant IDs: initiator + all approvers from all stages
        $approval->loadMissing(['document', 'stages.workflowStage.approvers']);

        $participantIds = collect([$approval->document->initiator_id]);

        foreach ($approval->stages as $stage) {
            $approverIds = $stage->workflowStage->approvers->pluck('approver_id');
            $participantIds = $participantIds->merge($approverIds);
        }

        $this->addParticipants($chat, $participantIds->unique()->values()->all());

        return $chat;
    }

    public function addParticipants(Chat $chat, array $userIds): void
    {
        $existing = $chat->participants()->pluck('users.id')->all();
        $new = array_diff($userIds, $existing);
        if ($new) {
            $chat->participants()->attach($new);
        }
    }

    public function sendMessage(Chat $chat, User $sender, string $body, ?array $attachment = null): ChatMessage
    {
        $message = $chat->messages()->create([
            'user_id'   => $sender->id,
            'body'      => $body,
            'file_path' => $attachment['file_path'] ?? null,
            'file_name' => $attachment['file_name'] ?? null,
            'file_size' => $attachment['file_size'] ?? null,
            'mime_type' => $attachment['mime_type'] ?? null,
        ]);

        $message->setRelation('user', $sender);

        broadcast(new MessageSent($message))->toOthers();

        // Notify every other participant (in-page toast + Web Push).
        $url     = $chat->document_id ? route('documents.show', $chat->document_id) : null;
        $preview = $body !== '' ? $body : '📎 ' . ($attachment['file_name'] ?? 'файл');
        $recipients = $chat->participants()->where('users.id', '!=', $sender->id)->pluck('users.id');
        foreach ($recipients as $recipientId) {
            $this->notifications->pushToBrowser(
                (int) $recipientId,
                'new_message',
                'Новое сообщение',
                $sender->name . ': ' . Str::limit($preview, 80),
                $url,
            );
        }

        return $message;
    }

    /**
     * Mark all messages in the chat as read for the given user
     * (only messages from other users that haven't been read yet).
     */
    public function markAsRead(Chat $chat, User $user): void
    {
        $unreadIds = $chat->messages()
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        if ($unreadIds->isEmpty()) {
            return;
        }

        $now = now()->toDateTimeString();
        $rows = $unreadIds->map(fn($id) => [
            'chat_message_id' => $id,
            'user_id'         => $user->id,
            'read_at'         => $now,
        ])->all();

        DB::table('chat_message_reads')->insertOrIgnore($rows);
    }

    /**
     * Count unread messages in a chat for a given user.
     */
    public function unreadCount(Chat $chat, User $user): int
    {
        return $chat->messages()
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))
            ->count();
    }
}

