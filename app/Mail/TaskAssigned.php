<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Письмо о поставленной задаче — замена задачи в Битрикс24.
 *
 * Постановщик — инициатор процесса. Отправителем он быть не может: SMTP шлёт письма
 * от служебного ящика, и чужой From на mail.ru не пройдёт проверку. Поэтому инициатор
 * подставляется в Reply-To и явно назван в теле письма — ответ уходит ему, а не в
 * служебный ящик.
 */
class TaskAssigned extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $assignee,
        public ?User $assigner,
        public string $taskTitle,
        public string $taskDescription,
        public ?string $url = null,
        public ?\DateTimeInterface $deadline = null,
        public ?string $context = null,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->assigner && $this->assigner->email && ! str_ends_with($this->assigner->email, '@noemail.local')
            ? [new Address($this->assigner->email, $this->assigner->name)]
            : [];

        return new Envelope(subject: $this->taskTitle, replyTo: $replyTo);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-assigned');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Не удалось отправить письмо о задаче', [
            'assignee_id' => $this->assignee->id,
            'email'       => $this->assignee->email,
            'task'        => $this->taskTitle,
            'error'       => $e->getMessage(),
        ]);
    }
}
