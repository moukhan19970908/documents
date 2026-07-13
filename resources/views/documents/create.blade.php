<x-app-layout>
    <x-slot name="title">Новый документ — Vamin</x-slot>

    @include('admin.partials.mask-preview-script')

    @php
        $user = auth()->user();

        $typesData = $documentTypes->map(fn($type) => [
            'id'                  => $type->id,
            'code'                => $type->code,
            'name'                => $type->name,
            'name_template'       => $type->name_template,
            'default_workflow_id' => $type->default_workflow_id,
            'allow_manual'        => (bool) $type->numerator?->allowsManualFor($user),
            'fields'              => $type->fields->map(fn($f) => [
                'field_key'    => $f->field_key,
                'label'        => $f->label,
                'field_type'   => $f->field_type,
                'options'      => $f->options ?? [],
                'reference_to' => $f->reference_to,
                'is_required'  => (bool) $f->is_required,
            ])->values(),
            'subtypes' => $type->subtypes->map(fn($s) => [
                'id'            => $s->id,
                'code'          => $s->code,
                'name'          => $s->name,
                'name_template' => $s->name_template,
                'allow_manual'  => (bool) $s->effectiveNumerator()?->allowsManualFor($user),
                'workflows'     => $s->workflows->map(fn($w) => [
                    'id'         => $w->id,
                    'name'       => $w->name,
                    'allow_file' => $w->allowsFileUpload(),
                    'blanks'     => $w->blankTemplates->map(fn($b) => [
                        'id'          => $b->id,
                        'name'        => $b->name,
                        'description' => $b->description,
                    ])->values(),
                    'parameters' => $w->parameters->map(fn($p) => [
                        'key'         => $p->key,
                        'label'       => $p->label,
                        'type'        => $p->type,
                        'options'     => $p->options ?? [],
                        'is_required' => (bool) $p->is_required,
                    ])->values(),
                    'stages'     => $w->stages->map(fn($st) => [
                        'name'      => $st->name,
                        'phase'     => $st->phase,
                        'key'       => $st->condition_key,
                        'operator'  => $st->condition_operator,
                        'value'     => $st->condition_value,
                    ])->values(),
                ])->values(),
                'fields'        => $s->fields->map(fn($f) => [
                    'field_key'    => $f->field_key,
                    'label'        => $f->label,
                    'field_type'   => $f->field_type,
                    'options'      => $f->options ?? [],
                    'reference_to' => $f->reference_to,
                    'is_required'  => (bool) $f->is_required,
                ])->values(),
            ])->values(),
        ]);

        $fallbackWorkflows = $workflows->map(fn($w) => [
            'id'         => $w->id,
            'name'       => $w->name,
            'allow_file' => $w->allowsFileUpload(),
            'blanks'     => $w->blankTemplates->map(fn($b) => [
                'id'          => $b->id,
                'name'        => $b->name,
                'description' => $b->description,
            ])->values(),
        ])->values();
        $referenceOptions = [
            'department' => \App\Models\Department::orderBy('name')->pluck('name'),
            'user'       => \App\Models\User::where('is_active', true)->orderBy('name')->pluck('name'),
        ];
    @endphp

    <div class="max-w-2xl" x-data="documentCreate()">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Новый документ</h1>
        </div>

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

                {{-- Тип --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Тип документа *</label>
                    <select name="document_type_id" x-model.number="typeId" @change="onTypeChange()" required
                            class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                        <option value="">— Выберите тип —</option>
                        <template x-for="type in types" :key="type.id">
                            <option :value="type.id" x-text="(type.code ? '[' + type.code + '] ' : '') + type.name"></option>
                        </template>
                    </select>
                    @error('document_type_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Подтип --}}
                <div x-show="currentType() && currentType().subtypes.length > 0">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Подтип *</label>
                    <select name="document_subtype_id" x-model.number="subtypeId" @change="onSubtypeChange()"
                            class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                        <option value="">— Выберите подтип —</option>
                        <template x-for="subtype in (currentType() ? currentType().subtypes : [])" :key="subtype.id">
                            <option :value="subtype.id" x-text="subtype.name"></option>
                        </template>
                    </select>
                    @error('document_subtype_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Сценарий: один — подставляется молча, несколько — выбор --}}
                <div x-show="scenarios().length > 1">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Сценарий *</label>
                    <select name="workflow_id" x-model.number="workflowId" @change="resetSource()"
                            class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                        <option value="">— Выберите сценарий —</option>
                        <template x-for="wf in scenarios()" :key="wf.id">
                            <option :value="wf.id" x-text="wf.name"></option>
                        </template>
                    </select>
                </div>
                <template x-if="scenarios().length === 1">
                    <input type="hidden" name="workflow_id" :value="scenarios()[0].id">
                </template>
                <p x-show="typeId && scenarios().length === 0" class="text-xs text-amber-600">
                    К выбранному типу не привязан сценарий — документ сохранится черновиком без маршрута.
                </p>

                {{-- Параметры запуска: ответы решают, какие звенья войдут в маршрут --}}
                <div x-show="parameters().length > 0" class="border-t border-gray-100 pt-5 space-y-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Параметры запуска</p>

                    <template x-for="parameter in parameters()" :key="parameter.key">
                        <div>
                            <label class="text-xs font-medium text-gray-700 block mb-1">
                                <span x-text="parameter.label"></span>
                                <span x-show="parameter.is_required" class="text-red-500">*</span>
                            </label>

                            <template x-if="parameter.type === 'select'">
                                <select :name="`parameters[${parameter.key}]`" x-model="answers[parameter.key]" :required="parameter.is_required"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                                    <option value="">—</option>
                                    <template x-for="option in parameter.options" :key="option">
                                        <option :value="option" x-text="option"></option>
                                    </template>
                                </select>
                            </template>

                            <template x-if="parameter.type === 'radio'">
                                <div class="flex flex-wrap gap-4">
                                    <template x-for="option in parameter.options" :key="option">
                                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="radio" :name="`parameters[${parameter.key}]`" :value="option" x-model="answers[parameter.key]"
                                                   class="border-gray-300 text-[#6C5CE7] focus:ring-[#6C5CE7]">
                                            <span x-text="option"></span>
                                        </label>
                                    </template>
                                </div>
                            </template>

                            <template x-if="parameter.type === 'boolean'">
                                <select :name="`parameters[${parameter.key}]`" x-model="answers[parameter.key]"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                                    <option value="">—</option>
                                    <option value="Да">Да</option>
                                    <option value="Нет">Нет</option>
                                </select>
                            </template>

                            <template x-if="['number', 'date', 'reference'].includes(parameter.type)">
                                <input :type="parameter.type === 'number' ? 'number' : (parameter.type === 'date' ? 'date' : 'text')"
                                       :name="`parameters[${parameter.key}]`" x-model="answers[parameter.key]" :required="parameter.is_required"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                            </template>
                        </div>
                    </template>

                    {{-- Предпросмотр маршрута перестраивается сразу, до запуска --}}
                    <div class="bg-gray-50 border border-gray-100 rounded-lg px-4 py-3">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Маршрут по вашим ответам</p>
                        <div class="space-y-1">
                            <template x-for="(stage, index) in routePreview()" :key="index">
                                <div class="flex items-center gap-2 text-sm text-gray-700">
                                    <span class="w-5 h-5 rounded-full bg-white border border-gray-200 text-[10px] font-semibold text-gray-500 flex items-center justify-center" x-text="index + 1"></span>
                                    <span x-text="stage.name"></span>
                                    <span x-show="stage.key" class="text-xs text-amber-600" x-text="'по условию ' + stage.key"></span>
                                </div>
                            </template>
                            <p x-show="routePreview().length === 0" class="text-sm text-gray-400">Маршрут пуст.</p>
                        </div>
                    </div>
                </div>

                {{-- Описание — участвует в автоназвании --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Описание</label>
                    <input type="text" name="data[описание]" x-model="values['описание']"
                           placeholder="На отгрузку товаров"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                </div>

                {{-- Атрибуты типа и подтипа --}}
                <div x-show="fields().length > 0" class="border-t border-gray-100 pt-5 space-y-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Атрибуты</p>

                    <template x-for="field in fields()" :key="field.field_key">
                        <div>
                            <label class="text-xs font-medium text-gray-700 block mb-1">
                                <span x-text="field.label"></span>
                                <span x-show="field.is_required" class="text-red-500">*</span>
                            </label>

                            <template x-if="['text', 'number', 'date'].includes(field.field_type)">
                                <input :type="field.field_type === 'text' ? 'text' : field.field_type"
                                       :name="`data[${field.field_key}]`"
                                       x-model="values[field.field_key]"
                                       :required="field.is_required"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                            </template>

                            <template x-if="field.field_type === 'textarea'">
                                <textarea :name="`data[${field.field_key}]`" x-model="values[field.field_key]" rows="3"
                                          :required="field.is_required"
                                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]"></textarea>
                            </template>

                            <template x-if="field.field_type === 'select'">
                                <select :name="`data[${field.field_key}]`" x-model="values[field.field_key]" :required="field.is_required"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                                    <option value="">—</option>
                                    <template x-for="option in field.options" :key="option">
                                        <option :value="option" x-text="option"></option>
                                    </template>
                                </select>
                            </template>

                            <template x-if="field.field_type === 'boolean'">
                                <select :name="`data[${field.field_key}]`" x-model="values[field.field_key]"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                                    <option value="">—</option>
                                    <option value="Да">Да</option>
                                    <option value="Нет">Нет</option>
                                </select>
                            </template>

                            <template x-if="field.field_type === 'reference'">
                                <select :name="`data[${field.field_key}]`" x-model="values[field.field_key]" :required="field.is_required"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                                    <option value="">—</option>
                                    <template x-for="option in (references[field.reference_to] || [])" :key="option">
                                        <option :value="option" x-text="option"></option>
                                    </template>
                                </select>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Название: производная от полей --}}
                <div class="border-t border-gray-100 pt-5">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest">Название документа *</label>
                        <label x-show="template()" class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
                            <input type="checkbox" x-model="manualTitle" class="rounded">
                            Изменить вручную
                        </label>
                    </div>
                    <input type="text" name="title" x-model="title" required
                           :readonly="template() && !manualTitle"
                           :class="template() && !manualTitle ? 'bg-gray-50 text-gray-600' : ''"
                           placeholder="Введите название документа"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                    <p x-show="template() && !manualTitle" class="text-xs text-gray-400 mt-1">
                        Собирается по маске типа. Номер и дата подставятся при запуске.
                    </p>
                    @error('title')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Ручной номер (регистрация задним числом) --}}
                <div x-show="allowsManual()">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Номер вручную</label>
                    <input type="text" name="manual_number" value="{{ old('manual_number') }}"
                           placeholder="Оставьте пустым — номер выдаст система"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                    <p class="text-xs text-gray-400 mt-1">Для документов из бумажного журнала. Счётчик не сдвигается.</p>
                </div>

                {{-- Крайний срок --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Крайний срок</label>
                    <input type="date" name="deadline_at" value="{{ old('deadline_at') }}"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                    <p class="text-xs text-gray-400 mt-1">Необязательно</p>
                </div>

                {{-- Тело документа: бланк системы или готовый файл --}}
                <div class="border-t border-gray-100 pt-5">
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-widest mb-3">Документ</p>

                    {{-- Способ выбираем, только если сценарий предлагает бланки --}}
                    <div x-show="blanks().length > 0" class="space-y-3">
                        <div class="flex gap-2">
                            <button type="button" @click="source = 'blank'"
                                    :class="source === 'blank' ? 'border-[#6C5CE7] text-[#6C5CE7] bg-[#6C5CE7]/5' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                    class="text-sm font-medium border rounded-lg px-4 py-2">Заполнить бланк</button>
                            <button type="button" x-show="allowsFile()" @click="source = 'file'"
                                    :class="source === 'file' ? 'border-[#6C5CE7] text-[#6C5CE7] bg-[#6C5CE7]/5' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                    class="text-sm font-medium border rounded-lg px-4 py-2">Загрузить готовый файл</button>
                        </div>

                        <div x-show="source === 'blank'" class="space-y-3">
                            <div class="grid grid-cols-3 gap-3">
                                <template x-for="blank in blanks()" :key="blank.id">
                                    <button type="button" @click="blankId = blank.id"
                                            :class="blankId === blank.id ? 'border-[#6C5CE7] ring-1 ring-[#6C5CE7]' : 'border-gray-200 hover:border-gray-300'"
                                            class="relative border rounded-xl p-3 text-left">
                                        <span x-show="blankId === blank.id"
                                              class="absolute top-2 right-2 w-4 h-4 rounded-full bg-[#6C5CE7] text-white text-[10px] flex items-center justify-center">✓</span>
                                        <span class="block h-14 rounded-lg bg-gray-50 border border-gray-100 mb-2"></span>
                                        <span class="block text-xs font-medium text-gray-700 truncate" x-text="blank.name"></span>
                                        <span class="block text-[11px] text-gray-400 truncate" x-text="blank.description || ''"></span>
                                    </button>
                                </template>
                            </div>
                            <p class="text-xs text-gray-400">Бланк откроется для заполнения на странице документа сразу после создания.</p>
                        </div>
                    </div>

                    {{-- Сценарий без бланков — документ приносят готовым файлом --}}
                    <div x-show="blanks().length === 0 || source === 'file'" class="mt-3">
                        <input type="file" name="file"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#6C5CE7] file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700">
                        <p class="text-xs text-gray-400 mt-1">Максимальный размер файла: 50 МБ</p>
                    </div>

                    <input type="hidden" name="blank_template_id" :value="source === 'blank' ? blankId : ''">

                    @error('blank_template_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    @error('file')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#6C5CE7] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Создать документ
                </button>
                <a href="{{ route('documents.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                    Отмена
                </a>
            </div>
        </form>
    </div>

    <script>
    function documentCreate() {
        return {
            types: @json($typesData),
            fallbackWorkflows: @json($fallbackWorkflows),
            references: @json($referenceOptions),

            typeId: @json(old('document_type_id', '')),
            subtypeId: @json(old('document_subtype_id', '')),
            workflowId: @json(old('workflow_id', '')),
            values: @json(old('data', (object) [])),
            answers: @json(old('parameters', (object) [])),
            title: @json(old('title', '')),
            manualTitle: false,

            source: 'file',
            blankId: @json(old('blank_template_id', '')),

            init() {
                this.$watch('values', () => this.syncTitle(), { deep: true });

                // После ошибки валидации возвращаем выбранный бланк, а не сбрасываем способ.
                this.blankId ? (this.source = 'blank') : this.resetSource();
            },

            blanks() {
                return this.currentWorkflow()?.blanks ?? [];
            },

            allowsFile() {
                return this.currentWorkflow()?.allow_file ?? true;
            },

            /** Есть бланки — заполнение по бланку и есть способ по умолчанию; единственный бланк выбираем сразу. */
            resetSource() {
                const blanks = this.blanks();

                this.source  = blanks.length > 0 ? 'blank' : 'file';
                this.blankId = blanks.length === 1 ? blanks[0].id : '';
            },

            currentWorkflow() {
                const id = Number(this.workflowId) || (this.scenarios().length === 1 ? this.scenarios()[0].id : null);
                return id ? (this.scenarios().find(w => w.id === id) || null) : null;
            },

            parameters() {
                return this.currentWorkflow()?.parameters ?? [];
            },

            /** Mirrors WorkflowStage::passesCondition() — the same rule the engine applies at launch. */
            routePreview() {
                const stages = this.currentWorkflow()?.stages ?? [];

                return stages.filter(stage => {
                    if (!stage.key) return true;

                    const actual = String(this.answers[stage.key] ?? '');
                    const expected = String(stage.value ?? '');

                    switch (stage.operator) {
                        case '!=': return actual !== expected;
                        case 'in': return expected.split(',').map(v => v.trim()).includes(actual);
                        case '>':  return parseFloat(actual) > parseFloat(expected);
                        case '<':  return parseFloat(actual) < parseFloat(expected);
                        default:   return actual === expected;
                    }
                });
            },

            currentType() {
                return this.types.find(t => t.id === this.typeId) || null;
            },

            currentSubtype() {
                const type = this.currentType();
                return type ? (type.subtypes.find(s => s.id === this.subtypeId) || null) : null;
            },

            /** Scenarios of the subtype; a type without subtypes falls back to its default workflow. */
            scenarios() {
                const subtype = this.currentSubtype();
                if (subtype) {
                    return subtype.workflows;
                }

                const type = this.currentType();
                if (!type || type.subtypes.length > 0) {
                    return [];
                }
                if (type.default_workflow_id) {
                    return this.fallbackWorkflows.filter(w => w.id === type.default_workflow_id);
                }
                return this.fallbackWorkflows;
            },

            fields() {
                const type = this.currentType();
                if (!type) return [];
                const subtype = this.currentSubtype();
                return [...type.fields, ...(subtype ? subtype.fields : [])];
            },

            template() {
                const subtype = this.currentSubtype();
                const type = this.currentType();
                return (subtype && subtype.name_template) || (type && type.name_template) || '';
            },

            allowsManual() {
                const subtype = this.currentSubtype();
                const type = this.currentType();
                return subtype ? subtype.allow_manual : (type ? type.allow_manual : false);
            },

            onTypeChange() {
                this.subtypeId = '';
                this.workflowId = '';
                this.values = {};
                this.answers = {};
                this.resetSource();
                this.syncTitle();
            },

            onSubtypeChange() {
                const scenarios = this.scenarios();
                this.workflowId = scenarios.length === 1 ? scenarios[0].id : '';
                this.answers = {};
                this.resetSource();
                this.syncTitle();
            },

            /** The name mirrors the fields until the user takes it over. */
            syncTitle() {
                if (this.manualTitle || !this.template()) return;

                const type = this.currentType();
                const subtype = this.currentSubtype();

                const context = {
                    'код_типа':    type ? (type.code || '') : '',
                    'код_подтипа': subtype ? (subtype.code || '') : '',
                    'тип':         type ? type.name : '',
                    'подтип':      subtype ? subtype.name : '',
                    'описание':    this.values['описание'] || '',
                    'номер':       '___',
                    'дата':        '__.__.____',
                    'отдел':       @json($user->department?->name ?? ''),
                    'инициатор':   @json($user->name),
                };

                this.fields().forEach(field => {
                    context[field.field_key.trim().toLowerCase()] = this.values[field.field_key] || '';
                });

                this.title = renderMask(this.template(), context);
            },
        };
    }
    </script>
</x-app-layout>
