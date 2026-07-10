<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class DocumentCommentPosted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public User $user,
        public ?string $comment = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('document.' . $this->document->id)];
    }

    public function broadcastAs(): string
    {
        return 'CommentPosted';
    }

    public function broadcastWith(): array
    {
        return [
            'user'    => ['id' => $this->user->id, 'name' => $this->user->name],
            'excerpt' => Str::limit($this->comment ?? '', 80),
        ];
    }
}
