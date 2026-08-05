<x-app-layout>
    <x-slot name="title">{{ $scenario ? 'Сценарий: ' . $scenario->name : 'Создание сценария' }} — Vamin</x-slot>

    @include('admin.partials.mask-preview-script')

    @php
        $parametersData = old('parameters', $scenario
            ? $scenario->parameters->map(fn ($p) => [
                'id'          => $p->id,
                'key'         => $p->key,
                'label'       => $p->label,
                'type'        => $p->type,
                'options'     => $p->options ?? [],
                'is_required' => (bool) $p->is_required,
            ])->values()->all()
            : []);

        $typesData = $documentTypes->map(fn ($t) => [
            'id'            => $t->id,
            'code'          => $t->code,
            'name'          => $t->name,
            'name_template' => $t->name_template,
            'number_mask'   => $t->numerator?->mask,
            'subtypes'      => $t->subtypes->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
        ])->values();

        $selectedTypeId = old('document_type_id', $scenario?->subtypes->first()?->document_type_id ?? $scenario?->document_type_id);
        $selectedSubtypes = old('subtypes', $scenario?->subtypes->pluck('id')->all() ?? []);

        $blanksData = $blankTemplates->map(fn ($b) => [
            'id'                  => $b->id,
            'name'                => $b->name,
            'document_type_id'    => $b->document_type_id,
            'document_subtype_id' => $b->document_subtype_id,
            'subtype_name'        => $b->subtype?->name,
        ])->values();

        $selectedBlanks = old('blank_template_ids', $scenario?->blankTemplates->pluck('id')->all() ?? []);

        // Права запуска: список отделов и отдельных сотрудников.
        $departmentsData = $departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values();
        $usersData = $users->map(fn ($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'department' => $u->department?->name,
        ])->values();
        $selectedDepartments = old('allowed_departments', $scenario?->allowed_departments ?? []);
        $selectedUsers = old('allowed_users', $scenario?->allowed_users ?? []);

        $wizardSteps = ['basic', 'classifier', 'parameters', 'route', 'rights'];

        // После ошибки валидации открываем тот шаг, на котором ошибка, — иначе сообщение
        // остаётся на скрытой вкладке и выглядит как беспричинный откат на первый шаг.
        $stepOfField = function (string $field) {
            if (str_starts_with($field, 'parameters')) return 'parameters';
            if (str_starts_with($field, 'blank_template_ids')) return 'classifier';
            if (in_array($field, ['document_type_id', 'subtypes'], true)) return 'classifier';
            if (str_starts_with($field, 'allowed_')) return 'rights';
            return 'basic';
        };

        $initialStep = $errors->any()
            ? $stepOfField($errors->keys()[0])
            : (in_array(request('step'), $wizardSteps, true) ? request('step') : 'basic');
    @endphp

    <div class="max-w-4xl" x-data="scenarioWizard()">
        <h1 class="text-2xl font-bold text-gray-900 mb-5">{{ $scenario ? $scenario->name : 'Создание сценария' }}</h1>

        @if($errors->any())
            <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 mb-5">
                <p class="text-sm font-medium text-red-600 mb-1">Сценарий не сохранён — исправьте:</p>
                <ul class="text-xs text-red-500 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Степпер --}}
        <div class="flex items-center gap-2 mb-8">
            @php
                $steps = [
                    1 => ['key' => 'basic', 'label' => 'Основное'],
                    2 => ['key' => 'classifier', 'label' => 'Классификатор'],
                    3 => ['key' => 'parameters', 'label' => 'Параметры'],
                    4 => ['key' => 'route', 'label' => 'Маршрут'],
                    5 => ['key' => 'rights', 'label' => 'Права'],
                ];
            @endphp
            @foreach($steps as $number => $step)
                @php
                    // Шаг 4 (маршрут) пишет звенья сценария, поэтому при создании сначала сохраняем черновик.
                    $isBuilt = true;
                    $needsSave = $number === 4 && !$scenario;
                @endphp
                <button type="button"
                        @if($needsSave)
                            @click="saveAndGoToRoute()" title="Черновик будет сохранён"
                        @elseif($isBuilt)
                            @click="step = '{{ $step['key'] }}'"
                        @else
                            disabled title="Следующий этап разработки"
                        @endif
                        class="flex items-center gap-2 {{ $isBuilt ? '' : 'opacity-40 cursor-not-allowed' }}">
                    <span :class="step === '{{ $step['key'] }}' ? 'bg-[#5B4FE8] text-white' : 'bg-gray-200 text-gray-500'"
                          class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold">{{ $number }}</span>
                    <span :class="step === '{{ $step['key'] }}' ? 'text-gray-900 font-semibold' : 'text-gray-500'"
                          class="text-sm">{{ $step['label'] }}</span>
                </button>
                @if(!$loop->last)
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                @endif
            @endforeach
        </div>

        <form x-ref="mainForm"
              action="{{ $scenario ? route('admin.scenarios.update', $scenario) : route('admin.scenarios.store') }}" method="POST" class="space-y-5">
            @csrf
            @if($scenario) @method('PUT') @endif

            <input type="hidden" name="icon" :value="icon">
            <input type="hidden" name="process_type" :value="processType">
            <input type="hidden" name="launch_mode" :value="launchMode">
            {{-- Куда вернуться после сохранения --}}
            <input type="hidden" name="step" x-ref="stepInput" value="basic">

            {{-- ============ Шаг 1: Основное ============ --}}
            <div x-show="step === 'basic'" class="space-y-5">
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1.5">Название сценария</label>
                        <input type="text" name="name" x-model="name" required
                               value="{{ old('name', $scenario->name ?? '') }}"
                               placeholder="Согласование договора по нашей форме"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1.5">Описание</label>
                        <textarea name="description" rows="3"
                                  class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('description', $scenario->description ?? '') }}</textarea>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <p class="text-sm font-semibold text-gray-800">Тип процесса</p>
                    <p class="text-xs text-gray-400 mb-4">Тип процесса определяет, какие блоки будут доступны в конструкторе маршрута</p>

                    <div class="grid grid-cols-4 gap-3">
                        @foreach(\App\Models\Workflow::PROCESS_TYPES as $value => $label)
                            <button type="button" @click="processType = '{{ $value }}'"
                                    :class="processType === '{{ $value }}' ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200 hover:border-gray-300'"
                                    class="border rounded-xl p-4 flex flex-col items-center gap-2">
                                <span class="w-9 h-9 rounded-lg bg-gray-50 text-gray-500 flex items-center justify-center">
                                    @include('admin.partials.scenario-icon', ['icon' => 'document', 'class' => 'w-4 h-4'])
                                </span>
                                <span class="text-xs font-medium text-gray-700">{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                    @error('process_type')<p class="text-xs text-red-500 mt-2">{{ $message }}</p>@enderror
                </div>

                {{-- Индивидуальный процесс отдела: маршрут собирает инициатор при запуске --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" :checked="launchMode === 'composed'"
                               @change="launchMode = $event.target.checked ? 'composed' : 'fixed'"
                               class="mt-0.5 rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                        <span>
                            <span class="text-sm font-semibold text-gray-800">Индивидуальный процесс отдела</span>
                            <span class="block text-xs text-gray-400 mt-1 leading-relaxed">
                                Маршрут не задаётся заранее — инициатор сам собирает фазы (согласование,
                                утверждение, ознакомление, приём) и участников при запуске. Один такой процесс
                                выделяется отделу, чтобы не плодить сценарии. Тип документа и нумерация
                                выбираются при создании документа.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6 flex gap-8">
                    <div>
                        <p class="text-xs text-gray-500 mb-2">Иконка сценария</p>
                        <div class="flex gap-2">
                            @foreach(\App\Models\Workflow::ICONS as $iconOption)
                                <button type="button" @click="icon = '{{ $iconOption }}'"
                                        :class="icon === '{{ $iconOption }}' ? 'border-[#5B4FE8] text-[#5B4FE8] bg-[#5B4FE8]/5' : 'border-gray-200 text-gray-400 hover:border-gray-300'"
                                        class="w-9 h-9 rounded-lg border flex items-center justify-center">
                                    @include('admin.partials.scenario-icon', ['icon' => $iconOption, 'class' => 'w-4 h-4'])
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-gray-500 block mb-2">Владелец процесса</label>
                        <select name="owner_id" class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ old('owner_id', $scenario->owner_id ?? auth()->id()) == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('admin.scenarios.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
                    <button type="button" @click="step = 'classifier'" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                        Далее: классификатор →
                    </button>
                </div>
            </div>

            {{-- ============ Шаг 2: Классификатор ============ --}}
            <div x-show="step === 'classifier'" class="space-y-5">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <p class="text-sm font-semibold text-gray-800">Тип документа</p>
                    <p class="text-xs text-gray-400 mb-4">Тип документа — ключ маршрутизации. При создании документа выбранного подтипа система подставит этот сценарий</p>

                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <template x-for="type in types" :key="type.id">
                                <div>
                                    <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer py-1.5 px-2 rounded-lg hover:bg-gray-50">
                                        <input type="radio" name="document_type_id" :value="type.id" x-model.number="typeId" @change="onTypeChange()"
                                               class="border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                        <span class="text-xs font-mono font-semibold text-amber-500" x-text="type.code ? '[' + type.code + ']' : ''"></span>
                                        <span x-text="type.name"></span>
                                    </label>

                                    <div x-show="typeId === type.id" class="ml-7 space-y-1">
                                        <template x-for="subtype in type.subtypes" :key="subtype.id">
                                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer py-1">
                                                <input type="checkbox" name="subtypes[]" :value="subtype.id" x-model.number="subtypeIds"
                                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                                <span x-text="subtype.name"></span>
                                            </label>
                                        </template>
                                        <p x-show="type.subtypes.length === 0" class="text-xs text-gray-400 py-1">
                                            У типа нет подтипов — создайте их в классификаторе.
                                        </p>
                                    </div>
                                </div>
                            </template>
                            <p x-show="types.length === 0" class="text-sm text-gray-400">Типов документов пока нет.</p>
                        </div>

                        <div>
                            <p class="text-xs text-gray-500 mb-2">Выбранные подтипы</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="id in subtypeIds" :key="id">
                                    <span class="inline-flex items-center gap-1.5 bg-[#5B4FE8]/8 text-[#5B4FE8] border border-[#5B4FE8]/20 rounded-full px-3 py-1 text-xs font-medium">
                                        <span x-text="subtypeName(id)"></span>
                                        <button type="button" @click="subtypeIds = subtypeIds.filter(s => s !== id)" class="hover:text-red-500">×</button>
                                    </span>
                                </template>
                                <p x-show="subtypeIds.length === 0" class="text-xs text-gray-400">Подтип не выбран — сценарий не будет подставляться автоматически.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Автонумерация и название — наследуются из классификатора --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm font-semibold text-gray-800">Автонумерация и название</p>
                        <span class="text-xs text-gray-400 bg-gray-100 rounded-full px-2.5 py-1">унаследовано из классификатора</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Маска имени</label>
                            <div class="bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-xs font-mono text-gray-600 min-h-[42px]"
                                 x-text="currentType()?.name_template || '— не задана —'"></div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Маска номера</label>
                            <div class="bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-xs font-mono text-gray-600 min-h-[42px]"
                                 x-text="currentType()?.number_mask || '— без номера —'"></div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="text-xs text-gray-500 mb-1">Живой предпросмотр</p>
                        <div class="bg-white border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 font-medium" x-text="namePreview() || '—'"></div>
                    </div>

                    <a href="{{ route('admin.document-types.index') }}" class="inline-block text-sm text-[#5B4FE8] font-medium mt-4 hover:underline">Настроить в классификаторе →</a>
                </div>

                {{-- Шаблоны бланков: сотрудник заполнит документ по бланку вместо загрузки готового файла --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <p class="text-sm font-semibold text-gray-800">Шаблоны бланков</p>
                    <p class="text-xs text-gray-400 mb-4">
                        Выбранные бланки сотрудник сможет заполнить прямо в системе. Если не выбрать ни одного — документ приносят готовым файлом, как раньше.
                    </p>

                    <div x-show="blanks().length > 0" class="grid grid-cols-4 gap-3">
                        <template x-for="blank in blanks()" :key="blank.id">
                            <button type="button" @click="toggleBlank(blank.id)"
                                    :class="blankIds.includes(blank.id) ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200 hover:border-gray-300'"
                                    class="relative border rounded-xl p-3 text-left">
                                <span x-show="blankIds.includes(blank.id)"
                                      class="absolute top-2 right-2 w-4 h-4 rounded-full bg-[#5B4FE8] text-white text-[10px] flex items-center justify-center">✓</span>

                                <span class="block h-16 rounded-lg bg-gray-50 border border-gray-100 mb-2"></span>
                                <span class="block text-xs font-medium text-gray-700 truncate" x-text="blank.name"></span>
                                <span class="block text-[11px] text-gray-400 truncate" x-text="blank.subtype_name || 'весь тип'"></span>
                            </button>
                        </template>
                    </div>

                    <p x-show="!typeId" class="text-xs text-gray-400">Сначала выберите тип документа.</p>
                    <p x-show="typeId && blanks().length === 0" class="text-xs text-gray-400">
                        У выбранного типа нет активных бланков.
                        <a href="{{ route('admin.blank-templates.create') }}" class="text-[#5B4FE8] font-medium hover:underline">Создать бланк →</a>
                    </p>

                    <template x-for="id in blankIds" :key="id">
                        <input type="hidden" name="blank_template_ids[]" :value="id">
                    </template>

                    {{-- Чекбокса нет, пока нет бланков: без них файл — единственный способ принести документ --}}
                    <template x-if="blankIds.length > 0">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mt-4">
                            <input type="hidden" name="allow_file_upload" value="0">
                            <input type="checkbox" name="allow_file_upload" value="1" x-model="allowFileUpload"
                                   class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            Разрешить загрузку готового файла вместо бланка
                        </label>
                    </template>
                </div>

                <div class="flex items-center justify-between">
                    <button type="button" @click="step = 'basic'" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">← Назад</button>
                    <button type="button" @click="step = 'parameters'" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                        Далее: параметры →
                    </button>
                </div>
            </div>

            {{-- ============ Шаг 3: Параметры ============ --}}
            <div x-show="step === 'parameters'" class="space-y-4">
                <div class="flex items-start gap-2 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Параметры — это вопросы, которые сотрудник заполнит при создании документа. От ответов зависит, какие звенья войдут в маршрут.
                </div>

                <template x-for="(parameter, index) in parameters" :key="parameter.uid">
                    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                        <template x-if="parameter.id">
                            <input type="hidden" :name="`parameters[${index}][id]`" :value="parameter.id">
                        </template>

                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label class="text-xs text-gray-500 block mb-1">Ключ</label>
                                <input type="text" :name="`parameters[${index}][key]`" x-model="parameter.key" placeholder="transport" required
                                       class="w-full text-sm font-mono border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            </div>
                            <div class="flex-1">
                                <label class="text-xs text-gray-500 block mb-1">Подпись для пользователя</label>
                                <input type="text" :name="`parameters[${index}][label]`" x-model="parameter.label" placeholder="Транспорт" required
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            </div>
                            <button type="button" @click="parameters.splice(index, 1)" class="self-end mb-2 text-gray-300 hover:text-red-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500 block mb-1.5">Тип поля</label>
                            <input type="hidden" :name="`parameters[${index}][type]`" :value="parameter.type">
                            <div class="flex gap-2">
                                @foreach(\App\Models\WorkflowParameter::TYPES as $value => $label)
                                    <button type="button" @click="parameter.type = '{{ $value }}'"
                                            :class="parameter.type === '{{ $value }}' ? 'border-[#5B4FE8] text-[#5B4FE8] bg-[#5B4FE8]/5' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                            class="text-xs font-medium border rounded-lg px-3 py-1.5">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div x-show="['select', 'radio'].includes(parameter.type)">
                            <label class="text-xs text-gray-500 block mb-1.5">Варианты значений</label>
                            <div class="flex flex-wrap items-center gap-2">
                                <template x-for="(option, optionIndex) in parameter.options" :key="optionIndex">
                                    <span class="inline-flex items-center gap-1.5 border border-gray-200 rounded-full pl-3 pr-2 py-1">
                                        <input type="text" :name="`parameters[${index}][options][]`" x-model="parameter.options[optionIndex]"
                                               class="text-xs bg-transparent border-0 p-0 w-24 focus:outline-none focus:ring-0">
                                        <button type="button" @click="parameter.options.splice(optionIndex, 1)" class="text-gray-400 hover:text-red-500 text-xs">×</button>
                                    </span>
                                </template>
                                <button type="button" @click="parameter.options.push('')"
                                        class="text-xs font-medium text-[#5B4FE8] border border-dashed border-[#5B4FE8]/40 rounded-full px-3 py-1 hover:bg-[#5B4FE8]/5">+ Вариант</button>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="hidden" :name="`parameters[${index}][is_required]`" value="0">
                            <input type="checkbox" :name="`parameters[${index}][is_required]`" value="1" x-model="parameter.is_required"
                                   class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            Обязательный
                        </label>
                    </div>
                </template>

                <button type="button" @click="addParameter()"
                        class="text-sm font-medium text-[#5B4FE8] border border-dashed border-gray-300 rounded-xl px-4 py-2.5 hover:border-[#5B4FE8]">
                    + Добавить параметр
                </button>

                {{-- Пустое состояние — это норма, а не ошибка --}}
                <div x-show="parameters.length === 0"
                     class="border border-dashed border-gray-200 rounded-xl py-12 px-6 text-center">
                    <div class="w-10 h-10 rounded-full bg-gray-50 text-gray-400 flex items-center justify-center mx-auto mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    </div>
                    <p class="text-sm text-gray-500 max-w-md mx-auto">
                        Сценарий без параметров — маршрут будет одинаковым для всех документов этого типа.
                        Добавьте параметр, если состав согласующих должен меняться.
                    </p>
                    <button type="button" @click="addParameter()" class="mt-4 px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">+ Добавить параметр</button>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="button" @click="step = 'classifier'" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">← Назад</button>
                    <div class="flex items-center gap-3">
                        <button type="submit" @click="$refs.stepInput.value = 'parameters'"
                                class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                            Сохранить черновик
                        </button>
                        <button type="submit" @click="$refs.stepInput.value = 'route'"
                                class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                            Далее: маршрут →
                        </button>
                    </div>
                </div>
            </div>

            {{-- ============ Шаг 5: Права ============ --}}
            <div x-show="step === 'rights'" class="space-y-5">
                <div class="flex items-start gap-2 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>
                        Определяет, кто может запускать документы по этому сценарию.
                        Ничего не выбрано — сценарий доступен всем. Выбранные отделы ограничивают доступ;
                        отдельные сотрудники получают доступ дополнительно, независимо от отдела.
                    </span>
                </div>

                {{-- Отделы --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-sm font-semibold text-gray-800">Отделы</p>
                        <button type="button" x-show="allowedDepartments.length > 0" @click="allowedDepartments = []"
                                class="text-xs text-gray-400 hover:text-red-500">Очистить</button>
                    </div>
                    <p class="text-xs text-gray-400 mb-4">Сотрудники этих отделов смогут запускать документы по сценарию.</p>

                    <div class="grid grid-cols-2 gap-x-6 gap-y-1 max-h-72 overflow-y-auto">
                        <template x-for="department in allDepartments" :key="department.id">
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer py-1.5 px-2 rounded-lg hover:bg-gray-50">
                                <input type="checkbox" name="allowed_departments[]" :value="department.id" x-model.number="allowedDepartments"
                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                <span x-text="department.name"></span>
                            </label>
                        </template>
                        <p x-show="allDepartments.length === 0" class="text-sm text-gray-400 py-1">Отделов пока нет.</p>
                    </div>
                </div>

                {{-- Дополнительные сотрудники --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <p class="text-sm font-semibold text-gray-800">Дополнительные сотрудники</p>
                    <p class="text-xs text-gray-400 mb-4">Получат доступ персонально — даже если их отдел не выбран выше.</p>

                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <template x-for="id in allowedUsers" :key="id">
                            <span class="inline-flex items-center gap-1.5 bg-[#5B4FE8]/8 text-[#5B4FE8] border border-[#5B4FE8]/20 rounded-full px-3 py-1 text-xs font-medium">
                                <span x-text="userName(id)"></span>
                                <button type="button" @click="removeUser(id)" class="hover:text-red-500">×</button>
                            </span>
                        </template>
                        <p x-show="allowedUsers.length === 0" class="text-xs text-gray-400">Персональный доступ никому не выдан.</p>
                    </div>

                    <select @change="addUser($event.target.value); $event.target.value = ''"
                            class="w-full max-w-md text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">+ Добавить сотрудника</option>
                        <template x-for="person in availableUsers()" :key="person.id">
                            <option :value="person.id" x-text="person.name + (person.department ? ' — ' + person.department : '')"></option>
                        </template>
                    </select>

                    <template x-for="id in allowedUsers" :key="'h' + id">
                        <input type="hidden" name="allowed_users[]" :value="id">
                    </template>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="button" @click="step = 'route'" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">← Назад</button>
                    <button type="submit" @click="$refs.stepInput.value = 'rights'"
                            class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                        Сохранить права
                    </button>
                </div>
            </div>
        </form>

        {{-- Шаг 4: маршрут (свой form — вложенные формы недопустимы) --}}
        @if($scenario)
            <div x-show="launchMode !== 'composed'">
                @include('admin.scenarios.route')
            </div>

            {{-- composed-сценарий не несёт фиксированного маршрута — собирать нечего --}}
            <div x-show="step === 'route' && launchMode === 'composed'"
                 class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <div class="w-11 h-11 rounded-full bg-[#5B4FE8]/8 text-[#5B4FE8] flex items-center justify-center mx-auto mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-800">Маршрут собирает инициатор при запуске</p>
                <p class="text-xs text-gray-400 mt-1.5 max-w-md mx-auto leading-relaxed">
                    Это индивидуальный процесс отдела — фиксированный маршрут ему не нужен. Осталось задать
                    отдел на шаге «Права». Не забудьте сохранить.
                </p>
                <button type="button" @click="step = 'rights'"
                        class="mt-4 px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                    Перейти к правам →
                </button>
            </div>
        @endif
    </div>

    <script>
    function scenarioWizard() {
        return {
            step: @json($initialStep),
            name: @json(old('name', $scenario->name ?? '')),
            icon: @json(old('icon', $scenario->icon ?? 'document')),
            processType: @json(old('process_type', $scenario->process_type ?? 'document_flow')),
            launchMode: @json(old('launch_mode', $scenario->launch_mode ?? 'fixed')),

            types: @json($typesData),
            typeId: @json($selectedTypeId ? (int) $selectedTypeId : null),
            subtypeIds: @json(array_map('intval', $selectedSubtypes)),

            allBlanks: @json($blanksData),
            blankIds: @json(array_map('intval', $selectedBlanks)),
            allowFileUpload: @json((bool) old('allow_file_upload', $scenario->allow_file_upload ?? true)),

            allDepartments: @json($departmentsData),
            allUsers: @json($usersData),
            allowedDepartments: @json(array_map('intval', $selectedDepartments ?? [])),
            allowedUsers: @json(array_map('intval', $selectedUsers ?? [])),

            parameters: @json($parametersData).map((p, i) => ({ ...p, uid: 'p' + i })),

            /**
             * Маршрут пишется в звенья сценария, поэтому сначала сохраняем черновик.
             * Значение пишем в поле напрямую: requestSubmit() сработает раньше, чем Alpine
             * успеет прокинуть реактивное значение в DOM.
             */
            saveAndGoToRoute() {
                this.$refs.stepInput.value = 'route';
                this.$refs.mainForm.requestSubmit();
            },

            currentType() {
                return this.types.find(t => t.id === this.typeId) || null;
            },

            subtypeName(id) {
                const subtype = this.currentType()?.subtypes.find(s => s.id === id);
                return subtype ? subtype.name : '—';
            },

            /** Switching the type drops subtypes of the previous one — they belong to it. Бланки тоже: они принадлежат типу. */
            onTypeChange() {
                this.subtypeIds = [];
                this.blankIds = [];
            },

            /** Бланк типа предлагается всегда; бланк подтипа — только если этот подтип в сценарии. */
            blanks() {
                return this.allBlanks.filter(blank => blank.document_type_id === this.typeId
                    && (!blank.document_subtype_id
                        || this.subtypeIds.length === 0
                        || this.subtypeIds.includes(blank.document_subtype_id)));
            },

            toggleBlank(id) {
                this.blankIds = this.blankIds.includes(id)
                    ? this.blankIds.filter(blankId => blankId !== id)
                    : [...this.blankIds, id];
            },

            namePreview() {
                const type = this.currentType();
                if (!type || !type.name_template) return '';

                const context = previewContext(type.code, type.name, '', '', []);
                context['описание'] = 'Поставка';
                context['контрагент'] = 'ООО «Ромашка»';
                context['номер'] = '114';
                context['дата'] = new Date().toLocaleDateString('ru-RU');

                return renderMask(type.name_template, context);
            },

            addParameter() {
                this.parameters.push({
                    uid: 'p' + Date.now(),
                    id: null, key: '', label: '', type: 'select', options: [''], is_required: false,
                });
            },

            // ——— Права запуска ———
            availableUsers() {
                return this.allUsers.filter(user => !this.allowedUsers.includes(user.id));
            },

            addUser(value) {
                const id = Number(value);
                if (id && !this.allowedUsers.includes(id)) {
                    this.allowedUsers.push(id);
                }
            },

            removeUser(id) {
                this.allowedUsers = this.allowedUsers.filter(userId => userId !== id);
            },

            userName(id) {
                return this.allUsers.find(user => user.id === id)?.name ?? ('#' + id);
            },
        };
    }
    </script>
</x-app-layout>
