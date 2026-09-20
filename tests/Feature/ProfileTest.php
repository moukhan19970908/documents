<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'agreement_accepted_at' => now(),
        ], $attributes));
    }

    public function test_user_updates_name_phone_and_email(): void
    {
        $user = $this->user(['name' => 'Мингазов Азат', 'email' => 'old@vamin.local']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name'  => 'Мингазов Азат Ильдарович',
            'phone' => '+7 900 111-22-33',
            'email' => 'new@vamin.local',
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Мингазов Азат Ильдарович', $user->name);
        $this->assertSame('+7 900 111-22-33', $user->phone);
        $this->assertSame('new@vamin.local', $user->email);
    }

    public function test_email_must_stay_unique(): void
    {
        $this->user(['email' => 'taken@vamin.local']);
        $user = $this->user(['email' => 'mine@vamin.local']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name'  => $user->name,
            'email' => 'taken@vamin.local',
        ])->assertSessionHasErrors('email');

        $this->assertSame('mine@vamin.local', $user->refresh()->email);
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $user = $this->user(['password' => Hash::make('secret-123')]);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password'      => 'wrong-one',
            'password'              => 'brand-new-456',
            'password_confirmation' => 'brand-new-456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('secret-123', $user->refresh()->password));
    }

    public function test_user_changes_password(): void
    {
        $user = $this->user(['password' => Hash::make('secret-123')]);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password'      => 'secret-123',
            'password'              => 'brand-new-456',
            'password_confirmation' => 'brand-new-456',
        ])->assertRedirect(route('profile.password'));

        $this->assertTrue(Hash::check('brand-new-456', $user->refresh()->password));
    }

    public function test_bitrix_user_without_password_sets_one_without_confirming(): void
    {
        $user = $this->user(['password' => null, 'bitrix24_id' => '42']);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'password'              => 'brand-new-456',
            'password_confirmation' => 'brand-new-456',
        ])->assertRedirect(route('profile.password'));

        $this->assertTrue(Hash::check('brand-new-456', $user->refresh()->password));
    }

    public function test_both_tabs_render(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Мой профиль')->assertSee('ФИО');
        $this->actingAs($user)->get(route('profile.password'))->assertOk()->assertSee('Новый пароль');
    }
}
