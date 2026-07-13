{{--
    Редактор бланка: панель инструментов + лист. Используется и в справочнике бланков,
    и на странице документа, заполняемого по бланку.

    $content    — HTML тела
    $inputName  — имя скрытого поля, в которое уходит HTML (по умолчанию content)
    $withTokens — показывать список «+ Поле»; требует tokens() во внешнем Alpine-компоненте
--}}
@php
    $inputName  = $inputName  ?? 'content';
    $withTokens = $withTokens ?? false;

    $btn = 'h-8 min-w-8 px-2 rounded text-sm text-gray-600 hover:bg-gray-100 flex items-center justify-center shrink-0';
    $on  = 'bg-[#5B4FE8]/10 text-[#5B4FE8]';
    $sel = 'h-8 text-xs border border-gray-200 rounded px-1.5 text-gray-600 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8] shrink-0';
    $sep = '<div class="w-px h-5 bg-gray-200 mx-1 shrink-0"></div>';
@endphp

<div x-data="blankEditor({ content: @js($content) })" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <input type="hidden" name="{{ $inputName }}" :value="html">

    {{-- Панель инструментов --}}
    <div class="sticky top-0 z-10 bg-gray-50 border-b border-gray-200 px-3 py-2 flex items-center gap-1 flex-wrap">
        <button type="button" @click="run('undo')" class="{{ $btn }}" title="Отменить">↶</button>
        <button type="button" @click="run('redo')" class="{{ $btn }}" title="Повторить">↷</button>
        {!! $sep !!}

        <select class="{{ $sel }}" title="Стиль абзаца"
                @change="$event.target.value === 'p' ? run('setParagraph') : run('toggleHeading', { level: Number($event.target.value) })">
            <option value="p">Обычный</option>
            <option value="1">Заголовок 1</option>
            <option value="2">Заголовок 2</option>
            <option value="3">Заголовок 3</option>
        </select>

        <select class="{{ $sel }}" title="Шрифт" @change="setFontFamily($event.target.value)">
            <option value="">Шрифт</option>
            <option value="'Times New Roman', serif">Times New Roman</option>
            <option value="Arial, sans-serif">Arial</option>
            <option value="Calibri, sans-serif">Calibri</option>
            <option value="Georgia, serif">Georgia</option>
            <option value="'Courier New', monospace">Courier New</option>
        </select>

        <select class="{{ $sel }}" title="Размер" @change="setFontSize($event.target.value)">
            <option value="">Размер</option>
            @foreach(['10pt', '11pt', '12pt', '14pt', '16pt', '18pt', '24pt'] as $size)
                <option value="{{ $size }}">{{ $size }}</option>
            @endforeach
        </select>
        {!! $sep !!}

        <button type="button" @click="run('toggleBold')" :class="isActive('bold') && '{{ $on }}'" class="{{ $btn }} font-bold" title="Жирный">Ж</button>
        <button type="button" @click="run('toggleItalic')" :class="isActive('italic') && '{{ $on }}'" class="{{ $btn }} italic font-serif" title="Курсив">К</button>
        <button type="button" @click="run('toggleUnderline')" :class="isActive('underline') && '{{ $on }}'" class="{{ $btn }} underline" title="Подчёркнутый">Ч</button>
        <button type="button" @click="run('toggleStrike')" :class="isActive('strike') && '{{ $on }}'" class="{{ $btn }} line-through" title="Зачёркнутый">З</button>
        <button type="button" @click="run('toggleHighlight')" :class="isActive('highlight') && '{{ $on }}'" class="{{ $btn }}" title="Выделить маркером">🖍</button>
        <label class="{{ $btn }} cursor-pointer" title="Цвет текста">
            <span class="w-4 h-4 rounded border border-gray-300 bg-gradient-to-br from-gray-900 to-red-500"></span>
            <input type="color" class="sr-only" @input="setColor($event.target.value)">
        </label>
        {!! $sep !!}

        <button type="button" @click="run('setTextAlign', 'left')" :class="isActive({ textAlign: 'left' }) && '{{ $on }}'" class="{{ $btn }}" title="По левому краю">⇤</button>
        <button type="button" @click="run('setTextAlign', 'center')" :class="isActive({ textAlign: 'center' }) && '{{ $on }}'" class="{{ $btn }}" title="По центру">⇔</button>
        <button type="button" @click="run('setTextAlign', 'right')" :class="isActive({ textAlign: 'right' }) && '{{ $on }}'" class="{{ $btn }}" title="По правому краю">⇥</button>
        <button type="button" @click="run('setTextAlign', 'justify')" :class="isActive({ textAlign: 'justify' }) && '{{ $on }}'" class="{{ $btn }}" title="По ширине">≡</button>
        {!! $sep !!}

        <button type="button" @click="run('toggleBulletList')" :class="isActive('bulletList') && '{{ $on }}'" class="{{ $btn }}" title="Маркированный список">•—</button>
        <button type="button" @click="run('toggleOrderedList')" :class="isActive('orderedList') && '{{ $on }}'" class="{{ $btn }}" title="Нумерованный список">1.</button>
        <button type="button" @click="run('toggleBlockquote')" :class="isActive('blockquote') && '{{ $on }}'" class="{{ $btn }}" title="Цитата">❝</button>
        <button type="button" @click="run('setHorizontalRule')" class="{{ $btn }}" title="Горизонтальная линия">―</button>
        {!! $sep !!}

        <button type="button" @click="insertTable()" class="{{ $btn }}" title="Вставить таблицу 3×3">▦</button>
        <button type="button" @click="insertLetterhead()" class="{{ $btn }} text-xs" title="Шапка бланка: логотип слева, реквизиты справа">Шапка</button>
        <button type="button" @click="addLink()" :class="isActive('link') && '{{ $on }}'" class="{{ $btn }}" title="Ссылка">🔗</button>
        <label class="{{ $btn }} cursor-pointer" title="Картинка">
            🖼
            <input type="file" accept="image/*" class="sr-only" @change="pickImage($event)">
        </label>
        <button type="button" @click="run('setPageBreak')" class="{{ $btn }}" title="Разрыв страницы">⤓</button>

        @if($withTokens)
            {!! $sep !!}

            <select class="{{ $sel }} text-[#5B4FE8] border-[#5B4FE8]/40" title="Вставить поле документа"
                    @change="insertToken($event.target.value); $event.target.value = ''">
                <option value="">+ Поле</option>
                <template x-for="token in tokens()" :key="token.key">
                    <option :value="token.key" x-text="`${token.label} — {${token.key}}`"></option>
                </template>
            </select>
        @endif
    </div>

    {{-- Инструменты таблицы: показываем, только когда курсор внутри таблицы --}}
    <div x-show="inTable()" x-cloak class="bg-indigo-50/50 border-b border-gray-200 px-3 py-1.5 flex items-center gap-1 flex-wrap text-xs">
        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mr-1">Таблица</span>
        <button type="button" @click="run('addColumnBefore')" class="{{ $btn }}" title="Столбец слева">←▮</button>
        <button type="button" @click="run('addColumnAfter')" class="{{ $btn }}" title="Столбец справа">▮→</button>
        <button type="button" @click="run('deleteColumn')" class="{{ $btn }} text-red-500" title="Удалить столбец">▮✕</button>
        {!! $sep !!}
        <button type="button" @click="run('addRowBefore')" class="{{ $btn }}" title="Строка выше">↑▬</button>
        <button type="button" @click="run('addRowAfter')" class="{{ $btn }}" title="Строка ниже">▬↓</button>
        <button type="button" @click="run('deleteRow')" class="{{ $btn }} text-red-500" title="Удалить строку">▬✕</button>
        {!! $sep !!}
        <button type="button" @click="run('mergeCells')" class="{{ $btn }}" title="Объединить ячейки">⧉</button>
        <button type="button" @click="run('splitCell')" class="{{ $btn }}" title="Разделить ячейку">⧈</button>
        <button type="button" @click="run('toggleHeaderRow')" class="{{ $btn }}" title="Строка-заголовок">⊤</button>
        {!! $sep !!}
        <button type="button" @click="run('toggleTableBorders')" :class="borderlessTable() && '{{ $on }}'" class="{{ $btn }} text-xs" title="Скрыть рамки — так делают шапку бланка">Без границ</button>
        <button type="button" @click="setCellAlign('top')" class="{{ $btn }}" title="Содержимое по верху">⤒</button>
        <button type="button" @click="setCellAlign('middle')" class="{{ $btn }}" title="Содержимое по центру">↕</button>
        <button type="button" @click="setCellAlign('bottom')" class="{{ $btn }}" title="Содержимое по низу">⤓</button>
        {!! $sep !!}
        <button type="button" @click="run('deleteTable')" class="{{ $btn }} text-red-500" title="Удалить таблицу">Удалить таблицу</button>
    </div>

    {{-- Инструменты картинки: появляются, когда картинка выделена --}}
    <div x-show="imageSelected()" x-cloak class="bg-indigo-50/50 border-b border-gray-200 px-3 py-1.5 flex items-center gap-1 flex-wrap text-xs">
        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mr-1">Картинка</span>
        <button type="button" @click="setImageAlign('left')" :class="imageAlign() === 'left' && '{{ $on }}'" class="{{ $btn }}" title="По левому краю">⇤</button>
        <button type="button" @click="setImageAlign('center')" :class="imageAlign() === 'center' && '{{ $on }}'" class="{{ $btn }}" title="По центру">⇔</button>
        <button type="button" @click="setImageAlign('right')" :class="imageAlign() === 'right' && '{{ $on }}'" class="{{ $btn }}" title="По правому краю">⇥</button>
        {!! $sep !!}
        <button type="button" @click="setImageAlign('float-left')" :class="imageAlign() === 'float-left' && '{{ $on }}'" class="{{ $btn }} text-xs" title="Обтекание: картинка слева, текст справа от неё">◧ обтекание</button>
        <button type="button" @click="setImageAlign('float-right')" :class="imageAlign() === 'float-right' && '{{ $on }}'" class="{{ $btn }} text-xs" title="Обтекание: картинка справа, текст слева от неё">обтекание ◨</button>
        {!! $sep !!}
        @foreach(['25%' => '¼', '50%' => '½', '100%' => 'Во всю ширину'] as $width => $label)
            <button type="button" @click="setImageWidth('{{ $width }}')" class="{{ $btn }}" title="Ширина {{ $width }}">{{ $label }}</button>
        @endforeach
        <button type="button" @click="setImageWidth(null)" class="{{ $btn }}" title="Исходный размер">Сбросить</button>
        {!! $sep !!}
        <span class="text-[11px] text-gray-400">или потяните за угол</span>
        {!! $sep !!}
        <button type="button" @click="deleteImage()" class="{{ $btn }} text-red-500" title="Удалить картинку">Удалить</button>
    </div>

    {{-- Лист --}}
    <div class="bg-gray-100 p-8 overflow-x-auto max-h-[70vh] overflow-y-auto">
        <div x-ref="editor"></div>
    </div>
</div>
