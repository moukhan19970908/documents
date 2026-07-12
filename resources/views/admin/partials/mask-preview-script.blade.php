{{--
    Client-side twin of App\Services\DocumentNamingService::render() — used only for the live
    preview in the admin forms. The server render stays the source of truth.
--}}
<script>
const MASK_OPEN = String.fromCharCode(1);
const MASK_CLOSE = String.fromCharCode(2);

function renderMask(template, context) {
    if (!template) return '';

    let result = template.replaceAll('[[', MASK_OPEN).replaceAll(']]', MASK_CLOSE);

    // [для {контрагент}] disappears when there is no counterparty
    result = result.replace(/\[([^\[\]]*)\]/gu, (_, segment) => {
        const tokens = [...segment.matchAll(/\{([^{}]+)\}/gu)].map(m => m[1].trim().toLowerCase());
        return tokens.some(t => !context[t]) ? '' : segment;
    });

    result = result.replace(/\{([^{}]+)\}/gu, (_, token) => context[token.trim().toLowerCase()] ?? '');

    return result
        .replaceAll(MASK_OPEN, '[')
        .replaceAll(MASK_CLOSE, ']')
        .replace(/\s{2,}/g, ' ')
        .trim();
}

/** Client-side twin of DocumentNumberService::renderMask(). */
function renderNumber(mask, value, padding, typeCode, subtypeCode, department) {
    if (!mask) return '';

    const now = new Date();
    const pad = (n, len) => String(n).padStart(len, '0');

    return mask
        .replaceAll('{N}', pad(value, padding || 1))
        .replaceAll('{YYYY}', now.getFullYear())
        .replaceAll('{YY}', pad(now.getFullYear() % 100, 2))
        .replaceAll('{MM}', pad(now.getMonth() + 1, 2))
        .replaceAll('{DD}', pad(now.getDate(), 2))
        .replaceAll('{код_типа}', typeCode || '')
        .replaceAll('{код_подтипа}', subtypeCode || '')
        .replaceAll('{отдел}', department || '');
}

function previewContext(typeCode, typeName, subtypeCode, subtypeName, fields) {
    const context = {
        'код_типа':    typeCode || '',
        'код_подтипа': subtypeCode || '',
        'тип':         typeName || '',
        'подтип':      subtypeName || '',
        'описание':    'Пример описания',
        'номер':       '___',
        'дата':        '__.__.____',
        'отдел':       'ОКК',
        'инициатор':   'Иванов И.И.',
    };

    fields.forEach(field => {
        if (field.field_key) {
            context[field.field_key.trim().toLowerCase()] = field.label ? `«${field.label}»` : '«значение»';
        }
    });

    return context;
}
</script>
