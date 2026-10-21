<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackHandler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Обработчики обращений тех поддержки: назначаются в админке и этим получают доступ
 * ко всем обращениям, не дожидаясь правки матрицы прав, плюс счётчик новых в меню.
 */
class FeedbackHandlersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL проекта содержит подпуть (/documents/public) — с ним тестовый запрос
        // не попадает в маршрут. Для теста ходим по корню.
        URL::forceRootUrl('http://localhost');
    }

    public function test_assigned_handler_sees_a_request_from_another_employee(): void
    {
        $handler = $this->employee();
        FeedbackHandler::create(['user_id' => $handler->id]);
        $this->requestFrom($this->employee(), 'Не открывается документ');

        $this->actingAs($handler)->get(route('feedback.index'))->assertSee('Не открывается документ');
    }

    public function test_employee_without_assignment_does_not_see_a_request_from_another_employee(): void
    {
        $this->requestFrom($this->employee(), 'Не открывается документ');

        $this->actingAs($this->employee())->get(route('feedback.index'))->assertDontSee('Не открывается документ');
    }

    public function test_menu_counter_shows_new_requests_to_an_assigned_handler(): void
    {
        $handler = $this->employee();
        FeedbackHandler::create(['user_id' => $handler->id]);
        $this->requestFrom($this->employee(), 'Не открывается документ');

        $this->assertSame(1, $this->menuCounterFor($handler));
    }

    public function test_menu_counter_stays_empty_for_an_employee_without_assignment(): void
    {
        $this->requestFrom($this->employee(), 'Не открывается документ');

        $this->assertSame(0, $this->menuCounterFor($this->employee()));
    }

    public function test_admin_assigns_a_handler_from_the_settings_page(): void
    {
        $handler = $this->employee();

        $this->actingAs($this->admin())
            ->put(route('admin.feedback.settings.update'), ['users' => [$handler->id]]);

        $this->assertSame([$handler->id], FeedbackHandler::userIds());
    }

    public function test_saving_the_settings_page_removes_a_handler_left_out_of_the_list(): void
    {
        $dropped = $this->employee();
        FeedbackHandler::create(['user_id' => $dropped->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.feedback.settings.update'), ['users' => []]);

        $this->assertSame([], FeedbackHandler::userIds());
    }

    private function employee(): User
    {
        return User::factory()->create([
            'role'                  => 'linear',
            'is_active'             => true,
            'agreement_accepted_at' => now(),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role'                  => 'admin',
            'is_active'             => true,
            'agreement_accepted_at' => now(),
        ]);
    }

    private function requestFrom(User $author, string $subject): Feedback
    {
        return Feedback::create([
            'user_id'  => $author->id,
            'category' => 'bug',
            'subject'  => $subject,
            'body'     => 'Текст обращения',
            'status'   => 'new',
        ]);
    }

    /** Значение счётчика «Тех Поддержка» в боковом меню для этого пользователя. */
    private function menuCounterFor(User $user): int
    {
        $this->actingAs($user);

        $view = view('layouts.app');
        View::callComposer($view);

        return $view->getData()['menuNewFeedback'];
    }
}
