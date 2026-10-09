<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Checko API (https://checko.ru/integration/api): карточка контрагента по ИНН — организации
 * (10 цифр, метод company) или индивидуального предпринимателя (12 цифр, метод entrepreneur).
 * Возвращает только поля справочника контрагентов.
 */
class CheckoService
{
    private const COMPANY_URL = 'https://api.checko.ru/v2/company';

    private const ENTREPRENEUR_URL = 'https://api.checko.ru/v2/entrepreneur';

    /**
     * @return array{inn: string, short_name: ?string, legal_address: ?string, director_name: ?string,
     *               phones: string[], emails: string[], website: ?string}
     *
     * @throws RuntimeException с сообщением для пользователя
     */
    public function findByInn(string $inn): array
    {
        $isEntrepreneur = strlen($inn) === 12;
        $data = $this->request($isEntrepreneur ? self::ENTREPRENEUR_URL : self::COMPANY_URL, $inn);

        if (empty($data['ИНН'])) {
            throw new RuntimeException($isEntrepreneur ? "ИП с ИНН {$inn} не найден." : "Организация с ИНН {$inn} не найдена.");
        }

        return $isEntrepreneur ? $this->entrepreneurFields($data) : $this->companyFields($data);
    }

    private function request(string $url, string $inn): array
    {
        $key = config('services.checko.key');
        if (! $key) {
            throw new RuntimeException('Не задан ключ Checko API (CHECKO_API_KEY в .env).');
        }

        try {
            $response = Http::timeout(15)->get($url, ['key' => $key, 'inn' => $inn]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Checko недоступен: ' . $e->getMessage());
        }

        $json = $response->json() ?? [];
        if (($json['meta']['status'] ?? null) === 'error' || $response->failed()) {
            throw new RuntimeException('Checko: ' . ($json['meta']['message'] ?? 'ошибка ' . $response->status()));
        }

        return $json['data'] ?? [];
    }

    private function companyFields(array $data): array
    {
        return [
            'inn'           => (string) $data['ИНН'],
            'short_name'    => $data['НаимСокр'] ?? null,
            'legal_address' => $data['ЮрАдрес']['АдресРФ'] ?? null,
            // Руководителей может быть несколько — перечисляем всех.
            'director_name' => collect($data['Руковод'] ?? [])->pluck('ФИО')->filter()->implode(', ') ?: null,
        ] + $this->contacts($data);
    }

    /** У ИП нет юрадреса в открытых данных — только регион и населённый пункт; руководитель — он сам. */
    private function entrepreneurFields(array $data): array
    {
        $name = $data['ФИО'] ?? null;

        return [
            'inn'           => (string) $data['ИНН'],
            'short_name'    => $name ? trim(($data['ТипСокр'] ?? 'ИП') . ' ' . $name) : null,
            'legal_address' => collect([$data['Регион']['Наим'] ?? null, $data['НасПункт'] ?? null])->filter()->implode(', ') ?: null,
            'director_name' => $name,
        ] + $this->contacts($data);
    }

    private function contacts(array $data): array
    {
        $contacts = $data['Контакты'] ?? [];

        return [
            'phones'  => array_values((array) ($contacts['Тел'] ?? [])),
            'emails'  => array_values((array) ($contacts['Емэйл'] ?? [])),
            'website' => $contacts['ВебСайт'] ?? null,
        ];
    }
}
