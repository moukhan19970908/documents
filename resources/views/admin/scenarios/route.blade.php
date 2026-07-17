@php
    $stagesData = old('stages', $scenario->stages->map(fn ($s) => [
        'name'                 => $s->name,
        'phase'                => $s->phase ?? 'approval',
        'resolver'             => $s->resolver ?? 'user',
        'approver_ids'         => $s->approvers->pluck('approver_id')->all(),
        'group_department_ids' => $s->group_department_ids ?? array_filter([$s->group_department_id]),
        'group_role'           => $s->group_role,
        'policy'               => $s->policy ?? 'all',
        'sla_days'             => $s->sla_days,
        'is_blocking'          => (bool) $s->is_blocking,
        'on_reject'            => $s->on_reject ?? 'return_initiator',
        'condition_key'        => $s->condition_key,
        'condition_operator'   => $s->condition_operator ?? '=',
        'condition_value'      => $s->condition_value,
        'branches'             => $s->branches->map(fn ($b) => [
            'name'               => $b->name,
            'condition_key'      => $b->condition_key,
            'condition_operator' => $b->condition_operator ?? '=',
            'condition_value'    => $b->condition_value,
            'approver_ids'       => $b->approver_ids ?? [],
            'department_ids'     => $b->department_ids ?? [],
            'policy'             => $b->policy ?? 'all',
        ])->values()->all(),
    ])->values()->all());

    $parametersForRoute = $scenario->parameters->map(fn ($p) => [
        'key'     => $p->key,
        'label'   => $p->label,
        'options' => $p->options ?? [],
        'type'    => $p->type,
    ])->values();

    $usersForRoute = $users->map(fn ($u) => [
        'id'       => $u->id,
        'name'     => $u->name,
        'position' => $u->position ?: ($u->department?->name ?? ''),
    ])->values();
    $departmentsForRoute = $departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values();
    // «Отделы» в резолвере = направления (корневые департаменты).
    $directionsForRoute = ($directions ?? collect())->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values();
@endphp

