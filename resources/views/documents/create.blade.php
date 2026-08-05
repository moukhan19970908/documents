<x-app-layout>
    <x-slot name="title">Запуск нового документа — Vamin</x-slot>

    @include('admin.partials.mask-preview-script')

    @php
        $user = auth()->user();

        $roleNames = \App\Models\Role::pluck('name', 'code');

        $mapFields = fn ($fields) => $fields->map(fn ($f) => [
            'field_key'    => $f->field_key,
            'label'        => $f->label,
            'field_type'   => $f->field_type,
            'options'      => $f->options ?? [],
            'reference_to' => $f->reference_to,
            'is_required'  => (bool) $f->is_required,
        ])->values();

        $mapWorkflow = function ($w) use ($roleNames) {
            return [
                'blanks'     => $w->blankTemplates->map(fn ($b) => [
                    'id'          => $b->id,
                    'name'        => $b->name,
                    'description' => $b->description,
                    'content'     => $b->content,
                ])->values(),
                'parameters' => $w->parameters->map(fn ($p) => [
                    'key'         => $p->key,
                    'label'       => $p->label,
                    'type'        => $p->type,
                    'options'     => $p->options ?? [],
                    'is_required' => (bool) $p->is_required,
                ])->values(),
                'stages'     => $w->stages->map(fn ($st) => [
                    'name'       => $st->name,
                    'phase'      => $st->phase ?: 'approval',
                    'key'        => $st->condition_key,
                    'operator'   => $st->condition_operator,
                    'value'      => $st->condition_value,
                    'sla_days'   => $st->sla_days,
                    'resolver'   => $st->resolver,
                    'role_code'  => $st->resolver === 'group' ? $st->group_role : null,
                    'role_name'  => $st->group_role ? ($roleNames[$st->group_role] ?? $st->group_role) : null,
                    // Роль/отдел разворачиваем в конкретных людей — теми же правилами, что и публикация,
                    // чтобы предпросмотр совпал с составом, который запустит движок.
                    'approvers'  => $st->resolvedApprovers()
                        ->map(fn ($u) => [
                            'id'       => $u->id,
                            'name'     => $u->name,
                            'position' => $u->role_label ?: $u->position,
                        ])->values(),
                ])->values(),
            ];
        };

        // Сценарий-первым: карточка = сценарий, тип и подтип подтягиваются из него.
        $scenarios = collect();

        foreach ($documentTypes as $type) {
            $typeFields = $mapFields($type->fields);

            if ($type->subtypes->isNotEmpty()) {
                foreach ($type->subtypes as $subtype) {
                    foreach ($subtype->workflows as $workflow) {
                        // composed-сценарии показываются отдельной карточкой — не по типу/подтипу.
                        if ($workflow->launch_mode === 'composed') {
                            continue;
                        }
                        $scenarios->push(array_merge([
                            'id'            => $workflow->id,
                            'name'          => $workflow->name,
                            'description'   => $workflow->description,
                            'type_id'       => $type->id,
                            'type_code'     => $type->code,
                            'type_name'     => $type->name,
                            'subtype_id'    => $subtype->id,
                            'subtype_code'  => $subtype->code,
                            'subtype_name'  => $subtype->name,
                            'name_template' => $subtype->name_template ?: $type->name_template,
                            'allow_manual'  => (bool) $subtype->effectiveNumerator()?->allowsManualFor($user),
                            'allow_file'    => $workflow->allowsFileUpload(),
                            'fields'        => $typeFields->concat($mapFields($subtype->fields))->values(),
                        ], $mapWorkflow($workflow)));
                    }
                }
                continue;
            }

            // Тип без подтипов идёт со своим сценарием по умолчанию; без него —
            // показывать нечего (иначе тип склеился бы со всеми сценариями подряд).
            $fallback = $type->default_workflow_id
                ? $workflows->where('id', $type->default_workflow_id)
                : collect();

            foreach ($fallback as $workflow) {
                if ($workflow->launch_mode === 'composed') {
                    continue;
                }
                $scenarios->push(array_merge([
                    'id'            => $workflow->id,
                    'name'          => $workflow->name,
                    'description'   => $workflow->description,
                    'type_id'       => $type->id,
                    'type_code'     => $type->code,
                    'type_name'     => $type->name,
                    'subtype_id'    => null,
                    'subtype_code'  => null,
                    'subtype_name'  => null,
                    'name_template' => $type->name_template,
                    'allow_manual'  => (bool) $type->numerator?->allowsManualFor($user),
                    'allow_file'    => $workflow->allowsFileUpload(),
                    'fields'        => $typeFields,
                ], $mapWorkflow($workflow)));
            }
        }

        // Индивидуальный процесс отдела (composed): маршрут собирает инициатор при запуске,
        // а тип и подтип берутся из сценария (классификатор конструктора) — инициатор их не выбирает.
        $allowManualFor = fn ($numerator) => (bool) $numerator?->allowsManualFor($user);

        // Классификатор composed-сценариев берём из их настроек — подгружаем разом.
        $workflows->loadMissing(['documentType.numerator', 'documentType.fields', 'subtypes.fields']);

        foreach ($workflows as $workflow) {
            if ($workflow->launch_mode !== 'composed') {
                continue;
            }

            $type    = $workflow->documentType;              // тип из классификатора сценария
            $subtype = $workflow->subtypes->first();          // подтип из классификатора сценария

            $composedFields = collect();
            if ($type)    $composedFields = $composedFields->concat($mapFields($type->fields));
            if ($subtype) $composedFields = $composedFields->concat($mapFields($subtype->fields));

            // Нумерация — по подтипу (если задан), иначе по типу.
            $numerator = $subtype?->effectiveNumerator() ?? $type?->numerator;

            $scenarios->push([
                'id'            => $workflow->id,
                'name'          => $workflow->name,
                'description'   => $workflow->description,
                'composed'      => true,
                'type_id'       => $type?->id,
                'type_code'     => $type?->code,
                'type_name'     => $type?->name,
                'subtype_id'    => $subtype?->id,
                'subtype_code'  => $subtype?->code,
                'subtype_name'  => $subtype?->name,
                'name_template' => $subtype?->name_template ?: $type?->name_template,
                'allow_manual'  => $allowManualFor($numerator),
                'allow_file'    => $workflow->allowsFileUpload(),
                'fields'        => $composedFields->values(),
                'blanks'        => [],
                'parameters'    => [],
                'stages'        => [],
            ]);
        }

        // Для «Своего сценария» инициатор сам выбирает тип и подтип — отдаём весь
        // доступный ему классификатор (те же типы/подтипы, что и в обычном запуске).
        $classifiers = $documentTypes->map(fn ($type) => [
            'id'            => $type->id,
            'code'          => $type->code,
            'name'          => $type->name,
            'name_template' => $type->name_template,
            'allow_manual'  => $allowManualFor($type->numerator),
            'fields'        => $mapFields($type->fields)->values(),
            'subtypes'      => $type->subtypes->map(fn ($st) => [
                'id'            => $st->id,
                'code'          => $st->code,
                'name'          => $st->name,
                'name_template' => $st->name_template,
                'allow_manual'  => $allowManualFor($st->effectiveNumerator()),
                'fields'        => $mapFields($st->fields)->values(),
            ])->values(),
        ])->values();

        // Вход «Свой сценарий» показывает только индивидуальные процессы из конструктора.
        if (request()->boolean('own')) {
            $scenarios = $scenarios->where('composed', true)->values();
        }

        $referenceOptions = [
            'department' => \App\Models\Department::orderBy('name')->pluck('name'),
            'user'       => \App\Models\User::where('is_active', true)->orderBy('name')->pluck('name'),
        ];

        $people = \App\Models\User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'position'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'position' => $p->position])
            ->values();

        // Фазы маршрута идут в этом порядке — тот же, что применяет движок.
        $phaseMeta = [
            'approval' => ['label' => 'Фаза согласования', 'dot' => 'bg-blue-500',   'fixed' => true],
            'approve'  => ['label' => 'Фаза утверждения',  'dot' => 'bg-emerald-500', 'fixed' => true],
            'opinion'  => ['label' => 'Фаза заключений',   'dot' => 'bg-sky-500',     'fixed' => true],
            'ack'      => ['label' => 'Фаза ознакомления', 'dot' => 'bg-amber-500',   'fixed' => false],
            'intake'   => ['label' => 'Фаза приёма',       'dot' => 'bg-purple-500',  'fixed' => false],
        ];
    @endphp

    @php $backRoute = $processMeta['index_route'] ?? 'documents.index'; @endphp
    <div x-data="documentCreate()" x-cloak>

        <div class="mb-5">
            <h1 class="text-xl font-bold text-gray-900">{{ $processMeta ? 'Новый документ — ' . $processMeta['label'] : 'Запуск нового документа' }}</h1>
        </div>

        {{-- Степпер --}}
        <div class="flex items-center gap-3 mb-6">
            @foreach(['Сценарий и маршрут', 'Документ', 'Запуск'] as $i => $label)
                @php $n = $i + 1; @endphp
                @if($i > 0)
                    <svg class="w-3.5 h-3.5 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                @endif
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full text-xs font-semibold flex items-center justify-center"
                          :class="step === {{ $n }} ? 'bg-[#5B4FE8] text-white' : 'bg-gray-100 text-gray-400'">{{ $n }}</span>
                    <span class="text-sm font-medium"
                          :class="step === {{ $n }} ? 'text-gray-900' : 'text-gray-400'">{{ $label }}</span>
                </div>
            @endforeach
        </div>

        {{-- Ошибки сервера могут относиться к любому шагу — показываем их разом наверху --}}
        @if($errors->any())
            <div class="bg-red-50 border border-red-100 rounded-xl px-4 py-3 mb-5">
                <p class="text-sm font-semibold text-red-600 mb-1">Документ не создан</p>
                <ul class="text-sm text-gray-600 list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Классификатор берётся из выбранного сценария (у composed — из его настроек конструктора) --}}
            <input type="hidden" name="workflow_id" :value="scenarioId">
            <input type="hidden" name="document_type_id" :value="cls()?.type_id ?? ''">
            <input type="hidden" name="document_subtype_id" :value="cls()?.subtype_id ?? ''">
            <input type="hidden" name="title" :value="title">

            {{-- ─────────────── Шаг 1: сценарий и маршрут ─────────────── --}}
            <div x-show="step === 1" class="max-w-4xl space-y-6">

                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Сценарий</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <template x-for="scenario in scenarios" :key="scenario.id + '-' + scenario.subtype_id">
                            <button type="button" @click="pickScenario(scenario)"
                                    :class="isPicked(scenario) ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200 hover:border-gray-300'"
                                    class="relative bg-white border rounded-xl p-4 text-left">
                                <span x-show="isPicked(scenario)" class="absolute top-3 right-3 w-4 h-4 rounded-full bg-[#5B4FE8] text-white text-[10px] flex items-center justify-center">✓</span>
                                <span class="block font-semibold text-gray-900 text-sm pr-6" x-text="scenario.name"></span>
                                <span class="block text-xs text-gray-500 mt-1 leading-relaxed" x-text="scenario.description || 'Без описания'"></span>
                                <span class="flex items-center gap-2 mt-3">
                                    {{-- Классификатор показываем так же, как конструктор: [код типа] подтип --}}
                                    <span class="text-[11px] bg-gray-100 text-gray-600 rounded px-2 py-0.5" x-text="classifierLabel(scenario)"></span>
                                    <span x-show="!scenario.composed" class="text-[11px] text-gray-400" x-text="scenario.stages.length + ' ' + stagePlural(scenario.stages.length)"></span>
                                    <span x-show="scenario.composed" class="text-[11px] text-[#5B4FE8]">маршрут при запуске</span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p x-show="scenarios.length === 0" class="text-sm text-gray-400">
                        Доступных сценариев нет — обратитесь к администратору.
                    </p>
                </div>

                {{-- Параметры запуска: ответы решают, какие звенья войдут в маршрут --}}
                <div x-show="parameters().length > 0">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Параметры запуска</p>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                        <template x-for="parameter in parameters()" :key="parameter.key">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1.5">
                                    <span x-text="parameter.label"></span>
                                    <span x-show="parameter.is_required" class="text-red-500">*</span>
                                </label>

                                {{-- Варианты выбора рисуем кнопками — так виден весь набор сразу --}}
                                <template x-if="['select', 'radio', 'boolean'].includes(parameter.type)">
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="option in optionsOf(parameter)" :key="option">
                                            <button type="button" @click="answers[parameter.key] = option"
                                                    :class="answers[parameter.key] === option
                                                            ? 'border-[#5B4FE8] text-[#5B4FE8] bg-[#5B4FE8]/5'
                                                            : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                                    class="text-sm font-medium border rounded-lg px-4 py-2" x-text="option"></button>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="['number', 'date', 'reference'].includes(parameter.type)">
                                    <input :type="parameter.type === 'number' ? 'number' : (parameter.type === 'date' ? 'date' : 'text')"
                                           x-model="answers[parameter.key]"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                </template>

                                <input type="hidden" :name="`parameters[${parameter.key}]`" :value="answers[parameter.key] ?? ''">
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Маршрут: собирается движком из сценария и ответов (фиксированный сценарий) --}}
                <div x-show="scenarioId && !current()?.composed">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">
                        Маршрут согласования — собран автоматически
                    </p>

                    <div class="space-y-5">
                        @foreach($phaseMeta as $phase => $meta)
                            <div x-show="stagesOf('{{ $phase }}').length > 0 || {{ $meta['fixed'] ? 'false' : 'true' }}">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}"></span>
                                    <span class="text-sm font-semibold text-gray-900">{{ $meta['label'] }}</span>
                                    @if($meta['fixed'])
                                        <span class="text-xs text-gray-400">— порядок и состав зафиксированы сценарием</span>
                                    @endif
                                </div>

                                @if(!$meta['fixed'])
                                    <p class="text-xs text-gray-400 mb-2 ml-4">
                                        Добавление участника не изменяет сам сценарий — звенья добавляются только к этому документу
                                    </p>
                                @endif

                                <div class="ml-4 border-l-2 border-gray-100 pl-4 space-y-2">
                                    <template x-for="(stage, index) in stagesOf('{{ $phase }}')" :key="index">
                                        <div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                                            {{-- Звено на роли: инициатор выбирает одного исполнителя из тех, у кого эта роль --}}
                                            <template x-if="stage.resolver === 'group' && stage.role_name">
                                                <div>
                                                    <p class="flex items-center gap-1.5 text-xs text-gray-500 mb-2">
                                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                        <span x-text="'Роль: ' + stage.role_name"></span>
                                                    </p>

                                                    <template x-if="stage.approvers.length > 0">
                                                        <div>
                                                            <select x-model="rolePicks[stage.role_code]"
                                                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                                                <option value="">— выберите исполнителя —</option>
                                                                <template x-for="approver in stage.approvers" :key="approver.id">
                                                                    <option :value="approver.id" x-text="approver.name + (approver.position ? ' — ' + approver.position : '')"></option>
                                                                </template>
                                                            </select>
                                                            <input type="hidden" :name="`role_picks[${stage.role_code}]`" :value="rolePicks[stage.role_code] ?? ''">
                                                        </div>
                                                    </template>

                                                    {{-- У роли нет ни одного сотрудника — выбирать не из кого --}}
                                                    <p x-show="stage.approvers.length === 0" class="text-sm text-amber-600"
                                                       x-text="'Нет сотрудников с ролью «' + stage.role_name + '» — обратитесь к администратору'"></p>
                                                </div>
                                            </template>

                                            {{-- Обычное звено: состав задан сценарием --}}
                                            <template x-if="!(stage.resolver === 'group' && stage.role_name)">
                                                <div class="flex items-center gap-3">
                                                    <template x-for="approver in stage.approvers" :key="approver.id">
                                                        <div class="flex items-center gap-2.5">
                                                            <span class="w-7 h-7 rounded-full bg-indigo-50 text-[#5B4FE8] text-[11px] font-semibold flex items-center justify-center shrink-0"
                                                                  x-text="initials(approver.name)"></span>
                                                            <span>
                                                                <span class="block text-sm text-gray-900" x-text="approver.name"></span>
                                                                <span class="block text-xs text-gray-400" x-text="approver.position || ''"></span>
                                                            </span>
                                                        </div>
                                                    </template>
                                                    <span x-show="stage.approvers.length === 0" class="text-sm text-gray-400" x-text="stage.name"></span>
                                                    <svg class="w-3.5 h-3.5 text-gray-300 ml-auto shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" title="Состав зафиксирован сценарием"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Ад-хок участники: только для ознакомления и приёма --}}
                                    @if(!$meta['fixed'])
                                        <div class="flex flex-wrap items-center gap-2">
                                            <template x-for="person in adhoc['{{ $phase }}']" :key="person.id">
                                                <span class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-gray-700 text-sm rounded-lg px-2.5 py-1.5">
                                                    <input type="hidden" name="adhoc[{{ $phase }}][]" :value="person.id">
                                                    <span class="w-5 h-5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-semibold flex items-center justify-center" x-text="initials(person.name)"></span>
                                                    <span x-text="person.name"></span>
                                                    <button type="button" @click="removeAdhoc('{{ $phase }}', person.id)" class="text-gray-400 hover:text-red-500">×</button>
                                                </span>
                                            </template>

                                            <select @change="addAdhoc('{{ $phase }}', $event.target.value); $event.target.value = ''"
                                                    class="text-sm border border-dashed border-[#5B4FE8] text-[#5B4FE8] rounded-lg px-3 py-1.5 bg-white focus:outline-none">
                                                <option value="">+ Добавить участника {{ $phase === 'ack' ? 'ознакомления' : 'приёма' }}</option>
                                                <template x-for="person in people" :key="person.id">
                                                    <option :value="person.id" x-text="person.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-xs text-gray-400 mt-3">
                        Участники ознакомления и приёма добавляются только к этому документу — сам сценарий не меняется.
                        Для звена на роли выберите одного исполнителя — звено уйдёт только ему.
                    </p>
                </div>

                {{-- ─────── Индивидуальный процесс: сборка маршрута (тип/подтип — из сценария) ─────── --}}
                <div x-show="scenarioId && current()?.composed" class="space-y-6">

                    {{-- Тип и подтип выбирает инициатор при запуске своего сценария --}}
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Тип документа</p>
                        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1.5">Тип <span class="text-red-500">*</span></label>
                                <select x-model="composedTypeId" @change="onComposedTypeChange()"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    <option value="">— выберите тип —</option>
                                    <template x-for="type in classifiers" :key="type.id">
                                        <option :value="type.id" x-text="(type.code ? '[' + type.code + '] ' : '') + type.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div x-show="composedType() && composedType().subtypes.length > 0">
                                <label class="block text-xs font-medium text-gray-500 mb-1.5">Подтип <span class="text-red-500">*</span></label>
                                <select x-model="composedSubtypeId" @change="syncTitle()"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    <option value="">— выберите подтип —</option>
                                    <template x-for="sub in (composedType()?.subtypes ?? [])" :key="sub.id">
                                        <option :value="sub.id" x-text="(sub.code ? '[' + sub.code + '] ' : '') + sub.name"></option>
                                    </template>
                                </select>
                            </div>

                            <p class="text-[11px] text-gray-400">Нумерация подставится по выбранному типу или подтипу.</p>
                        </div>
                    </div>

                    {{-- Маршрут: инициатор собирает фазы --}}
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Маршрут — соберите фазы</p>
                            <div class="flex items-center gap-2">
                                <select x-show="presets.length > 0" @change="applyPreset($event.target.value); $event.target.value = ''"
                                        class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-600 focus:outline-none">
                                    <option value="">Применить заготовку…</option>
                                    <template x-for="p in presets" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                                <button type="button" @click="savePreset()" x-show="composedRoute.some(b => b.participants.length > 0)"
                                        class="text-xs font-medium text-[#5B4FE8] border border-[#5B4FE8]/40 rounded-lg px-2.5 py-1.5 hover:bg-[#5B4FE8]/5">
                                    Сохранить как заготовку
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(block, index) in composedRoute" :key="index">
                                <div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                                    <div class="flex items-center gap-2 mb-2.5">
                                        <span class="w-2 h-2 rounded-full" :class="phaseDot(block.phase)"></span>
                                        <span class="text-sm font-semibold text-gray-900" x-text="phaseLabel(block.phase)"></span>
                                        <span class="text-[11px] text-gray-400" x-text="'#' + (index + 1)"></span>
                                        <button type="button" @click="removePhase(index)" class="ml-auto text-gray-300 hover:text-red-500" title="Убрать фазу">
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <template x-for="pid in block.participants" :key="pid">
                                            <span class="inline-flex items-center gap-1.5 bg-indigo-50 border border-indigo-100 text-gray-700 text-sm rounded-lg px-2.5 py-1.5">
                                                <span class="w-5 h-5 rounded-full bg-indigo-100 text-[#5B4FE8] text-[10px] font-semibold flex items-center justify-center" x-text="initials(personName(pid))"></span>
                                                <span x-text="personName(pid)"></span>
                                                <button type="button" @click="removeParticipant(index, pid)" class="text-gray-400 hover:text-red-500">×</button>
                                            </span>
                                        </template>
                                        <select @change="addParticipant(index, $event.target.value); $event.target.value = ''"
                                                class="text-sm border border-dashed border-[#5B4FE8] text-[#5B4FE8] rounded-lg px-3 py-1.5 bg-white focus:outline-none">
                                            <option value="">+ Добавить участника</option>
                                            <template x-for="person in people" :key="person.id">
                                                <option :value="person.id" x-text="person.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            <p x-show="composedRoute.length === 0" class="text-sm text-gray-400 border border-dashed border-gray-200 rounded-xl py-6 text-center">
                                Маршрут пуст — добавьте фазу ниже.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <template x-for="ph in phaseChoices" :key="ph.key">
                                <button type="button" @click="addPhase(ph.key)"
                                        class="text-sm font-medium text-gray-600 border border-dashed border-gray-300 rounded-lg px-3 py-1.5 hover:border-[#5B4FE8] hover:text-[#5B4FE8]"
                                        x-text="'+ ' + ph.label"></button>
                            </template>
                        </div>

                        <p class="text-xs text-gray-400 mt-3">
                            Фазы идут в порядке добавления. Согласование, утверждение и приём ждут решения участников;
                            ознакомление рассылается для чтения и не задерживает маршрут.
                        </p>
                    </div>

                    {{-- Скрытые поля собранного маршрута --}}
                    <template x-for="(block, index) in composedRoute" :key="'rh' + index">
                        <div>
                            <template x-if="block.participants.length > 0">
                                <input type="hidden" :name="`route[${index}][phase]`" :value="block.phase">
                            </template>
                            <template x-for="pid in block.participants" :key="'rp' + index + '-' + pid">
                                <input type="hidden" :name="`route[${index}][participants][]`" :value="pid">
                            </template>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route($backRoute) }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
                    <button type="button" @click="step = 2" :disabled="!canLeaveStep1()"
                            :class="canLeaveStep1() ? 'bg-[#5B4FE8] hover:bg-indigo-700' : 'bg-gray-200 cursor-not-allowed'"
                            class="flex items-center gap-2 text-white px-6 py-2.5 rounded-lg text-sm font-medium">
                        Далее: документ
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>

            {{-- ─────────────── Шаг 2: документ ─────────────── --}}
            <div x-show="step === 2" class="flex gap-6">

                <div class="flex-1 min-w-0 space-y-4">
                    {{-- Бланк документа --}}
                    <div x-show="blanks().length > 0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Бланк документа</p>
                        <div class="flex gap-3">
                            <template x-for="blank in blanks()" :key="blank.id">
                                <button type="button" @click="pickBlank(blank)"
                                        :class="blankId === blank.id ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200 hover:border-gray-300'"
                                        class="bg-white border rounded-xl p-3 w-40 text-left shrink-0">
                                    <span class="block h-16 rounded-lg bg-gray-50 border border-gray-100 mb-2"></span>
                                    <span class="block text-xs font-semibold text-gray-700 truncate" x-text="blank.name"></span>
                                </button>
                            </template>
                            <button type="button" x-show="allowsFile()" @click="source = 'file'"
                                    :class="source === 'file' ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200 hover:border-gray-300'"
                                    class="bg-white border rounded-xl p-3 w-40 text-left shrink-0">
                                <span class="block h-16 rounded-lg bg-gray-50 border border-gray-100 mb-2"></span>
                                <span class="block text-xs font-semibold text-gray-700">Загрузить файл</span>
                            </button>
                        </div>
                    </div>

                    {{-- Редактор бланка --}}
                    <div x-show="source === 'blank'">
                        @include('partials.blank-editor', ['content' => '', 'inputName' => 'body_html'])
                    </div>

                    {{-- Готовый файл вместо бланка --}}
                    <div x-show="source === 'file'" class="bg-white border border-gray-200 rounded-xl p-6">
                        <input type="file" name="file"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#5B4FE8] file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700">
                        <p class="text-xs text-gray-400 mt-2">Максимальный размер файла: 50 МБ</p>
                        @error('file')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <input type="hidden" name="blank_template_id" :value="source === 'blank' ? blankId : ''">
                </div>

                {{-- Свойства документа --}}
                <aside class="w-80 shrink-0 space-y-4">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Свойства документа</p>

                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Тип документа</label>
                            <input type="text" :value="cls()?.type_name ?? ''" readonly
                                   class="w-full text-sm bg-gray-50 text-gray-600 border border-gray-200 rounded-lg px-3 py-2.5">
                        </div>

                        <div x-show="cls()?.subtype_name">
                            <label class="text-xs text-gray-500 block mb-1">Подтип</label>
                            <input type="text" :value="cls()?.subtype_name ?? ''" readonly
                                   class="w-full text-sm bg-gray-50 text-gray-600 border border-gray-200 rounded-lg px-3 py-2.5">
                        </div>

                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Описание</label>
                            <input type="text" name="data[описание]" x-model="values['описание']"
                                   placeholder="На отгрузку товаров"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        {{-- Атрибуты типа: контрагент, сумма, срок действия и всё, что настроено --}}
                        <template x-for="field in fields()" :key="field.field_key">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">
                                    <span x-text="field.label"></span>
                                    <span x-show="field.is_required" class="text-red-500">*</span>
                                </label>

                                <template x-if="['text', 'number', 'date'].includes(field.field_type)">
                                    <input :type="field.field_type === 'text' ? 'text' : field.field_type"
                                           :name="`data[${field.field_key}]`" x-model="values[field.field_key]"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                </template>

                                <template x-if="field.field_type === 'textarea'">
                                    <textarea :name="`data[${field.field_key}]`" x-model="values[field.field_key]" rows="3"
                                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                                </template>

                                <template x-if="field.field_type === 'select'">
                                    <select :name="`data[${field.field_key}]`" x-model="values[field.field_key]"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <option value="">—</option>
                                        <template x-for="option in field.options" :key="option">
                                            <option :value="option" x-text="option"></option>
                                        </template>
                                    </select>
                                </template>

                                <template x-if="field.field_type === 'boolean'">
                                    <select :name="`data[${field.field_key}]`" x-model="values[field.field_key]"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <option value="">—</option>
                                        <option value="Да">Да</option>
                                        <option value="Нет">Нет</option>
                                    </select>
                                </template>

                                <template x-if="field.field_type === 'reference'">
                                    <select :name="`data[${field.field_key}]`" x-model="values[field.field_key]"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <option value="">—</option>
                                        <template x-for="option in (references[field.reference_to] || [])" :key="option">
                                            <option :value="option" x-text="option"></option>
                                        </template>
                                    </select>
                                </template>
                            </div>
                        </template>

                        {{-- Название вводится руками только там, где у классификатора нет маски --}}
                        <div x-show="!template()">
                            <label class="text-xs text-gray-500 block mb-1">Название документа *</label>
                            <input type="text" x-model="title"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @error('title')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Крайний срок</label>
                            <input type="date" name="deadline_at" value="{{ old('deadline_at') }}"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        <div x-show="allowsManual()">
                            <label class="text-xs text-gray-500 block mb-1">Номер вручную</label>
                            <input type="text" name="manual_number" value="{{ old('manual_number') }}"
                                   placeholder="Оставьте пустым — выдаст система"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        <p x-show="template()" class="text-xs text-gray-400 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 leading-relaxed">
                            Из этих атрибутов будет собрано название и номер документа по шаблону классификатора.
                        </p>
                    </div>
                </aside>
            </div>

            {{-- ─────────────── Шаг 3: запуск ─────────────── --}}
            <div x-show="step === 3" class="max-w-3xl mx-auto space-y-4">
                <h2 class="text-base font-bold text-gray-900">Проверьте перед запуском</h2>

                <div class="bg-white border border-gray-200 rounded-xl p-5 flex gap-5">
                    <div class="w-24 h-28 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0">
                        <svg class="w-8 h-8 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[11px] bg-indigo-50 text-[#5B4FE8] rounded px-2 py-0.5" x-text="cls()?.type_name ?? ''"></span>
                            <span x-show="cls()?.subtype_name" class="text-[11px] text-gray-400"
                                  x-text="'Подтип: ' + (cls()?.subtype_name ?? '')"></span>
                        </div>
                        <p x-show="counterparty()" class="text-sm text-gray-600 mb-1.5" x-text="'Контрагент: ' + counterparty()"></p>
                        <p class="text-base font-semibold text-gray-900" x-text="title || '— название не собрано —'"></p>
                        <p class="text-xs text-gray-400 mt-1.5 leading-relaxed">
                            Название формируется автоматически по классификатору. Номер присваивается при регистрации документа.
                        </p>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <p class="text-sm font-semibold text-gray-900 mb-4">Маршрут</p>
                    <div class="flex items-start gap-4 overflow-x-auto">
                        @foreach($phaseMeta as $phase => $meta)
                            <template x-if="phaseParticipants('{{ $phase }}').length > 0">
                                <div class="flex items-start gap-4 shrink-0">
                                    <div>
                                        <div class="flex items-center gap-1.5 mb-2">
                                            <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}"></span>
                                            <span class="text-xs font-medium text-gray-700">{{ str_replace('Фаза ', '', $meta['label']) }}</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <template x-for="person in phaseParticipants('{{ $phase }}')" :key="person.id">
                                                <span class="w-7 h-7 rounded-full bg-indigo-50 text-[#5B4FE8] text-[10px] font-semibold flex items-center justify-center"
                                                      :title="person.name" x-text="initials(person.name)"></span>
                                            </template>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-2 text-center"
                                           x-text="phaseParticipants('{{ $phase }}').length + ' уч.'"></p>
                                    </div>
                                    <svg class="w-3.5 h-3.5 text-gray-300 mt-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </div>
                            </template>
                        @endforeach
                    </div>
                    <p x-show="routeStages().length === 0" class="text-sm text-gray-400">
                        Маршрут пуст — документ сохранится черновиком.
                    </p>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="button" @click="step = 2"
                            class="flex items-center gap-2 px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Назад
                    </button>

                    <div class="flex items-center gap-3">
                        <button type="submit" name="action" value="draft"
                                class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                            Сохранить как черновик
                        </button>
                        <button type="submit" name="action" value="launch"
                                class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                            Запустить согласование
                        </button>
                    </div>
                </div>
            </div>

            {{-- Навигация шага 2 вынесена вниз, чтобы не ломать двухколоночную раскладку --}}
            <div x-show="step === 2" class="flex items-center justify-between mt-5">
                <button type="button" @click="step = 1"
                        class="flex items-center gap-2 px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Назад
                </button>

                <div class="flex items-center gap-3">
                    <button type="submit" name="action" value="draft"
                            class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                        Сохранить черновик
                    </button>
                    <button type="button" @click="step = 3" :disabled="!canLeaveStep2()"
                            :class="canLeaveStep2() ? 'bg-[#5B4FE8] hover:bg-indigo-700' : 'bg-gray-200 cursor-not-allowed'"
                            class="flex items-center gap-2 text-white px-6 py-2.5 rounded-lg text-sm font-medium">
                        Далее: запуск
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
    function documentCreate() {
        return {
            scenarios: @json($scenarios),
            classifiers: @json($classifiers),
            references: @json($referenceOptions),
            people: @json($people),

            step: 1,
            scenarioId: @json(old('workflow_id', '')),
            subtypeId: @json(old('document_subtype_id', '')),

            // «Свой сценарий»: тип и подтип выбирает инициатор при запуске.
            composedTypeId: @json(old('document_type_id', '')),
            composedSubtypeId: @json(old('document_subtype_id', '')),

            values: @json(old('data', (object) [])),
            answers: @json(old('parameters', (object) [])),
            title: @json(old('title', '')),

            source: 'blank',
            blankId: @json(old('blank_template_id', '')),

            // Выбор исполнителя для звена на роли: role_code => id пользователя.
            rolePicks: @json(old('role_picks', (object) [])),
            adhoc: { ack: [], intake: [] },

            // Индивидуальный процесс (composed): инициатор собирает только маршрут; тип/подтип — из сценария.
            composedRoute: [],
            presets: [],
            oldRoute: @json(old('route', [])),
            phaseChoices: [
                { key: 'approval', label: 'Согласование' },
                { key: 'approve',  label: 'Утверждение' },
                { key: 'ack',      label: 'Ознакомление' },
                { key: 'intake',   label: 'Приём' },
            ],

            init() {
                this.$watch('values', () => this.syncTitle(), { deep: true });

                // После ошибки валидации возвращаем пользователя к тому, что он уже выбрал.
                if (this.scenarioId) {
                    // Карточку ищем по id: у «Своего сценария» подтип выбирает инициатор, и он
                    // может не совпадать с классификатором карточки, по которому матчит current().
                    const scenario = this.scenarios.find(s => String(s.id) === String(this.scenarioId));

                    if (scenario?.composed) {
                        this.subtypeId = scenario.subtype_id ?? '';   // матч карточки — по её классификатору
                        this.loadPresets();
                        this.composedRoute = (this.oldRoute || []).map(block => ({
                            phase: block.phase,
                            participants: (block.participants || []).map(Number),
                        }));
                        this.resetSource();
                        // Ошибка чаще всего в маршруте — возвращаем к его сборке.
                        this.step = 1;
                    } else {
                        this.syncRolePicks();
                        this.resetSource();
                        this.step = 2;
                    }
                }
            },

            current() {
                if (this.scenarioId === '' || this.scenarioId === null || this.scenarioId === undefined) return null;

                return this.scenarios.find(s => String(s.id) === String(this.scenarioId)
                    && (!this.subtypeId || Number(s.subtype_id) === Number(this.subtypeId))) ?? null;
            },

            isPicked(scenario) {
                return String(this.scenarioId) === String(scenario.id)
                    && Number(this.subtypeId || 0) === Number(scenario.subtype_id || 0);
            },

            pickScenario(scenario) {
                this.scenarioId = scenario.id;
                this.subtypeId = scenario.subtype_id ?? '';
                this.values = {};
                this.answers = {};
                this.rolePicks = {};
                this.adhoc = { ack: [], intake: [] };
                this.composedRoute = [];
                this.composedTypeId = '';
                this.composedSubtypeId = '';
                if (scenario.composed) {
                    this.loadPresets();
                }
                this.syncRolePicks();
                this.resetSource();
                this.syncTitle();
            },

            /** Классификатор документа. У фиксированного сценария тип/подтип берутся из
             *  карточки; у «Своего сценария» их выбирает инициатор — собираем из выбора. */
            cls() {
                const cur = this.current();
                if (!cur) return null;
                if (!cur.composed) return cur;

                const type = this.composedType();
                if (!type) return null;

                const sub = (type.subtypes || []).find(s => String(s.id) === String(this.composedSubtypeId)) || null;

                return {
                    composed:      true,
                    type_id:       type.id,
                    type_code:     type.code,
                    type_name:     type.name,
                    subtype_id:    sub ? sub.id : '',
                    subtype_code:  sub ? sub.code : '',
                    subtype_name:  sub ? sub.name : '',
                    name_template: (sub && sub.name_template) ? sub.name_template : type.name_template,
                    allow_manual:  sub ? sub.allow_manual : type.allow_manual,
                    fields:        [...(type.fields || []), ...(sub ? sub.fields : [])],
                };
            },

            /** Выбранный инициатором тип «Своего сценария» (для списка подтипов и валидации). */
            composedType() {
                return this.classifiers.find(t => String(t.id) === String(this.composedTypeId)) ?? null;
            },

            /** Смена типа сбрасывает подтип и введённые поля — набор полей у типов разный. */
            onComposedTypeChange() {
                this.composedSubtypeId = '';
                this.values = {};
                this.syncTitle();
            },

            phaseLabel(phase) {
                return { approval: 'Согласование', approve: 'Утверждение', ack: 'Ознакомление', intake: 'Приём' }[phase] ?? phase;
            },

            phaseDot(phase) {
                return { approval: 'bg-blue-500', approve: 'bg-emerald-500', ack: 'bg-amber-500', intake: 'bg-purple-500' }[phase] ?? 'bg-gray-400';
            },

            addPhase(phase) {
                this.composedRoute.push({ phase, participants: [] });
            },

            removePhase(index) {
                this.composedRoute.splice(index, 1);
            },

            addParticipant(index, userId) {
                const id = Number(userId);
                const block = this.composedRoute[index];
                if (!id || !block || block.participants.includes(id)) return;
                block.participants.push(id);
            },

            removeParticipant(index, userId) {
                const block = this.composedRoute[index];
                if (block) block.participants = block.participants.filter(p => p !== userId);
            },

            personById(id) {
                return this.people.find(p => p.id === Number(id)) ?? null;
            },

            personName(id) {
                return this.personById(id)?.name ?? '';
            },

            loadPresets() {
                fetch('{{ route('route-presets.index') }}', { headers: { 'Accept': 'application/json' } })
                    .then(response => response.ok ? response.json() : [])
                    .then(data => { this.presets = data; })
                    .catch(() => {});
            },

            applyPreset(id) {
                const preset = this.presets.find(p => String(p.id) === String(id));
                if (!preset) return;

                // Глубокая копия — правки маршрута не должны менять саму заготовку.
                this.composedRoute = (preset.config ?? []).map(block => ({
                    phase: block.phase,
                    participants: [...(block.participants ?? [])].map(Number),
                }));
            },

            savePreset() {
                const name = window.prompt('Название заготовки маршрута:');
                if (!name) return;

                const config = this.composedRoute
                    .filter(block => block.participants.length > 0)
                    .map(block => ({ phase: block.phase, participants: block.participants }));

                if (config.length === 0) return;

                fetch('{{ route('route-presets.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ name, config }),
                })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(preset => { this.presets.push(preset); })
                .catch(() => {});
            },

            /** Готовит выбор исполнителей для звеньев на роли: единственного кандидата ставим сразу,
             *  где кандидатов несколько — ждём выбора инициатора. Ранее сделанный выбор сохраняем. */
            syncRolePicks() {
                const picks = {};

                (this.current()?.stages ?? []).forEach(stage => {
                    if (stage.resolver !== 'group' || !stage.role_code) return;

                    if (this.rolePicks[stage.role_code]) {
                        picks[stage.role_code] = this.rolePicks[stage.role_code];
                    } else if (stage.approvers.length === 1) {
                        picks[stage.role_code] = String(stage.approvers[0].id);
                    } else {
                        picks[stage.role_code] = '';
                    }
                });

                this.rolePicks = picks;
            },

            parameters() {
                return this.current()?.parameters ?? [];
            },

            optionsOf(parameter) {
                return parameter.type === 'boolean' ? ['Да', 'Нет'] : (parameter.options ?? []);
            },

            /** Повторяет WorkflowStage::passesCondition() — то же правило, что применит движок. */
            routeStages() {
                // Индивидуальный процесс: маршрут — то, что собрал инициатор.
                if (this.current()?.composed) {
                    return this.composedRoute
                        .filter(block => block.participants.length > 0)
                        .map(block => ({
                            phase: block.phase,
                            resolver: 'user',
                            role_code: null,
                            approvers: block.participants.map(id => this.personById(id)).filter(Boolean),
                        }));
                }

                return (this.current()?.stages ?? []).filter(stage => {
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

            stagesOf(phase) {
                return this.routeStages().filter(stage => stage.phase === phase);
            },

            /** Участники фазы для сводки на шаге 3: состав сценария плюс добавленные вручную. */
            phaseParticipants(phase) {
                const fromRoute = this.stagesOf(phase).flatMap(stage => this.stageParticipants(stage));
                const added = this.adhoc[phase] ?? [];

                return [...fromRoute, ...added].filter(
                    (person, index, list) => list.findIndex(p => p.id === person.id) === index
                );
            },

            /** Кто реально пойдёт по звену: для роли — только выбранный исполнитель. */
            stageParticipants(stage) {
                if (stage.resolver === 'group' && stage.role_code) {
                    const picked = stage.approvers.find(a => a.id === Number(this.rolePicks[stage.role_code]));
                    return picked ? [picked] : [];
                }

                return stage.approvers;
            },

            addAdhoc(phase, userId) {
                const person = this.people.find(p => p.id === Number(userId));
                if (!person || this.adhoc[phase].some(p => p.id === person.id)) return;

                this.adhoc[phase].push(person);
            },

            removeAdhoc(phase, userId) {
                this.adhoc[phase] = this.adhoc[phase].filter(p => p.id !== userId);
            },

            blanks() {
                return this.current()?.blanks ?? [];
            },

            allowsFile() {
                return this.current()?.allow_file ?? true;
            },

            /** Есть бланки — заполняем бланк; единственный выбираем сразу. */
            resetSource() {
                const blanks = this.blanks();

                this.source = blanks.length > 0 ? 'blank' : 'file';
                this.blankId = blanks.length === 1 ? blanks[0].id : (this.blankId || '');

                if (this.source === 'blank' && this.blankId) {
                    this.pushBlankToEditor(this.blankId);
                }
            },

            pickBlank(blank) {
                this.source = 'blank';
                this.blankId = blank.id;
                this.pushBlankToEditor(blank.id);
            },

            /** Редактор живёт своим Alpine-компонентом — тело подменяем событием. */
            pushBlankToEditor(blankId) {
                const blank = this.blanks().find(b => b.id === Number(blankId));

                this.$nextTick(() => window.dispatchEvent(new CustomEvent('set-blank-content', {
                    detail: { html: blank?.content ?? '' },
                })));
            },

            fields() {
                return this.cls()?.fields ?? [];
            },

            template() {
                return this.cls()?.name_template ?? '';
            },

            allowsManual() {
                return this.cls()?.allow_manual ?? false;
            },

            counterparty() {
                const key = Object.keys(this.values).find(k => k.toLowerCase().includes('контрагент'));
                return key ? this.values[key] : '';
            },

            initials(name) {
                return (name || '').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
            },

            /** Ярлык классификатора, идентичный конструктору: «[код типа] подтип», иначе — имя типа. */
            classifierLabel(scenario) {
                if (scenario.subtype_name) return (scenario.type_code ? '[' + scenario.type_code + '] ' : '') + scenario.subtype_name;
                if (scenario.type_name) return scenario.type_name;
                return scenario.composed ? 'Индивидуальный процесс' : '';
            },

            stagePlural(count) {
                const tail = count % 10, hundred = count % 100;
                if (tail === 1 && hundred !== 11) return 'этап';
                if ([2, 3, 4].includes(tail) && ![12, 13, 14].includes(hundred)) return 'этапа';
                return 'этапов';
            },

            canLeaveStep1() {
                if (!this.scenarioId) return false;

                // Свой сценарий: инициатор выбирает тип (и подтип, если он есть) и собирает
                // хотя бы одну фазу с участником.
                if (this.current()?.composed) {
                    const type = this.composedType();
                    if (!type) return false;
                    if (type.subtypes.length > 0 && !this.composedSubtypeId) return false;
                    return this.composedRoute.some(block => block.participants.length > 0);
                }

                const paramsReady = this.parameters()
                    .filter(parameter => parameter.is_required)
                    .every(parameter => (this.answers[parameter.key] ?? '') !== '');

                // Для каждого звена на роли (с кандидатами) исполнитель должен быть выбран.
                const rolesReady = this.routeStages()
                    .filter(stage => stage.resolver === 'group' && stage.role_code && stage.approvers.length > 0)
                    .every(stage => (this.rolePicks[stage.role_code] ?? '') !== '');

                return paramsReady && rolesReady;
            },

            canLeaveStep2() {
                const filled = this.fields()
                    .filter(field => field.is_required)
                    .every(field => (this.values[field.field_key] ?? '') !== '');

                return filled && (this.title ?? '') !== '';
            },

            /** Название — проекция атрибутов, пока маска классификатора задана. */
            syncTitle() {
                const scenario = this.cls();
                if (!scenario || !scenario.name_template) return;

                const context = {
                    'код_типа':    scenario.type_code || '',
                    'код_подтипа': scenario.subtype_code || '',
                    'тип':         scenario.type_name || '',
                    'подтип':      scenario.subtype_name || '',
                    'описание':    this.values['описание'] || '',
                    'номер':       '___',
                    'дата':        '__.__.____',
                    'отдел':       @json($user->department?->name ?? ''),
                    'инициатор':   @json($user->name),
                };

                this.fields().forEach(field => {
                    context[field.field_key.trim().toLowerCase()] = this.values[field.field_key] || '';
                });

                this.title = renderMask(scenario.name_template, context);
            },
        };
    }
    </script>
</x-app-layout>
