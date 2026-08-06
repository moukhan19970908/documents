<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentApprovalStage;
use App\Models\DocumentField;
use App\Models\DocumentSubtype;
use App\Models\DocumentType;
use App\Models\WorkflowNode;

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
     * Тело документа, заполненного по бланку. Токены подставляются при показе, а не при
     * заполнении: номер и дата появляются у документа позже, при регистрации. Скобочные
     * сегменты и схлопывание пробелов — правила маски названия, к тексту они не применяются.
     * Незнакомый токен остаётся как есть — это опечатка автора бланка, а не пустое значение.
     */
    public function fillBlank(Document $document): ?string
    {
        if (blank($document->body_html)) {
            return null;
        }

        $context = $this->blankContext($document);

        // Автор бланка использует оба синтаксиса: {токен} (служебные) и ${токен}
        // (параметры и секции комментариев). Незнакомый токен остаётся как есть.
        return preg_replace_callback('/\$?\{([^{}]+)\}/u', function ($m) use ($context) {
            $key = $this->normalizeKey($m[1]);

            return array_key_exists($key, $context) ? (string) $context[$key] : $m[0];
        }, $document->body_html);
    }

    /**
     * Контекст бланка = базовые токены плюс то, что накапливается по ходу процесса:
     *  - ответы инициатора на параметры запуска (токен по ключу параметра);
     *  - комментарии ответственных по звеньям (токен «заключение:<название звена>»).
     *
     * Секции комментариев задаёт автор бланка заранее; до решения по звену токен пуст —
     * не показываем сырое {…}. Значения из процесса экранируются: они идут в тело как HTML.
     */
    private function blankContext(Document $document): array
    {
        $context = $this->contextFor($document);
        $approval = $document->latestApproval;

        // (1) Параметры запуска — ответы инициатора. Не перекрываем уже заполненный
        // токен (например, поле документа с тем же ключом).
        foreach (($approval?->parameter_values ?? []) as $key => $value) {
            $token = $this->normalizeKey((string) $key);

            if (($context[$token] ?? '') === '') {
                $context[$token] = is_scalar($value) ? e((string) $value) : '';
            }
        }

        $document->loadMissing('workflow.stages');
        $approval?->loadMissing(['stages.workflowStage', 'stages.workflowNode', 'stages.decisions.user']);

        // (2) Секции комментариев по ключу звена (${ключ}) — основной способ. Ключи
        // задаёт автор в конструкторе. Заранее объявляем их пустыми (из графа-определения),
        // чтобы до решения токен не оставался литералом, затем заполняем пройденными.
        if ($graphId = $approval?->runtime_data['graph_workflow_id'] ?? null) {
            foreach (WorkflowNode::where('workflow_id', $graphId)->get() as $node) {
                if ($key = $node->cfg('comment_key')) {
                    $context[$this->normalizeKey($key)] = '';
                }
            }
        }

        foreach ($approval?->stages ?? [] as $stage) {
            $key = $stage->workflowNode?->cfg('comment_key');

            if ($key && ($rendered = $this->renderStageComments($stage)) !== '') {
                $context[$this->normalizeKey($key)] = $rendered;
            }
        }

        // (3) Резервный способ: секция по названию звена — {заключение:<Название>}.
        $names = collect($document->workflow?->stages ?? [])->pluck('name')
            ->merge(collect($approval?->stages ?? [])->map(fn ($s) => $s->workflowStage?->name))
            ->filter()->unique();

        foreach ($names as $name) {
            $context['заключение:' . $this->normalizeKey($name)] = '';
        }

        foreach ($approval?->stages ?? [] as $stage) {
            $name = $stage->workflowStage?->name;

            if ($name && ($rendered = $this->renderStageComments($stage)) !== '') {
                $context['заключение:' . $this->normalizeKey($name)] = $rendered;
            }
        }

        return $context;
    }

    /** Комментарии одного звена как HTML: текст решения плюс подпись «— ФИО, дата». */
    private function renderStageComments(DocumentApprovalStage $stage): string
    {
        $blocks = [];

        foreach ($stage->decisions as $decision) {
            if (blank($decision->comment)) {
                continue;
            }

            $sign = trim(e($decision->user?->name ?? '')
                . ($decision->decided_at ? ', ' . $decision->decided_at->format('d.m.Y') : ''));

            $blocks[] = '<div>' . e($decision->comment)
                . ($sign ? ' <span style="color:#9ca3af">— ' . $sign . '</span>' : '')
                . '</div>';
        }

        return implode('', $blocks);
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
