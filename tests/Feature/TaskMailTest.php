<?php

namespace Tests\Feature;

use App\Mail\TaskAssigned;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TaskDispatchService;
use App\Jobs\SendApprovalNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Вместо задачи в Битрикс24 исполнителю уходит письмо. Постановщик — инициатор
 * процесса; письмо отправляется через очередь.
 */
class TaskMailTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'is_active'          => true,
            'notification_email' => true,
        ], $attributes));
    }

    public function test_dispatching_a_task_stores_the_assigner_and_queues_a_mail(): void
    {
        Mail::fake();

        $assignee = $this->user(['email' => 'executor@vamin.local']);
        $assigner = $this->user(['email' => 'boss@vamin.local', 'name' => 'Мингазов Азат']);

        $task = app(TaskDispatchService::class)->dispatchTask($assignee, $assigner, [
            'title'       => 'Согласуйте документ',
            'description' => 'Договор поставки',
            'deadline_at' => now()->addDays(2),
        ], 'https://example.test/documents/1', 'документу «Договор»');

        $this->assertSame($assigner->id, $task->created_by, 'Постановщиком должен быть инициатор.');
        $this->assertSame($assignee->id, $task->assignee_id);
        $this->assertSame('pending', $task->status);

        Mail::assertQueued(TaskAssigned::class, function (TaskAssigned $mail) use ($assignee, $assigner) {
            return $mail->hasTo($assignee->email)
                && $mail->assigner->is($assigner)
                && $mail->taskTitle === 'Согласуйте документ';
        });
    }

    public function test_the_mail_names_the_assigner_and_answers_to_them(): void
    {
        $assignee = $this->user(['email' => 'executor@vamin.local']);
        $assigner = $this->user(['email' => 'boss@vamin.local', 'name' => 'Мингазов Азат']);

        $mail = new TaskAssigned($assignee, $assigner, 'Согласуйте документ', 'Договор поставки',
            'https://example.test/documents/1', now()->addDay(), 'документу «Договор»');

        // Reply-To, а не From: SMTP шлёт от служебного ящика, чужой From не пройдёт.
        $this->assertSame($assigner->email, $mail->envelope()->replyTo[0]->address);

        $rendered = $mail->render();
        $this->assertStringContainsString('Мингазов Азат', $rendered);
        $this->assertStringContainsString('Согласуйте документ', $rendered);
        $this->assertStringContainsString('https://example.test/documents/1', $rendered);
    }

    public function test_no_mail_for_users_who_opted_out_or_have_a_stub_address(): void
    {
        Mail::fake();
        $service = app(TaskDispatchService::class);
        $assigner = $this->user();

        $service->mail($this->user(['notification_email' => false]), $assigner, 'Задача');
        $service->mail($this->user(['email' => 'bitrix24_55@noemail.local']), $assigner, 'Задача');

        Mail::assertNothingQueued();
    }

    /** Контракт notify(..., mail:) — им пользуются все процессы, ставящие задачи. */
    public function test_notify_can_skip_the_mail_but_keeps_the_in_app_record(): void
    {
        Queue::fake();
        $user = $this->user();

        app(NotificationService::class)->notify($user, 'new_document', ['title' => 'Договор'], mail: false);

        Queue::assertNotPushed(SendApprovalNotification::class);
        $this->assertDatabaseHas('notifications_log', ['user_id' => $user->id, 'type' => 'new_document']);

        app(NotificationService::class)->notify($user, 'new_document', ['title' => 'Договор']);
        Queue::assertPushed(SendApprovalNotification::class);
    }

    public function test_a_task_without_an_assigner_still_goes_out(): void
    {
        Mail::fake();

        $task = app(TaskDispatchService::class)->dispatchTask(
            $this->user(['email' => 'executor@vamin.local']), null, ['title' => 'Задача'],
        );

        $this->assertNull($task->created_by);
        Mail::assertQueued(TaskAssigned::class);
    }
}
