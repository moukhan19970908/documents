<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->message->chat_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->message->id,
            'body'       => $this->message->body,
            'created_at' => $this->message->created_at->toISOString(),
            'user'       => [
                'id'   => $this->message->user->id,
                'name' => $this->message->user->name,
            ],
        ];
    }
}
