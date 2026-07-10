<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotification implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $type,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'notify';
    }

    public function broadcastWith(): array
    {
        return [
            'type'  => $this->type,
            'title' => $this->title,
            'body'  => $this->body,
            'url'   => $this->url,
        ];
    }
}
