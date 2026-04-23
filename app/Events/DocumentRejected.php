<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public User $rejectedBy,
        public ?string $comment = null
    ) {}
}
