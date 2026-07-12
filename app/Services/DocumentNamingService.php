<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentField;
use App\Models\DocumentSubtype;
use App\Models\DocumentType;

/**
 * Renders a document name from a mask.
 *
 * Mask syntax:
 *   {токен}      — placeholder (reserved token or attribute key)
 *   [ ... ]      — optional segment: dropped entirely if any placeholder inside is empty
 *   [[ ]]        — literal square brackets
 *
 * Example:
 *   [[{код_типа}]] {описание} [для {контрагент}] № {номер} от {дата}
 *   → [СЗ] На отгрузку товаров для ООО «Ромашка» № 233 от 08.07.2026
 *
 * The name is a projection of the structural fields — never the source of truth.
 */
class DocumentNamingService
{
    private const OPEN_SENTINEL  = "\x01";
    private const CLOSE_SENTINEL = "\x02";

    public const DRAFT_NUMBER = '___';
    public const DRAFT_DATE   = '__.__.____';

    /** Tokens available in every mask, regardless of the type's attributes. */
    public const RESERVED_TOKENS = [
        'код_типа'    => 'Код типа — СЗ',
        'код_подтипа' => 'Код подтипа',
        'тип'         => 'Название типа',
        'подтип'      => 'Название подтипа',
        'описание'    => 'Описание, введённое пользователем',
        'номер'       => 'Регистрационный номер (до регистрации — ___)',
        'дата'        => 'Дата регистрации (до регистрации — __.__.____)',
        'отдел'       => 'Отдел инициатора',
        'инициатор'   => 'ФИО инициатора',
    ];

    public function forDocument(Document $document): ?string
    {
        $template = $document->subtype?->effectiveNameTemplate() ?: $document->type?->name_template;

        if (!$template) {
            return null;
        }

        return $this->render($template, $this->contextFor($document));
    }

    public function render(string $template, array $context): string
    {
        $template = str_replace(['[[', ']]'], [self::OPEN_SENTINEL, self::CLOSE_SENTINEL], $template);
        $template = $this->dropEmptySegments($template, $context);

        $result = preg_replace_callback('/\{([^{}]+)\}/u', function ($m) use ($context) {
            return (string) ($context[$this->normalizeKey($m[1])] ?? '');
        }, $template);

        $result = str_replace([self::OPEN_SENTINEL, self::CLOSE_SENTINEL], ['[', ']'], $result);

        return trim(preg_replace('/\s{2,}/u', ' ', $result));
    }

    /**
     * Values for every token the mask may reference. Empty values collapse their segment.
     * `$draft` keeps the ___ / __.__.____ stubs for an unregistered document.
     */
    public function contextFor(Document $document): array
    {
        $document->loadMissing(['type', 'subtype.type', 'initiator.department']);

        return $this->buildContext(
            type:        $document->type,
            subtype:     $document->subtype,
            attributes:  $document->data ?? [],
            number:      $document->number,
            date:        $document->registered_at?->format('d.m.Y'),
            department:  $document->initiator?->department?->name,
            initiator:   $document->initiator?->name,
        );
    }

    public function buildContext(
        ?DocumentType $type = null,
        ?DocumentSubtype $subtype = null,
        array $attributes = [],
        ?string $number = null,
        ?string $date = null,
        ?string $department = null,
        ?string $initiator = null,
    ): array {
        $context = [
            'код_типа'    => $type?->code ?? '',
            'код_подтипа' => $subtype?->code ?? '',
            'тип'         => $type?->name ?? '',
            'подтип'      => $subtype?->name ?? '',
            'описание'    => (string) ($attributes['описание'] ?? $attributes['description'] ?? ''),
            'номер'       => $number ?: self::DRAFT_NUMBER,
            'дата'        => $date ?: self::DRAFT_DATE,
            'отдел'       => $department ?? '',
            'инициатор'   => $initiator ?? '',
        ];

        foreach ($attributes as $key => $value) {
            $context[$this->normalizeKey((string) $key)] = is_scalar($value) ? (string) $value : '';
        }

        return $context;
    }

    /** Tokens an admin may use in the mask of this type: reserved ones + its attribute keys. */
    public function availableTokens(iterable $fields): array
    {
        $tokens = self::RESERVED_TOKENS;

        foreach ($fields as $field) {
            $key = $field instanceof DocumentField ? $field->field_key : ($field['field_key'] ?? null);
            if ($key) {
                $label = $field instanceof DocumentField ? $field->label : ($field['label'] ?? $key);
                $tokens[$this->normalizeKey($key)] = $label;
            }
        }

        return $tokens;
    }

    /** `[для {контрагент}]` disappears when there is no counterparty — no dangling «для». */
    private function dropEmptySegments(string $template, array $context): string
    {
        return preg_replace_callback('/\[([^\[\]]*)\]/u', function ($m) use ($context) {
            $segment = $m[1];

            preg_match_all('/\{([^{}]+)\}/u', $segment, $placeholders);

            foreach ($placeholders[1] as $token) {
                if (($context[$this->normalizeKey($token)] ?? '') === '') {
                    return '';
                }
            }

            return $segment;
        }, $template);
    }

    private function normalizeKey(string $key): string
    {
        return mb_strtolower(trim($key));
    }
}