<div x-data="routeBuilder()" x-show="step === 'route'">
    <form action="{{ route('admin.scenarios.route', $scenario) }}" method="POST">
        @csrf @method('PUT')

        <div class="flex gap-5">
            {{-- Палитра блоков --}}
            @php
                // Доступность блока зависит от типа процесса: `process` = null — блок общий.
                // `enabled` = false — блок пока только в вёрстке, движок его не умеет.
                $paletteBlocks = [
                    ['label' => 'Звено согласования',  'icon' => 'approve',   'color' => 'indigo',  'phase' => 'approval', 'enabled' => true],
                    ['label' => 'Звено утверждения',   'icon' => 'confirm',   'color' => 'emerald', 'phase' => 'approve',  'enabled' => true],
                    ['label' => 'Группа заключений',   'icon' => 'opinions',  'color' => 'teal',    'phase' => 'opinion',  'enabled' => true],
                    ['label' => 'Звено ознакомления',  'icon' => 'ack',       'color' => 'amber',   'phase' => 'ack',      'enabled' => true],
                    ['label' => 'Звено приёма',        'icon' => 'intake',    'color' => 'violet',  'phase' => 'intake',   'enabled' => true],
                    ['label' => 'Параллельная группа', 'icon' => 'parallel',  'color' => 'slate',   'phase' => 'parallel', 'enabled' => true],
                    ['label' => 'Условие / развилка',  'icon' => 'condition', 'color' => 'purple',  'phase' => 'branch',   'enabled' => true],
                    ['label' => 'Резолвер аудитории',  'icon' => 'audience',  'color' => 'green',   'process' => 'orders',      'for' => 'Приказов',   'enabled' => false],
                    ['label' => 'Узел сборки в реестр','icon' => 'registry',  'color' => 'orange',  'process' => 'requests',    'for' => 'Заявок',     'enabled' => false],
                    ['label' => 'Порождаемые задания', 'icon' => 'tasks',     'color' => 'pink',    'process' => 'assignments', 'for' => 'Поручений',  'enabled' => false],
                    ['label' => 'Этап-чек-лист',       'icon' => 'checklist', 'color' => 'sky',     'process' => 'procedures',  'for' => 'Процедур',   'enabled' => false],
                    ['label' => 'Правила дерева',      'icon' => 'tree',      'color' => 'lime',    'process' => 'assignments', 'for' => 'Поручений',  'enabled' => false],
                ];

                $blockColors = [
                    'indigo'  => 'bg-indigo-50 text-indigo-500',
                    'emerald' => 'bg-emerald-50 text-emerald-500',
                    'teal'    => 'bg-teal-50 text-teal-500',
                    'amber'   => 'bg-amber-50 text-amber-500',
                    'violet'  => 'bg-violet-50 text-violet-500',
                    'slate'   => 'bg-slate-100 text-slate-500',
                    'purple'  => 'bg-purple-50 text-purple-500',
                    'green'   => 'bg-green-50 text-green-600',
                    'orange'  => 'bg-orange-50 text-orange-500',
                    'pink'    => 'bg-pink-50 text-pink-500',
                    'sky'     => 'bg-sky-50 text-sky-500',
                    'lime'    => 'bg-lime-50 text-lime-600',
                ];
            @endphp

            <div class="w-56 shrink-0">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Палитра блоков</p>
                <p class="text-[11px] text-gray-400 mb-3">Доступность зависит от типа процесса</p>

                <div class="space-y-2">
                    @foreach($paletteBlocks as $block)
                        @php
                            $process = $block['process'] ?? null;
                            // Блок чужого типа процесса показываем, но не даём положить в маршрут.
                            $wrongProcess = $process && $process !== $scenario->process_type;
                            $available = ($block['enabled'] ?? false) && !$wrongProcess;

                            $title = $wrongProcess
                                ? 'Доступен только для процессов «' . \App\Models\Workflow::PROCESS_TYPES[$process] . '»'
                                : ($block['hint'] ?? ($available ? '' : 'Появится на следующем этапе'));
                            $subtitle = $block['for'] ?? null;
                        @endphp

                        <button type="button"
                                @if($available) @click="addStage('{{ $block['phase'] }}')" @else disabled @endif
                                title="{{ $title }}"
                                class="w-full flex items-center gap-2.5 bg-white border rounded-xl px-3 py-2.5 text-left transition-colors
                                       {{ $available
                                          ? 'border-gray-200 hover:border-[#5B4FE8] hover:bg-[#5B4FE8]/5 cursor-pointer'
                                          : 'border-gray-100 opacity-50 cursor-not-allowed' }}">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {{ $blockColors[$block['color']] }}">
                                @include('admin.partials.block-icon', ['icon' => $block['icon']])
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm text-gray-700 leading-tight">{{ $block['label'] }}</span>
                                @if($subtitle)
                                    <span class="block text-[11px] text-gray-400 leading-tight mt-0.5">для {{ $subtitle }}</span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Канвас --}}
            @php
                $phaseStyles = [
                    'approval' => ['bg-[#5B4FE8]/5 border-[#5B4FE8]/20', 'text-[#5B4FE8]'],
                    'approve'  => ['bg-emerald-50 border-emerald-100', 'text-emerald-600'],
                    'opinion'  => ['bg-teal-50 border-teal-100', 'text-teal-600'],
                    'ack'      => ['bg-amber-50 border-amber-100', 'text-amber-600'],
                    'intake'   => ['bg-violet-50 border-violet-100', 'text-violet-600'],
                    'branch'   => ['bg-purple-50 border-purple-100', 'text-purple-600'],
                ];
            @endphp

            <div class="flex-1 bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-5 min-h-[420px]">
                @foreach(\App\Models\WorkflowStage::PHASES as $phase => $phaseLabel)
                    <div x-show="stagesOf('{{ $phase }}').length > 0"
                         class="border rounded-xl p-4 {{ $phaseStyles[$phase][0] }}">
                        <p class="text-[10px] font-semibold uppercase tracking-widest mb-3 {{ $phaseStyles[$phase][1] }}">{{ $phaseLabel }}</p>

                        <template x-for="stage in stagesOf('{{ $phase }}')" :key="stage.uid">
                            <div class="mb-2">
                                <div @click="selected = stage.uid"
                                     :class="selected === stage.uid ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200'"
                                     class="bg-white border rounded-xl px-4 py-3 cursor-pointer">
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 h-5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-semibold flex items-center justify-center"
                                              x-text="stages.indexOf(stage) + 1"></span>
                                        <span class="text-sm font-medium text-gray-800" x-text="stage.name || 'Без названия'"></span>
                                        <span x-show="stage.condition_key" class="text-amber-500" title="Условное звено">⚡</span>
                                        <button type="button" @click.stop="removeStage(stage)" class="ml-auto text-gray-300 hover:text-red-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1 ml-7" x-text="summary(stage)"></p>
                                    <p x-show="stage.condition_key" class="text-xs text-amber-600 mt-0.5 ml-7"
                                       x-text="'включается, если ' + stage.condition_key + ' ' + stage.condition_operator + ' ' + (stage.condition_value || '—')"></p>
                                </div>
                                <div class="text-center text-gray-300 text-xs">↓</div>
                            </div>
                        </template>
                    </div>
                @endforeach

                <p x-show="stages.length === 0" class="text-sm text-gray-400 text-center py-16">
                    Маршрут пуст — добавьте звено из палитры слева.
                </p>
            </div>

            {{-- Свойства звена --}}
            <div class="w-72 shrink-0">
                <p class="text-sm font-semibold text-gray-800 mb-3">Свойства звена</p>

                <template x-if="!current()">
                    <p class="text-xs text-gray-400">Выберите звено на схеме.</p>
                </template>

                <template x-if="current()">
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Название звена</label>
                            <input type="text" x-model="current().name"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Тип действия</label>
                            <select x-model="current().phase" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                @foreach(\App\Models\WorkflowStage::PHASES as $phase => $phaseLabel)
                                    <option value="{{ $phase }}">{{ str_replace('Фаза ', '', $phaseLabel) }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Какие кнопки увидит участник — задаётся видом звена --}}
                        <div x-show="current().phase !== 'branch'" class="bg-gray-50 border border-gray-100 rounded-lg px-3 py-2">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-1.5">Кнопки участника</p>
                            <div class="flex flex-wrap gap-1">
                                <template x-for="action in actionsOf(current().phase)" :key="action">
                                    <span class="bg-white border border-gray-200 text-gray-600 text-xs px-2 py-0.5 rounded" x-text="actionLabels[action]"></span>
                                </template>
                            </div>
                            <p x-show="current().phase === 'intake'" class="text-xs text-gray-400 mt-1.5">«Исполнено» появится после принятия к исполнению.</p>
                            <p x-show="current().phase === 'opinion'" class="text-xs text-gray-400 mt-1.5">Решение фиксируется, но маршрут не останавливает.</p>
                        </div>

                        {{-- ===== Развилка ===== --}}
                        <template x-if="current().phase === 'branch'">
                            <div class="space-y-3">
                                <p class="text-xs text-gray-400">Сработает первая ветка, чьё условие истинно. У каждой ветки — свой состав согласующих.</p>

                                <template x-for="(branch, bIndex) in current().branches" :key="bIndex">
                                    <div class="border border-gray-200 rounded-lg p-3 space-y-2">
                                        <div class="flex items-center gap-2">
                                            <input type="text" x-model="branch.name" placeholder="Название ветки"
                                                   class="flex-1 text-sm border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                            <button type="button" @click="current().branches.splice(bIndex, 1)" class="text-gray-300 hover:text-red-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>

                                        <div class="flex gap-1.5">
                                            <select x-model="branch.condition_key" class="flex-1 text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                                <option value="">без условия (иначе)</option>
                                                <template x-for="p in parameters" :key="p.key">
                                                    <option :value="p.key" x-text="p.label"></option>
                                                </template>
                                            </select>
                                            <select x-model="branch.condition_operator" class="w-28 text-xs border border-gray-200 rounded px-1 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                                @foreach(\App\Models\WorkflowStage::OPERATORS as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <template x-if="optionsOf(branch.condition_key).length > 0">
                                            <select x-model="branch.condition_value" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                                <template x-for="option in optionsOf(branch.condition_key)" :key="option">
                                                    <option :value="option" x-text="option"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="optionsOf(branch.condition_key).length === 0">
                                            <input type="text" x-model="branch.condition_value" placeholder="значение"
                                                   class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                        </template>

                                        <div>
                                            <label class="text-[11px] text-gray-500 block mb-1">Согласующие ветки</label>
                                            <select multiple size="4" x-model.number="branch.approver_ids"
                                                    class="w-full text-xs border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                                <template x-for="u in allUsers" :key="u.id">
                                                    <option :value="u.id" x-text="u.name"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <select x-model="branch.policy" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                            <option value="all">Согласуют все</option>
                                            <option value="any">Достаточно одного</option>
                                        </select>
                                    </div>
                                </template>

                                <button type="button" @click="addBranch()"
                                        class="w-full text-xs font-medium text-[#5B4FE8] border border-dashed border-[#5B4FE8]/40 rounded-lg py-2 hover:bg-[#5B4FE8]/5">
                                    + Добавить ветку
                                </button>
                            </div>
                        </template>

                        {{-- ===== Обычное звено ===== --}}
                        <template x-if="current().phase !== 'branch'">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1.5">Резолвер исполнителя</label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer py-0.5">
                                        <input type="radio" value="user" x-model="current().resolver" class="border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                        Конкретные сотрудники
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer py-0.5">
                                        <input type="radio" value="group" x-model="current().resolver" class="border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                        Роль / группа
                                    </label>
                                </div>

                                <div class="max-w-[15rem]">
                                    <label class="text-xs text-gray-500 block mb-1">Сотрудники</label>
                                    <input type="text" x-model="userSearch" placeholder="Поиск по имени…"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 mb-1.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-50" style="max-height: 7.5rem; overflow-y: auto;">
                                        <template x-for="u in allUsers.filter(u => !userSearch || u.name.toLowerCase().includes(userSearch.toLowerCase()))" :key="u.id">
                                            <label class="flex items-center gap-2 px-2.5 py-1.5 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" :value="u.id" x-model.number="current().approver_ids"
                                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                                <span class="text-sm text-gray-800" x-text="u.name"></span>
                                                <span class="ml-auto text-xs text-gray-400 truncate" x-text="u.position"></span>
                                            </label>
                                        </template>
                                        <p x-show="allUsers.filter(u => !userSearch || u.name.toLowerCase().includes(userSearch.toLowerCase())).length === 0"
                                           class="text-xs text-gray-400 px-2.5 py-2">Ничего не найдено</p>
                                    </div>
                                    <div class="flex flex-wrap gap-1 mt-1.5" x-show="current().approver_ids.length > 0">
                                        <template x-for="id in current().approver_ids" :key="id">
                                            <span class="inline-flex items-center gap-1 bg-[#5B4FE8]/8 text-[#5B4FE8] border border-[#5B4FE8]/20 rounded-full px-2 py-0.5 text-xs">
                                                <span x-text="(allUsers.find(u => u.id === id) || {}).name"></span>
                                                <button type="button" @click="current().approver_ids = current().approver_ids.filter(x => x !== id)" class="hover:text-red-500">×</button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Направления</label>
                                    <select multiple size="4" x-model.number="current().group_department_ids"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <template x-for="d in allDirections" :key="d.id">
                                            <option :value="d.id" x-text="d.name"></option>
                                        </template>
                                    </select>
                                    <p class="text-xs text-gray-400 mt-1">Направление разворачивается во всех сотрудников его отделов при публикации. Можно совмещать с сотрудниками — это и есть параллельная группа.</p>
                                </div>

                                <div x-show="current().resolver === 'group'">
                                    <label class="text-xs text-gray-500 block mb-1">Роль</label>
                                    <select x-model="current().group_role"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <option value="">— любая —</option>
                                        @foreach($roles as $r)
                                            <option value="{{ $r->code }}">{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Политика</label>
                                    <select x-model="current().policy" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <option value="any">Достаточно одного</option>
                                        <option value="all">Решают все (параллельно)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Срок (SLA)</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" min="1" max="365" x-model.number="current().sla_days"
                                               class="w-16 text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <span class="text-xs text-gray-500">рабочих дней</span>
                                    </div>
                                </div>

                                {{-- Пропустить звено вправе только совещательное: у согласования,
                                     утверждения и приёма маршрут ждёт решения всегда. --}}
                                <div x-show="['ack', 'opinion'].includes(current().phase)" class="space-y-2">
                                    <label class="flex items-center justify-between text-sm text-gray-700 cursor-pointer">
                                        Блокирующее звено
                                        <input type="checkbox" x-model="current().is_blocking" class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    </label>
                                    <p x-show="!current().is_blocking" class="text-xs text-gray-400">Не держит маршрут: участники получают задачу, документ идёт дальше.</p>
                                </div>

                                <div class="border-t border-gray-100 pt-3">
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mb-2">
                                        <input type="checkbox" :checked="!!current().condition_key"
                                               @change="toggleCondition($event.target.checked)"
                                               class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                        Условное звено
                                    </label>

                                    <div x-show="current().condition_key" class="space-y-2">
                                        <template x-if="parameters.length === 0">
                                            <p class="text-xs text-amber-600">Сначала добавьте параметр на шаге 3 — условию не на что ссылаться.</p>
                                        </template>

                                        <div class="flex gap-2">
                                            <select x-model="current().condition_key" class="flex-1 text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                                <template x-for="p in parameters" :key="p.key">
                                                    <option :value="p.key" x-text="p.label"></option>
                                                </template>
                                            </select>
                                            <select x-model="current().condition_operator" class="w-28 text-sm border border-gray-200 rounded-lg px-1 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                                @foreach(\App\Models\WorkflowStage::OPERATORS as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <template x-if="conditionOptions().length > 0">
                                            <select x-model="current().condition_value" class="w-full text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                                <template x-for="option in conditionOptions()" :key="option">
                                                    <option :value="option" x-text="option"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="conditionOptions().length === 0">
                                            <input type="text" x-model="current().condition_value" placeholder="значение"
                                                   class="w-full text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        </template>
                                    </div>
                                </div>

                                {{-- Отклонение решает судьбу документа только на согласовании и утверждении --}}
                                <div x-show="['approval', 'approve'].includes(current().phase)">
                                    <label class="text-xs text-gray-500 block mb-1">При отклонении</label>
                                    <select x-model="current().on_reject" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        @foreach(\App\Models\WorkflowStage::ON_REJECT as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Скрытые поля: источник истины — массив stages --}}
        <template x-for="(stage, index) in stages" :key="stage.uid">
            <div class="hidden">
                <input type="hidden" :name="`stages[${index}][name]`" :value="stage.name">
                <input type="hidden" :name="`stages[${index}][phase]`" :value="stage.phase">
                <input type="hidden" :name="`stages[${index}][resolver]`" :value="stage.resolver">
                <input type="hidden" :name="`stages[${index}][group_role]`" :value="stage.group_role ?? ''">
                <input type="hidden" :name="`stages[${index}][policy]`" :value="stage.policy">
                <input type="hidden" :name="`stages[${index}][sla_days]`" :value="stage.sla_days ?? ''">
                <input type="hidden" :name="`stages[${index}][is_blocking]`" :value="stage.is_blocking ? 1 : 0">
                <input type="hidden" :name="`stages[${index}][on_reject]`" :value="stage.on_reject">
                <input type="hidden" :name="`stages[${index}][condition_key]`" :value="stage.condition_key ?? ''">
                <input type="hidden" :name="`stages[${index}][condition_operator]`" :value="stage.condition_operator ?? '='">
                <input type="hidden" :name="`stages[${index}][condition_value]`" :value="stage.condition_value ?? ''">

                <template x-for="userId in stage.approver_ids" :key="userId">
                    <input type="hidden" :name="`stages[${index}][approver_ids][]`" :value="userId">
                </template>
                <template x-for="deptId in stage.group_department_ids" :key="deptId">
                    <input type="hidden" :name="`stages[${index}][group_department_ids][]`" :value="deptId">
                </template>

                <template x-for="(branch, bIndex) in stage.branches" :key="bIndex">
                    <div>
                        <input type="hidden" :name="`stages[${index}][branches][${bIndex}][name]`" :value="branch.name ?? ''">
                        <input type="hidden" :name="`stages[${index}][branches][${bIndex}][condition_key]`" :value="branch.condition_key ?? ''">
                        <input type="hidden" :name="`stages[${index}][branches][${bIndex}][condition_operator]`" :value="branch.condition_operator ?? '='">
                        <input type="hidden" :name="`stages[${index}][branches][${bIndex}][condition_value]`" :value="branch.condition_value ?? ''">
                        <input type="hidden" :name="`stages[${index}][branches][${bIndex}][policy]`" :value="branch.policy ?? 'all'">
                        <template x-for="userId in branch.approver_ids" :key="userId">
                            <input type="hidden" :name="`stages[${index}][branches][${bIndex}][approver_ids][]`" :value="userId">
                        </template>
                        <template x-for="deptId in branch.department_ids" :key="deptId">
                            <input type="hidden" :name="`stages[${index}][branches][${bIndex}][department_ids][]`" :value="deptId">
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <div class="flex items-center justify-between mt-6">
            <button type="button" @click="step = 'parameters'" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">← Назад</button>
            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сохранить маршрут</button>
                <button type="button" @click="step = 'rights'" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Далее: права →</button>
            </div>
        </div>
    </form>

    {{-- Публикация --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mt-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-800">Публикация</p>
                <p class="text-xs text-gray-400 mt-1">
                    Статус:
                    <span class="font-medium {{ $scenario->status === 'published' ? 'text-emerald-600' : 'text-amber-600' }}">
                        {{ $scenario->status === 'published' ? 'Опубликован' : 'Черновик' }}
                    </span>
                    @if($versions->isNotEmpty())
                        · последняя версия: <span class="font-mono">v{{ $versions->first()->version_label }}</span>
                        от {{ $versions->first()->published_at?->format('d.m.Y') }}
                    @endif
                </p>
            </div>
            <form action="{{ route('admin.scenarios.publish', $scenario) }}" method="POST">
                @csrf
                <button class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Опубликовать сценарий</button>
            </form>
        </div>

        <div class="flex items-start gap-2 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2.5 mt-4 text-xs text-amber-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0l-7.1 12.25A2 2 0 004.98 19z"/></svg>
            Изменения не затронут уже запущенные процессы — они продолжатся по версии, с которой стартовали.
        </div>

        @error('stages')<p class="text-xs text-red-500 mt-2">{{ $message }}</p>@enderror
    </div>
</div>

<script>
function routeBuilder() {
    return {
        stages: @json($stagesData).map((s, i) => ({ ...s, uid: 'st' + i })),
        parameters: @json($parametersForRoute),
        allUsers: @json($usersForRoute),
        allDepartments: @json($departmentsForRoute),
        allDirections: @json($directionsForRoute),
        userSearch: '',
        actionsByKind: @json(\App\Models\WorkflowStage::ACTIONS),
        actionLabels: @json(\App\Models\WorkflowStage::ACTION_LABELS),
        selected: null,

        actionsOf(phase) {
            return this.actionsByKind[phase] || this.actionsByKind['approval'];
        },

        optionsOf(key) {
            const parameter = this.parameters.find(p => p.key === key);
            return parameter ? (parameter.options || []) : [];
        },

        addBranch() {
            const stage = this.current();
            if (!stage) return;

            stage.branches.push({
                name: '', condition_key: '', condition_operator: '=', condition_value: '',
                approver_ids: [], department_ids: [], policy: 'all',
            });
        },

        current() {
            return this.stages.find(s => s.uid === this.selected) || null;
        },

        stagesOf(phase) {
            return this.stages.filter(s => s.phase === phase);
        },

        /**
         * «Параллельная группа» — не отдельный вид звена, а звено согласования, где решают все:
         * несколько сотрудников и/или отделов сразу. Развилка — контейнер веток.
         */
        addStage(kind) {
            const isParallel = kind === 'parallel';
            const phase = isParallel ? 'approval' : kind;

            const stage = {
                uid: 'st' + Date.now(),
                name: isParallel ? 'Параллельная группа' : '',
                phase,
                resolver: isParallel ? 'group' : 'user',
                approver_ids: [],
                group_department_ids: [],
                group_role: '',
                policy: 'all',
                sla_days: 2,
                is_blocking: true,
                on_reject: 'return_initiator',
                condition_key: '', condition_operator: '=', condition_value: '',
                branches: kind === 'branch'
                    ? [{ name: 'Ветка 1', condition_key: '', condition_operator: '=', condition_value: '',
                         approver_ids: [], department_ids: [], policy: 'all' }]
                    : [],
            };

            this.stages.push(stage);
            this.selected = stage.uid;
        },

        removeStage(stage) {
            this.stages = this.stages.filter(s => s.uid !== stage.uid);
            if (this.selected === stage.uid) this.selected = null;
        },

        /** A condition needs a parameter to point at — without one it cannot exist. */
        toggleCondition(enabled) {
            const stage = this.current();
            if (!stage) return;

            if (!enabled) {
                stage.condition_key = '';
                stage.condition_value = '';
                return;
            }

            stage.condition_key = this.parameters.length ? this.parameters[0].key : '';
            stage.condition_operator = '=';
            stage.condition_value = this.conditionOptions()[0] ?? '';
        },

        conditionOptions() {
            const stage = this.current();
            if (!stage) return [];
            const parameter = this.parameters.find(p => p.key === stage.condition_key);
            return parameter ? (parameter.options || []) : [];
        },

        summary(stage) {
            if (stage.phase === 'branch') {
                return stage.branches.length + ' веток · сработает первая подходящая';
            }

            const people = stage.approver_ids
                .map(id => (this.allUsers.find(u => u.id === id) || {}).name)
                .filter(Boolean);

            const departments = (stage.group_department_ids || [])
                .map(id => (this.allDepartments.find(d => d.id === id) || {}).name)
                .filter(Boolean);

            const who = [...people, ...departments].join(', ');
            const policy = stage.policy === 'any' ? 'достаточно одного' : 'решают все';
            const sla = stage.sla_days ? ` · ${stage.sla_days} р.д.` : '';

            return (who || 'исполнитель не назначен') + ' · ' + policy + sla + (stage.is_blocking ? '' : ' · не блокирует');
        },
    };
}
</script>
