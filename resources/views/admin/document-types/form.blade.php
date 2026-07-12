<x-app-layout>
    <x-slot name="title">{{ $documentType ? $documentType->name : 'Новый тип документа' }} — Vamin</x-slot>

    @include('admin.partials.mask-preview-script')

    @php
        $numerator = $documentType?->numerator;
        $hasRegistered = $documentType && $documentType->documents()->whereNotNull('number')->exists();

        $fieldsData = old('fields', $documentType
            ? $documentType->fields->map(fn ($f) => [
                'id'             => $f->id,
                'field_key'      => $f->field_key,
                'label'          => $f->label,
                'type_spec'      => $f->field_type === 'reference' ? 'reference:' . $f->reference_to : $f->field_type,
                'options'        => implode(', ', $f->options ?? []),
                'is_required'    => (bool) $f->is_required,
                'use_in_name'    => (bool) $f->use_in_name,
                'use_in_filter'  => (bool) $f->use_in_filter,
                'use_in_archive' => (bool) $f->use_in_archive,
            ])->values()->all()
            : []);

        $numberingData = old('numbering', [
            'mask'          => $numerator->mask ?? '',
            'reset_period'  => $numerator->reset_period ?? 'yearly',
            'padding'       => $numerator->padding ?? 4,
            'leading_zeros' => ($numerator->padding ?? 4) > 1,
            'scope'         => $numerator->scope ?? ['type'],
            'assign_moment' => $numerator->assign_moment ?? 'on_launch',
            'allow_manual'  => (bool) ($numerator->allow_manual ?? false),
            'manual_roles'  => $numerator->manual_roles ?? [],
            'start_value'   => $numerator->start_value ?? 0,
        ]);

        $subtypesData = old('subtypes', $documentType
            ? $documentType->subtypes->map(fn ($s) => [
                'id'            => $s->id,
                'name'          => $s->name,
                'code'          => $s->code,
                'is_active'     => (bool) $s->is_active,
                'workflows'     => $s->workflows->pluck('id')->all(),
                'own_numerator' => (bool) $s->numerator_id,
                'editing'       => false,
                'numerator'     => [
                    'mask'          => $s->numerator->mask ?? '',
                    'reset_period'  => $s->numerator->reset_period ?? 'yearly',
                    'padding'       => $s->numerator->padding ?? 4,
                    'scope'         => $s->numerator->scope ?? ['subtype'],
                    'assign_moment' => $s->numerator->assign_moment ?? 'on_launch',
                    'allow_manual'  => (bool) ($s->numerator->allow_manual ?? false),
                    'manual_roles'  => $s->numerator->manual_roles ?? [],
                    'start_value'   => $s->numerator->start_value ?? 0,
                ],
            ])->values()->all()
            : []);

        $workflowsData = $workflows->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values()->all();

        $rootDepartments = $departments->whereNull('parent_id');
        $childDepartments = $departments->whereNotNull('parent_id')->groupBy('parent_id');
        $selectedDepartments = old('allowed_departments', $documentType?->allowed_departments ?? []);
        $selectedRoles = old('allowed_roles', $documentType?->allowed_roles ?? []);
        $selectedUsers = old('allowed_users', $documentType?->allowed_users ?? []);
    @endphp

    <div class="max-w-4xl" x-data="typeForm()">

        {{-- Шапка --}}
        <div class="flex items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center">
                    <template x-if="icon === 'document'">@include('admin.partials.type-icon', ['icon' => 'document', 'class' => 'w-5 h-5'])</template>
                    <template x-if="icon === 'contract'">@include('admin.partials.type-icon', ['icon' => 'contract', 'class' => 'w-5 h-5'])</template>
                    <template x-if="icon === 'order'">@include('admin.partials.type-icon', ['icon' => 'order', 'class' => 'w-5 h-5'])</template>
                    <template x-if="icon === 'letter'">@include('admin.partials.type-icon', ['icon' => 'letter', 'class' => 'w-5 h-5'])</template>
                </div>
                <h1 class="text-xl font-bold text-gray-900" x-text="name || 'Новый тип документа'"></h1>
                <span x-show="code" class="text-sm font-mono font-semibold text-amber-500" x-text="'[' + code + ']'"></span>
                @if($documentType)
                    <span class="flex items-center gap-1.5 text-xs font-medium {{ $documentType->is_active ? 'text-emerald-600' : 'text-gray-400' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $documentType->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        {{ $documentType->is_active ? 'Активен' : 'Неактивен' }}
                    </span>
                @endif
            </div>

            @if($documentType)
                <div class="flex items-center gap-2">
                    <form action="{{ route('admin.document-types.duplicate', $documentType) }}" method="POST">
                        @csrf
                        <button class="text-sm font-medium text-gray-600 border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50">Дублировать</button>
                    </form>
                    <form action="{{ route('admin.document-types.toggle', $documentType) }}" method="POST">
                        @csrf @method('PATCH')
                        <button class="text-sm font-medium {{ $documentType->is_active ? 'text-red-500 border-red-200 hover:bg-red-50' : 'text-emerald-600 border-emerald-200 hover:bg-emerald-50' }} border px-4 py-2 rounded-lg">
                            {{ $documentType->is_active ? 'Деактивировать' : 'Активировать' }}
                        </button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Вкладки --}}
        <div class="flex items-center gap-1 mb-6 border-b border-gray-200">
            @foreach(['basic' => 'Основное', 'naming' => 'Название и нумерация', 'subtypes' => 'Подтипы и сценарии', 'access' => 'Доступ'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-white border-gray-200 border-b-white text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800'"
                        class="text-sm font-medium px-4 py-2.5 rounded-t-lg border -mb-px">{{ $label }}</button>
            @endforeach
        </div>

        <form action="{{ $documentType ? route('admin.document-types.update', $documentType) : route('admin.document-types.store') }}" method="POST" class="space-y-5">
            @csrf
            @if($documentType) @method('PUT') @endif

            <input type="hidden" name="icon" :value="icon">
            <input type="hidden" name="is_active" :value="isActive ? 1 : 0">

            {{-- ================= Основное ================= --}}
            <div x-show="tab === 'basic'" class="space-y-5">
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label class="text-sm text-gray-600 block mb-1.5">Полное название типа</label>
                        <input type="text" name="name" x-model="name" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="text-sm text-gray-600 block mb-1.5">Код-сокращение</label>
                        <div class="flex items-center gap-3">
                            <input type="text" name="code" x-model="code" required
                                   {{ $hasRegistered ? 'readonly' : '' }}
                                   class="w-40 text-sm font-mono border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] {{ $hasRegistered ? 'bg-gray-50 text-gray-500' : '' }}">
                            <span class="flex items-center gap-1.5 text-xs text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Используется в имени и номере документа. Изменение после регистрации документов запрещено
                            </span>
                        </div>
                        @error('code')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="text-sm text-gray-600 block mb-1.5">Иконка типа</label>
                        <div class="flex gap-2">
                            @foreach(\App\Models\DocumentType::ICONS as $iconOption)
                                <button type="button" @click="icon = '{{ $iconOption }}'"
                                        :class="icon === '{{ $iconOption }}' ? 'border-amber-400 bg-amber-50 text-amber-500' : 'border-gray-200 text-gray-400 hover:border-gray-300'"
                                        class="w-10 h-10 rounded-lg border flex items-center justify-center">
                                    @include('admin.partials.type-icon', ['icon' => $iconOption, 'class' => 'w-5 h-5'])
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="text-sm text-gray-600 block mb-1.5">Описание</label>
                        <textarea name="description" rows="3"
                                  class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('description', $documentType->description ?? '') }}</textarea>
                    </div>
                </div>

                {{-- Атрибуты --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <p class="text-sm font-semibold text-gray-800">Атрибуты типа</p>
                    <p class="text-xs text-gray-400 mb-4">Атрибуты — метаданные документа. Они используются в фильтрах, архиве и автоназвании</p>

                    <table class="w-full">
                        <thead>
                            <tr class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                                <th class="text-left pb-2 font-semibold">Ключ</th>
                                <th class="text-left pb-2 font-semibold">Подпись</th>
                                <th class="text-left pb-2 font-semibold">Тип поля</th>
                                <th class="pb-2 font-semibold w-16">Обяз.</th>
                                <th class="pb-2 font-semibold w-16">В имени</th>
                                <th class="pb-2 font-semibold w-20">В фильтрах</th>
                                <th class="pb-2 font-semibold w-20">В архиве</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(field, index) in fields" :key="field.uid">
                                <tr class="border-b border-gray-50">
                                    <template x-if="field.id">
                                        <input type="hidden" :name="`fields[${index}][id]`" :value="field.id">
                                    </template>
                                    <td class="py-2 pr-2">
                                        <input type="text" :name="`fields[${index}][field_key]`" x-model="field.field_key" placeholder="counterparty"
                                               class="w-full text-sm font-mono text-[#5B4FE8] border border-transparent hover:border-gray-200 focus:border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="text" :name="`fields[${index}][label]`" x-model="field.label" placeholder="Контрагент"
                                               class="w-full text-sm border border-transparent hover:border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <select :name="`fields[${index}][type_spec]`" x-model="field.type_spec"
                                                class="w-full text-sm border border-transparent hover:border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                            <option value="text">Текст</option>
                                            <option value="textarea">Многострочный</option>
                                            <option value="number">Число</option>
                                            <option value="date">Дата</option>
                                            <option value="select">Список</option>
                                            <option value="boolean">Да/Нет</option>
                                            <option value="reference:department">Справочник → Отделы</option>
                                            <option value="reference:user">Справочник → Сотрудники</option>
                                        </select>
                                        <input x-show="field.type_spec === 'select'" type="text" :name="`fields[${index}][options]`" x-model="field.options"
                                               placeholder="Варианты через запятую"
                                               class="w-full text-xs border border-gray-200 rounded px-2 py-1 mt-1 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                    </td>
                                    @foreach(['is_required', 'use_in_name', 'use_in_filter', 'use_in_archive'] as $flag)
                                        <td class="py-2 text-center">
                                            <input type="hidden" :name="`fields[${index}][{{ $flag }}]`" value="0">
                                            <input type="checkbox" :name="`fields[${index}][{{ $flag }}]`" value="1" x-model="field.{{ $flag }}"
                                                   class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                        </td>
                                    @endforeach
                                    <td class="py-2 text-right">
                                        <button type="button" @click="fields.splice(index, 1)" class="text-gray-300 hover:text-red-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <button type="button" @click="addField()" class="flex items-center gap-1.5 text-sm text-[#5B4FE8] font-medium mt-4 hover:underline">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Добавить атрибут
                    </button>
                </div>
            </div>

            {{-- ================= Название и нумерация ================= --}}
            <div x-show="tab === 'naming'" class="space-y-5">
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <p class="text-sm font-semibold text-gray-800">Автоматическое название</p>

                    <div class="flex flex-wrap gap-1.5">
                        @foreach(\App\Services\DocumentNamingService::RESERVED_TOKENS as $token => $hint)
                            <button type="button" @click="insertToken('{{ $token }}')" title="{{ $hint }}"
                                    class="bg-gray-100 text-gray-500 text-xs font-mono px-2 py-1 rounded hover:bg-[#5B4FE8]/10 hover:text-[#5B4FE8]">{{ '{' . $token . '}' }}</button>
                        @endforeach
                        <template x-for="field in fields.filter(f => f.field_key)" :key="field.uid">
                            <button type="button" @click="insertToken(field.field_key)"
                                    class="bg-[#5B4FE8]/10 text-[#5B4FE8] text-xs font-mono px-2 py-1 rounded hover:bg-[#5B4FE8]/20"
                                    x-text="'{' + field.field_key + '}'"></button>
                        </template>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Маска имени</label>
                        <input type="text" name="name_template" x-model="nameTemplate"
                               placeholder="[[{код_типа}]] {описание} [для {контрагент}] № {номер} от {дата}"
                               class="w-full text-sm font-mono border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <p class="text-xs text-gray-400 mt-1">
                            <code>[…]</code> — сегмент исчезает, если значение внутри пустое. <code>[[…]]</code> — литеральные скобки.
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400 mb-1">до регистрации</p>
                        <div class="bg-gray-50 border border-gray-100 rounded-lg px-4 py-2.5 text-sm text-gray-700" x-text="previewDraft() || '—'"></div>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-1">после регистрации</p>
                        <div class="bg-[#5B4FE8]/5 border border-[#5B4FE8]/20 rounded-lg px-4 py-2.5 text-sm text-gray-800 font-medium" x-text="previewRegistered() || '—'"></div>
                        <p class="text-xs text-gray-400 mt-1">Номер и дата подставляются только при регистрации документа</p>
                    </div>
                </div>

                {{-- Нумерация --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <p class="text-sm font-semibold text-gray-800">Нумерация</p>

                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="text-xs text-gray-500 block mb-1">Маска номера</label>
                            <input type="text" name="numbering[mask]" x-model="numbering.mask" placeholder="{N}"
                                   class="w-full text-sm font-mono border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <p class="text-xs text-gray-400 mt-1">Пусто — тип без номера. Токены: <code>{N}</code>, <code>{YYYY}</code>, <code>{YY}</code>, <code>{MM}</code>, <code>{код_типа}</code>, <code>{отдел}</code></p>
                        </div>
                        <div class="flex-1">
                            <label class="text-xs text-gray-500 block mb-1">Предпросмотр</label>
                            <div class="bg-gray-50 border border-gray-100 rounded-lg px-4 py-2.5 text-sm text-gray-700">
                                Следующий номер: <span class="font-mono font-medium" x-text="nextNumber() || '—'"></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1.5">Период сброса</label>
                        <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                            @foreach(['none' => 'Без сброса', 'monthly' => 'Ежемесячно', 'yearly' => 'Ежегодно'] as $value => $label)
                                <button type="button" @click="numbering.reset_period = '{{ $value }}'"
                                        :class="numbering.reset_period === '{{ $value }}' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        class="text-xs font-medium px-3 py-1.5 border-r border-gray-200 last:border-r-0">{{ $label }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" name="numbering[reset_period]" :value="numbering.reset_period">
                    </div>

                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" x-model="numbering.leading_zeros" class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            Ведущие нули
                        </label>
                        <input type="number" min="1" max="12" x-model.number="numbering.padding" :disabled="!numbering.leading_zeros"
                               class="w-16 text-sm border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] disabled:bg-gray-50">
                        <span class="text-xs text-gray-400">разрядов</span>
                        <input type="hidden" name="numbering[padding]" :value="numbering.leading_zeros ? numbering.padding : 1">
                        <span class="text-xs text-gray-400 ml-auto">пример: <span class="font-mono" x-text="nextNumber()"></span></span>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1.5">Область нумерации (scope)</label>
                        <div class="space-y-1">
                            @foreach(['type' => 'По типу', 'subtype' => 'По подтипу', 'department' => 'По отделу'] as $value => $label)
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="checkbox" name="numbering[scope][]" value="{{ $value }}" x-model="numbering.scope"
                                           class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Каждая комбинация ведёт независимый счётчик. Год и месяц задаются периодом сброса.</p>
                    </div>

                    {{-- Счётчики --}}
                    @if($numerator && $numerator->counters->isNotEmpty())
                        {{-- Счётчики уже живут своей жизнью: start_value больше не редактируется, но и не должен обнуляться --}}
                        <input type="hidden" name="numbering[start_value]" :value="numbering.start_value">

                        <div class="border border-[#5B4FE8]/30 rounded-xl p-4 space-y-3">
                            <p class="text-xs text-gray-500">Текущее значение счётчика</p>
                            @foreach($numerator->counters as $counter)
                                <div x-data="{ editing: false }">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl font-bold text-gray-900">{{ $counter->current_value }}</span>
                                        <span class="text-xs text-gray-400 font-mono">{{ $counter->scope_key }}</span>
                                        <button type="button" @click="editing = !editing"
                                                class="text-xs font-medium text-gray-600 border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50">Изменить</button>
                                    </div>

                                    <div x-show="editing" class="flex items-center gap-2 mt-2">
                                        <input type="number" min="0" name="current_value" value="{{ $counter->current_value }}"
                                               form="counter-{{ $counter->id }}"
                                               class="w-32 text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <button type="submit" form="counter-{{ $counter->id }}"
                                                class="text-xs font-medium bg-[#5B4FE8] text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700">Сохранить счётчик</button>
                                    </div>

                                    <div class="bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mt-2 text-xs text-amber-700">
                                        Для перехода с бумажного журнала укажите последний номер, который вела офис-менеджер — система продолжит нумерацию без разрыва.
                                    </div>

                                    @if($counter->updated_by)
                                        <p class="text-xs text-gray-400 mt-1">Изменено: {{ $counter->editor?->name }}, {{ $counter->updated_at->format('d.m.Y') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Продолжить с номера</label>
                            <input type="number" min="0" name="numbering[start_value]" x-model.number="numbering.start_value"
                                   class="w-40 text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <p class="text-xs text-gray-400 mt-1">Последний номер из бумажного журнала: 247 → первый документ получит 248. Счётчик появится после первой регистрации.</p>
                        </div>
                    @endif

                    <div>
                        <label class="text-xs text-gray-500 block mb-1.5">Момент присвоения номера</label>
                        <div class="space-y-1">
                            @foreach(['on_launch' => 'При запуске документа', 'on_approval' => 'При утверждении (после согласования)'] as $value => $label)
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="numbering[assign_moment]" value="{{ $value }}" x-model="numbering.assign_moment"
                                           class="border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="hidden" name="numbering[allow_manual]" value="0">
                            <input type="checkbox" name="numbering[allow_manual]" value="1" x-model="numbering.allow_manual"
                                   class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            Разрешить ручной ввод номера
                        </label>

                        <div x-show="numbering.allow_manual" class="flex flex-wrap gap-2 mt-2 ml-6">
                            @foreach(\App\Models\DocumentType::CREATOR_ROLES as $role => $label)
                                <label class="flex items-center gap-1.5 text-xs text-gray-600 border border-gray-200 rounded-full px-3 py-1 cursor-pointer hover:bg-gray-50">
                                    <input type="checkbox" name="numbering[manual_roles][]" value="{{ $role }}" x-model="numbering.manual_roles"
                                           class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Черновики номер не получают — это исключает пропуски в нумерации.</p>
                    </div>
                </div>
            </div>

            {{-- ================= Подтипы и сценарии ================= --}}
            <div x-show="tab === 'subtypes'" class="space-y-4">
                <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 rounded-lg px-4 py-2.5 text-xs text-gray-500">
                    Подтип → Сценарий — ключ маршрутизации. При создании документа система подставит привязанный сценарий автоматически.
                </div>

                <template x-for="(subtype, index) in subtypes" :key="subtype.uid">
                    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                        <template x-if="subtype.id">
                            <input type="hidden" :name="`subtypes[${index}][id]`" :value="subtype.id">
                        </template>
                        <input type="hidden" :name="`subtypes[${index}][is_active]`" :value="subtype.is_active ? 1 : 0">

                        <div class="flex items-center gap-3">
                            <input type="text" :name="`subtypes[${index}][name]`" x-model="subtype.name" placeholder="На отгрузку товаров" required
                                   class="flex-1 text-sm font-medium border border-transparent hover:border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                            <input type="text" :name="`subtypes[${index}][code]`" x-model="subtype.code" placeholder="код"
                                   class="w-24 text-sm font-mono border border-transparent hover:border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                            <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer whitespace-nowrap">
                                <input type="checkbox" x-model="subtype.own_numerator" class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                Свой нумератор
                            </label>
                            <input type="hidden" :name="`subtypes[${index}][own_numerator]`" :value="subtype.own_numerator ? 1 : 0">
                            <button type="button" @click="subtypes.splice(index, 1)" class="text-gray-300 hover:text-red-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Сценарии --}}
                        <div class="flex flex-wrap items-center gap-2">
                            <template x-for="wfId in subtype.workflows" :key="wfId">
                                <span class="inline-flex items-center gap-1 bg-[#5B4FE8]/8 text-[#5B4FE8] border border-[#5B4FE8]/20 rounded-full px-3 py-1 text-xs font-medium">
                                    → <span x-text="workflowName(wfId)"></span>
                                </span>
                            </template>
                            <button type="button" @click="subtype.editing = !subtype.editing"
                                    class="text-xs font-medium text-[#5B4FE8] hover:underline" x-text="subtype.editing ? 'Готово' : 'Изменить'"></button>
                        </div>

                        <div x-show="subtype.editing" class="border border-gray-100 rounded-lg p-3 space-y-1 max-h-48 overflow-y-auto">
                            <template x-for="wf in allWorkflows" :key="wf.id">
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer py-0.5">
                                    <input type="checkbox" :value="wf.id" x-model.number="subtype.workflows"
                                           class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    <span x-text="wf.name"></span>
                                </label>
                            </template>
                            <p x-show="allWorkflows.length === 0" class="text-sm text-gray-400 py-1">Активных сценариев нет — создайте процесс в конструкторе.</p>
                        </div>

                        {{-- Единственный источник отправки: чекбоксы только правят массив --}}
                        <template x-for="wfId in subtype.workflows" :key="'h' + wfId">
                            <input type="hidden" :name="`subtypes[${index}][workflows][]`" :value="wfId">
                        </template>

                        <p x-show="subtype.workflows.length > 1" class="text-xs text-gray-400">Сотрудник выберет один при создании</p>

                        <div x-show="subtype.workflows.length === 0"
                             class="flex items-center gap-2 bg-red-50 border border-red-100 rounded-lg px-3 py-2 text-xs text-red-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0l-7.1 12.25A2 2 0 004.98 19z"/></svg>
                            Сценарий не назначен — документы этого подтипа нельзя запустить
                        </div>

                        {{-- Свой нумератор подтипа --}}
                        <div x-show="subtype.own_numerator" class="border-t border-gray-100 pt-3 space-y-3">
                            <div class="flex gap-3">
                                <div class="flex-1">
                                    <label class="text-xs text-gray-500 block mb-1">Маска номера</label>
                                    <input type="text" :name="`subtypes[${index}][numerator][mask]`" x-model="subtype.numerator.mask" placeholder="{код_подтипа}-{N}"
                                           class="w-full text-sm font-mono border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                </div>
                                <div class="w-32">
                                    <label class="text-xs text-gray-500 block mb-1">Разрядов</label>
                                    <input type="number" min="1" max="12" :name="`subtypes[${index}][numerator][padding]`" x-model.number="subtype.numerator.padding"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                </div>
                                <div class="w-40">
                                    <label class="text-xs text-gray-500 block mb-1">Сброс</label>
                                    <select :name="`subtypes[${index}][numerator][reset_period]`" x-model="subtype.numerator.reset_period"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                        <option value="none">Без сброса</option>
                                        <option value="monthly">Ежемесячно</option>
                                        <option value="yearly">Ежегодно</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-4">
                                @foreach(['type' => 'По типу', 'subtype' => 'По подтипу', 'department' => 'По отделу'] as $value => $label)
                                    <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                        <input type="checkbox" value="{{ $value }}" x-model="subtype.numerator.scope"
                                               :name="`subtypes[${index}][numerator][scope][]`"
                                               class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                        {{ $label }}
                                    </label>
                                @endforeach
                                <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer ml-auto">
                                    Продолжить с
                                    <input type="number" min="0" :name="`subtypes[${index}][numerator][start_value]`" x-model.number="subtype.numerator.start_value"
                                           class="w-20 text-xs border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                </label>
                            </div>

                            <input type="hidden" :name="`subtypes[${index}][numerator][assign_moment]`" :value="subtype.numerator.assign_moment">
                        </div>
                    </div>
                </template>

                <button type="button" @click="addSubtype()"
                        class="w-full border border-dashed border-gray-300 rounded-xl py-3 text-sm font-medium text-gray-500 hover:border-[#5B4FE8] hover:text-[#5B4FE8]">
                    + Добавить подтип
                </button>
            </div>

            {{-- ================= Доступ ================= --}}
            <div x-show="tab === 'access'" class="space-y-5">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <p class="text-sm font-semibold text-gray-800">Где доступен тип</p>
                    <p class="text-xs text-gray-400 mb-4">Тип появится при создании документа только у сотрудников отмеченных подразделений и ролей. Ничего не отмечено — доступен всем.</p>

                    @include('admin.partials.department-checkboxes', [
                        'nodes'    => $rootDepartments,
                        'children' => $childDepartments,
                        'selected' => $selectedDepartments,
                        'level'    => 0,
                    ])
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <p class="text-sm font-semibold text-gray-800 mb-4">Кто может создавать документы этого типа</p>

                    <div class="space-y-1">
                        @foreach(\App\Models\DocumentType::CREATOR_ROLES as $role => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer py-1">
                                <input type="checkbox" name="allowed_roles[]" value="{{ $role }}"
                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"
                                       {{ in_array($role, $selectedRoles) ? 'checked' : '' }}>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-5">
                        <label class="text-xs text-gray-500 block mb-1.5">Дополнительно — конкретные сотрудники</label>
                        <select name="allowed_users[]" multiple size="6"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ in_array($u->id, $selectedUsers) ? 'selected' : '' }}>
                                    {{ $u->name }}@if($u->department) — {{ $u->department->name }}@endif
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Названный здесь сотрудник получает доступ независимо от отдела и роли.</p>
                    </div>
                </div>
            </div>

            {{-- Действия --}}
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.document-types.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
                <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сохранить</button>
            </div>
        </form>

        {{-- Формы счётчиков живут вне основной формы: вложенные формы недопустимы --}}
        @if($numerator)
            @foreach($numerator->counters as $counter)
                <form id="counter-{{ $counter->id }}" action="{{ route('admin.document-types.counters.update', [$documentType, $counter]) }}" method="POST" class="hidden">
                    @csrf @method('PATCH')
                </form>
            @endforeach
        @endif
    </div>

    <script>
    function typeForm() {
        return {
            tab: 'basic',
            name: @json(old('name', $documentType->name ?? '')),
            code: @json(old('code', $documentType->code ?? '')),
            icon: @json(old('icon', $documentType->icon ?? 'document')),
            isActive: @json((bool) old('is_active', $documentType->is_active ?? true)),
            nameTemplate: @json(old('name_template', $documentType->name_template ?? '')),
            fields: @json($fieldsData).map((f, i) => ({ ...f, uid: 'f' + i })),
            numbering: @json($numberingData),
            subtypes: @json($subtypesData).map((s, i) => ({ ...s, uid: 's' + i, editing: false })),
            allWorkflows: @json($workflowsData),

            addField() {
                this.fields.push({
                    uid: 'f' + Date.now(),
                    id: null, field_key: '', label: '', type_spec: 'text', options: '',
                    is_required: false, use_in_name: false, use_in_filter: false, use_in_archive: false,
                });
            },

            addSubtype() {
                this.subtypes.push({
                    uid: 's' + Date.now(),
                    id: null, name: '', code: '', is_active: true, workflows: [], editing: true,
                    own_numerator: false,
                    numerator: {
                        mask: '{N}', reset_period: 'yearly', padding: 4, scope: ['subtype'],
                        assign_moment: 'on_launch', allow_manual: false, manual_roles: [], start_value: 0,
                    },
                });
            },

            workflowName(id) {
                const wf = this.allWorkflows.find(w => w.id === id);
                return wf ? wf.name : '—';
            },

            insertToken(token) {
                this.nameTemplate = (this.nameTemplate + ' {' + token + '}').trim();
            },

            context(number, date) {
                const ctx = previewContext(this.code, this.name, '', '', this.fields);
                ctx['номер'] = number;
                ctx['дата'] = date;
                return ctx;
            },

            previewDraft() {
                return renderMask(this.nameTemplate, this.context('___', '__.__.____'));
            },

            previewRegistered() {
                const now = new Date();
                const date = String(now.getDate()).padStart(2, '0') + '.' + String(now.getMonth() + 1).padStart(2, '0') + '.' + now.getFullYear();
                return renderMask(this.nameTemplate, this.context(this.nextNumber() || '233', date));
            },

            nextNumber() {
                const padding = this.numbering.leading_zeros ? this.numbering.padding : 1;
                return renderNumber(this.numbering.mask, (this.numbering.start_value || 0) + 1, padding, this.code, '', '');
            },
        };
    }
    </script>
</x-app-layout>
