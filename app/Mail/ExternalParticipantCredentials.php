<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExternalParticipantCredentials extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $password,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Доступ к системе документооборота Vamin');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.external-participant-credentials');
    }

    /**
     * Вызывается очередью, если письмо так и не удалось отправить.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Не удалось отправить письмо с доступом внешнему участнику', [
            'user_id' => $this->user->id,
            'email'   => $this->user->email,
            'error'   => $e->getMessage(),
        ]);
    }
}
