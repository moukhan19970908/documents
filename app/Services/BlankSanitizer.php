<?php

namespace App\Services;

use Mews\Purifier\Facades\Purifier;

/**
 * Очистка HTML бланка. Единая точка, потому что бланк приходит из трёх мест:
 * справочник бланков, создание документа и правка тела на странице документа.
 */
class BlankSanitizer
{
    private const TEMPNAM_WARNING = "tempnam(): file created in the system's temporary directory";

    public function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        // Картинки в бланке лежат как data:-URI. HTMLPurifier проверяет их через
        // tempnam(sys_get_temp_dir()), а PHP 8.4.0–8.4.1 бросал на такой вызов предупреждение
        // даже при исправном каталоге (php-src #16697). Laravel превращает его в
        // ErrorException — и сохранение бланка падает. Глушим ровно это предупреждение;
        // остальные ошибки проходят как обычно.
        set_error_handler(
            static fn (int $severity, string $message): bool => str_contains($message, self::TEMPNAM_WARNING),
            E_WARNING,
        );

        try {
            return Purifier::clean($html, 'blank');
        } finally {
            restore_error_handler();
        }
    }
}
