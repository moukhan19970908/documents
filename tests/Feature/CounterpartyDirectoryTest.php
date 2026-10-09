<?php

namespace Tests\Feature;

use App\Models\Counterparty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Справочник контрагентов: ИП (12 цифр ИНН) подтягивается из Checko, как и организация,
 * а иностранную компанию вносят вручную — и в справочнике, и прямо из формы документа.
 */
class CounterpartyDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.checko.key' => 'test-key']);
        // Карточку ИП Checko отдаёт только методом entrepreneur — любой другой метод здесь «ошибается».
        Http::fake([
            'api.checko.ru/v2/entrepreneur*' => Http::response([
                'meta' => ['status' => 'ok'],
                'data' => [
                    'ИНН' => '644802303351', 'ФИО' => 'Инназарова Камилла Рамисовна', 'ТипСокр' => 'ИП',
                    'Регион' => ['Наим' => 'Саратовская область'], 'НасПункт' => '',
                    'Контакты' => ['Емэйл' => ['innazarova.kamilla@mail.ru']],
                ],
            ]),
            '*' => Http::response(['meta' => ['status' => 'error', 'message' => 'не тот метод']], 400),
        ]);

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'linear', 'is_active' => true, 'agreement_accepted_at' => now()]);
    }

    public function test_entrepreneur_is_loaded_by_twelve_digit_inn(): void
    {
        $this->actingAs($this->admin)->post(route('counterparties.store'), ['inn' => '644802303351']);

        $this->assertDatabaseHas('counterparties', ['inn' => '644802303351', 'short_name' => 'ИП Инназарова Камилла Рамисовна']);
    }

    public function test_entrepreneur_can_be_added_from_document_form(): void
    {
        $this->actingAs($this->manager())
            ->postJson(route('counterparties.quick'), ['inn' => '644802303351'])
            ->assertJson(['short_name' => 'ИП Инназарова Камилла Рамисовна']);
    }

    public function test_inn_of_eleven_digits_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('counterparties.store'), ['inn' => '64480230335'])
            ->assertSessionHasErrors('inn');
    }

    public function test_foreign_company_is_added_manually_without_inn(): void
    {
        $this->actingAs($this->admin)->post(route('counterparties.manual'), [
            'short_name' => 'Agro Trade LLP', 'legal_address' => 'Казахстан, Алматы',
        ]);

        $this->assertDatabaseHas('counterparties', ['short_name' => 'Agro Trade LLP', 'inn' => null, 'synced_at' => null]);
    }

    public function test_manager_adds_foreign_company_from_document_form(): void
    {
        $this->actingAs($this->manager())
            ->postJson(route('counterparties.manual'), ['short_name' => 'Agro Trade LLP'])
            ->assertJson(['short_name' => 'Agro Trade LLP']);
    }

    private function manualCard(array $attributes = []): Counterparty
    {
        return Counterparty::create($attributes + ['short_name' => 'Agro Trde LLP', 'legal_address' => 'Казахстан, Алматы']);
    }

    private function updatePayload(Counterparty $card, array $changes): array
    {
        return $changes + ['short_name' => $card->short_name, 'inn' => $card->inn, 'legal_address' => $card->legal_address];
    }

    public function test_typo_in_manual_counterparty_name_can_be_fixed(): void
    {
        $card = $this->manualCard();

        $this->actingAs($this->admin)->put(route('counterparties.update', $card), $this->updatePayload($card, ['short_name' => 'Agro Trade LLP']));

        $this->assertSame('Agro Trade LLP', $card->fresh()->short_name);
    }

    public function test_counterparty_from_checko_cannot_be_edited_by_hand(): void
    {
        $card = $this->manualCard(['inn' => '644802303351', 'synced_at' => now()]);

        $this->actingAs($this->admin)
            ->put(route('counterparties.update', $card), $this->updatePayload($card, ['short_name' => 'Другое имя']))
            ->assertStatus(422);
    }

    public function test_editing_keeps_own_inn_without_duplicate_error(): void
    {
        $card = $this->manualCard(['inn' => '644802303351']);

        $this->actingAs($this->admin)
            ->put(route('counterparties.update', $card), $this->updatePayload($card, ['short_name' => 'Agro Trade LLP']))
            ->assertSessionHasNoErrors();
    }

    public function test_user_without_directory_access_cannot_edit_counterparty(): void
    {
        $card = $this->manualCard();

        $this->actingAs($this->manager())
            ->put(route('counterparties.update', $card), $this->updatePayload($card, ['short_name' => 'Agro Trade LLP']))
            ->assertForbidden();
    }

    public function test_manual_counterparty_with_known_inn_is_rejected(): void
    {
        Counterparty::create(['inn' => '644802303351', 'short_name' => 'ИП Инназарова Камилла Рамисовна']);

        $this->actingAs($this->admin)
            ->post(route('counterparties.manual'), ['short_name' => 'Дубль', 'inn' => '644802303351'])
            ->assertSessionHasErrors('inn');
    }
}
