<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public NotificationLog $notification,
        public User $user
    ) {}

    public function handle(): void
    {
        try {
            if ($this->user->notification_email && $this->user->email) {
                Mail::raw(
                    $this->notification->body,
                    fn($message) => $message
                        ->to($this->user->email)
                        ->subject($this->notification->title)
                );
            }
        } catch (\Exception $e) {
            Log::error('SendApprovalNotification failed: ' . $e->getMessage());
        }
    }
}
