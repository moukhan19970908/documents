<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Checko API (https://checko.ru/integration/api/company): карточка организации по ИНН.
 * Возвращает только поля справочника контрагентов.
 */
class CheckoService
{
    private const URL = 'https://api.checko.ru/v2/company';

    /**
     * @return array{inn: string, short_name: ?string, legal_address: ?string, director_name: ?string,
     *               phones: string[], emails: string[], website: ?string}
     *
     * @throws RuntimeException с сообщением для пользователя
     */
    public function company(string $inn): array
    {
        $key = config('services.checko.key');
        if (! $key) {
            throw new RuntimeException('Не задан ключ Checko API (CHECKO_API_KEY в .env).');
        }

        try {
            $response = Http::timeout(15)->get(self::URL, ['key' => $key, 'inn' => $inn]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Checko недоступен: ' . $e->getMessage());
        }

        $json = $response->json() ?? [];
        if (($json['meta']['status'] ?? null) === 'error' || $response->failed()) {
            throw new RuntimeException('Checko: ' . ($json['meta']['message'] ?? 'ошибка ' . $response->status()));
        }

        $data = $json['data'] ?? [];
        if (empty($data['ИНН'])) {
            throw new RuntimeException("Организация с ИНН {$inn} не найдена.");
        }

        $contacts = $data['Контакты'] ?? [];

        return [
            'inn'           => (string) $data['ИНН'],
            'short_name'    => $data['НаимСокр'] ?? null,
            'legal_address' => $data['ЮрАдрес']['АдресРФ'] ?? null,
            // Руководителей может быть несколько — перечисляем всех.
            'director_name' => collect($data['Руковод'] ?? [])->pluck('ФИО')->filter()->implode(', ') ?: null,
            'phones'        => array_values((array) ($contacts['Тел'] ?? [])),
            'emails'        => array_values((array) ($contacts['Емэйл'] ?? [])),
            'website'       => $contacts['ВебСайт'] ?? null,
        ];
    }
}
