@php
    use App\Models\WorkflowNode;
    use App\Models\WorkflowStage;

    // Плоский список узлов разворачивается в дерево: конструктор рисует именно его.
    $nodesByChain = $scenario->nodes->groupBy(fn ($n) => $n->parent_id . '|' . $n->branch);

    $buildTree = function ($parentId, $branch) use (&$buildTree, $nodesByChain) {
        return ($nodesByChain[$parentId . '|' . $branch] ?? collect())
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn ($n) => [
                'type'   => $n->type,
                'name'   => $n->name,
                'config' => $n->config ?? [],
                'yes'    => $buildTree($n->id, 'yes'),
                'no'     => $buildTree($n->id, 'no'),
            ])->values()->all();
    };

    // После ошибки валидации схема восстанавливается из того, что уже было отправлено.
    $graphTree = old('graph') ? (json_decode(old('graph'), true) ?: []) : $buildTree(null, 'main');

    $graphData = [
        'nodes'      => $graphTree,
        'types'      => WorkflowNode::TYPES,
        'statuses'   => WorkflowNode::STATUSES,
        'results'    => WorkflowNode::RESULTS,
        'recipients' => WorkflowNode::RECIPIENTS,
        'operators'  => WorkflowStage::OPERATORS,
        'departmentOperators' => WorkflowNode::DEPARTMENT_OPERATORS,
        'parameters' => $scenario->parameters->map(fn ($p) => [
            'key' => $p->key, 'label' => $p->label, 'options' => $p->options ?? [],
        ])->values(),
        'users'      => $users->map(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'position' => $u->position ?: ($u->department?->name ?? ''),
        ])->values(),
        'directions' => ($directions ?? collect())->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
        // Для условия по отделу инициатора — вся оргструктура, а не только направления.
        'departments' => $departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
        'roles'      => $roles->map(fn ($r) => ['code' => $r->code, 'name' => $r->name])->values(),
    ];

    // Иконка и цвет узла в палитре и на схеме.
    $nodeIcons = [
        'approval'  => ['icon' => 'approve',   'color' => 'indigo'],
        'approve'   => ['icon' => 'confirm',   'color' => 'emerald'],
        'opinion'   => ['icon' => 'opinions',  'color' => 'teal'],
        'ack'       => ['icon' => 'ack',       'color' => 'amber'],
        'intake'    => ['icon' => 'intake',    'color' => 'violet'],
        'status'    => ['icon' => 'checklist', 'color' => 'sky'],
        'notify'    => ['icon' => 'mail',      'color' => 'blue'],
        'condition' => ['icon' => 'condition', 'color' => 'purple'],
        'end'       => ['icon' => 'finish',    'color' => 'slate'],
    ];

    $nodeColors = [
        'indigo'  => 'bg-indigo-50 text-indigo-500',
        'emerald' => 'bg-emerald-50 text-emerald-500',
        'teal'    => 'bg-teal-50 text-teal-500',
        'amber'   => 'bg-amber-50 text-amber-500',
        'violet'  => 'bg-violet-50 text-violet-500',
        'sky'     => 'bg-sky-50 text-sky-500',
        'blue'    => 'bg-blue-50 text-blue-500',
        'purple'  => 'bg-purple-50 text-purple-500',
        'slate'   => 'bg-slate-100 text-slate-500',
    ];
@endphp

<div x-data="scenarioGraph(@js($graphData))" x-show="step === 'route'">

    <div class="flex gap-4 items-start">

        {{-- ── Схема процесса ─────────────────────────────────────────────── --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-800">Схема процесса</p>
                <p class="text-xs text-gray-400">
                    <template x-if="insertAt">
                        <span class="text-[#5B4FE8] font-medium">Выберите блок справа — он встанет в отмеченную точку</span>
                    </template>
                    <template x-if="!insertAt">
                        <span>Нажмите «+» на схеме, затем блок справа</span>
                    </template>
                </p>
            </div>

            <div class="bg-[#F7F8FB] border border-gray-200 rounded-xl overflow-auto" style="max-height: 68vh">
                <div class="relative" :style="`width:${layout.width}px; height:${layout.height}px; min-width:100%`">

                    {{-- Соединители --}}
                    <svg class="absolute top-0 left-0 pointer-events-none" :width="layout.width" :height="layout.height">
                        <path :d="layout.linePath" fill="none" stroke="#c3cad8" stroke-width="1.5"></path>
                        <path :d="layout.arrowPath" fill="#c3cad8"></path>
                    </svg>

                    {{-- Начало --}}
                    <div class="absolute flex items-center justify-center bg-emerald-50 border border-emerald-300 rounded-lg text-sm font-medium text-emerald-700"
                         :style="`left:${layout.startX}px; top:24px; width:140px; height:40px`">
                        Начало
                    </div>

                    {{-- Точки вставки --}}
                    <template x-for="(plus, i) in layout.pluses" :key="'p' + i">
                        <button type="button"
                                @click="aim({ parent: plus.parent, branch: plus.branch, index: plus.index })"
                                :class="sameTarget({ parent: plus.parent, branch: plus.branch, index: plus.index })
                                        ? 'bg-[#5B4FE8] text-white border-[#5B4FE8] scale-110'
                                        : 'bg-white text-gray-400 border-gray-300 hover:border-[#5B4FE8] hover:text-[#5B4FE8]'"
                                class="absolute w-5 h-5 rounded-full border flex items-center justify-center text-xs leading-none transition-all"
                                :style="`left:${plus.x - 10}px; top:${plus.y - 10}px`"
                                title="Добавить блок сюда">+</button>
                    </template>

                    {{-- Узлы --}}
                    <template x-for="card in layout.cards" :key="card.uid">
                        <div class="absolute bg-white border rounded-lg shadow-sm select-none"
                             :class="editing && editing.uid === card.uid ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200'"
                             :style="`left:${card.x}px; top:${card.y}px; width:248px; height:56px`">

                            {{-- Заголовок с кнопками, как в конструкторе бизнес-процессов --}}
                            <div class="flex items-center gap-1 px-2 h-[18px] bg-[#FCE8C3]/70 border-b border-amber-200/60 rounded-t-lg">
                                <span class="text-[9px] text-amber-800/70 truncate" x-text="types[card.node.type].label"></span>
                                <button type="button" @click="openNode(card.uid)" class="ml-auto text-amber-800/60 hover:text-amber-900" title="Настроить">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3a1 1 0 011.4 0l.9.9a7 7 0 011.8.7l1.2-.3a1 1 0 011.2.5l.7 1.2a1 1 0 01-.2 1.3l-.9.8a7 7 0 010 1.9l.9.8a1 1 0 01.2 1.3l-.7 1.2a1 1 0 01-1.2.5l-1.2-.3a7 7 0 01-1.8.7l-.9.9a1 1 0 01-1.4 0l-.9-.9a7 7 0 01-1.8-.7l-1.2.3a1 1 0 01-1.2-.5l-.7-1.2a1 1 0 01.2-1.3l.9-.8a7 7 0 010-1.9l-.9-.8a1 1 0 01-.2-1.3l.7-1.2a1 1 0 011.2-.5l1.2.3a7 7 0 011.8-.7l.9-.9z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                </button>
                                <button type="button" @click="removeNode(card.uid)" class="text-amber-800/60 hover:text-red-600" title="Удалить">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="flex items-center gap-2 px-2.5 py-1.5 cursor-pointer" @click="openNode(card.uid)">
                                {{-- Метки выходов ветвящегося узла --}}
                                <template x-if="card.branching">
                                    <span class="absolute -bottom-4 left-1 text-[10px] font-semibold text-emerald-600">Да</span>
                                </template>
                                <template x-if="card.branching">
                                    <span class="absolute -bottom-4 right-1 text-[10px] font-semibold text-red-500">Нет</span>
                                </template>

                                <span class="w-6 h-6 rounded shrink-0 flex items-center justify-center"
                                      :class="{
                                        @foreach($nodeIcons as $type => $meta)
                                        '{{ $nodeColors[$meta['color']] }}': card.node.type === '{{ $type }}',
                                        @endforeach
                                      }">
                                    @foreach($nodeIcons as $type => $meta)
                                        <template x-if="card.node.type === '{{ $type }}'">
                                            <span>@include('admin.partials.block-icon', ['icon' => $meta['icon']])</span>
                                        </template>
                                    @endforeach
                                </span>

                                <span class="min-w-0">
                                    <span class="block text-[13px] leading-tight text-gray-800 truncate" x-text="card.node.name"></span>
                                    <span class="block text-[11px] leading-tight text-gray-400 truncate" x-text="summary(card.node)"></span>
                                </span>
                            </div>
                        </div>
                    </template>

                    <template x-if="nodes.length === 0">
                        <p class="absolute left-0 right-0 text-xs text-gray-400 text-center" :style="`top:${layout.height - 40}px`">
                            Маршрут пуст — нажмите «+» и выберите блок справа.
                        </p>
                    </template>
                </div>
            </div>
        </div>

        {{-- ── Палитра блоков ─────────────────────────────────────────────── --}}
        <div class="w-64 shrink-0 space-y-4">
            @foreach(WorkflowNode::GROUPS as $group => $groupLabel)
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <p class="px-3 py-2 bg-gray-50 border-b border-gray-100 text-[11px] font-semibold text-gray-600 uppercase tracking-wide">
                        {{ $groupLabel }}
                    </p>
                    <div class="divide-y divide-gray-50">
                        @foreach(WorkflowNode::TYPES as $type => $meta)
                            @continue($meta['group'] !== $group)
                            <button type="button" @click="addNode('{{ $type }}')"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-left hover:bg-[#5B4FE8]/5">
                                <span class="w-6 h-6 rounded flex items-center justify-center shrink-0 {{ $nodeColors[$nodeIcons[$type]['color']] }}">
                                    @include('admin.partials.block-icon', ['icon' => $nodeIcons[$type]['icon']])
                                </span>
                                <span class="text-[13px] text-gray-700 leading-tight">{{ $meta['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <p class="text-[11px] text-gray-400 leading-relaxed">
                У согласования, утверждения и условия два выхода: «Да» и «Нет».
                Когда ветка заканчивается, процесс продолжается тем, что нарисовано ниже развилки.
            </p>
        </div>
    </div>

    {{-- ── Настройки узла ─────────────────────────────────────────────────── --}}
    <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="editing = null"></div>

        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-y-auto" x-show="editing">
            <template x-if="editing">
                <div>
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 sticky top-0 bg-white rounded-t-xl">
                        <p class="text-sm font-semibold text-gray-800" x-text="types[editing.type].label"></p>
                        <button type="button" @click="editing = null" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-5 space-y-4">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Название блока</label>
                            <input type="text" x-model="editing.name"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        {{-- Задания --}}
                        <template x-if="types[editing.type].task">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1.5">Кто исполняет</label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="radio" value="user" x-model="editing.config.resolver" class="border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                            Сотрудники
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="radio" value="group" x-model="editing.config.resolver" class="border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                            Роль / группа
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Сотрудники</label>
                                    <input type="text" x-model="userSearch" placeholder="Поиск по имени…"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 mb-1.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-50 max-h-40 overflow-y-auto">
                                        <template x-for="u in filteredUsers()" :key="u.id">
                                            <label class="flex items-center gap-2 px-2.5 py-1.5 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" :checked="editing.config.approver_ids.includes(u.id)"
                                                       @change="toggleId(editing.config.approver_ids, u.id)"
                                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                                <span class="text-sm text-gray-800" x-text="u.name"></span>
                                                <span class="ml-auto text-xs text-gray-400 truncate" x-text="u.position"></span>
                                            </label>
                                        </template>
                                    </div>
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        <template x-for="id in editing.config.approver_ids" :key="id">
                                            <span class="inline-flex items-center gap-1 bg-[#5B4FE8]/10 text-[#5B4FE8] border border-[#5B4FE8]/20 rounded-full px-2 py-0.5 text-xs">
                                                <span x-text="userName(id)"></span>
                                                <button type="button" @click="toggleId(editing.config.approver_ids, id)" class="hover:text-red-500">×</button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Направления</label>
                                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-50 max-h-32 overflow-y-auto">
                                        <template x-for="d in directions" :key="d.id">
                                            <label class="flex items-center gap-2 px-2.5 py-1.5 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" :checked="editing.config.group_department_ids.includes(d.id)"
                                                       @change="toggleId(editing.config.group_department_ids, d.id)"
                                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                                <span class="text-sm text-gray-800" x-text="d.name"></span>
                                            </label>
                                        </template>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Направление разворачивается в сотрудников его отделов при публикации.</p>
                                </div>

                                <div x-show="editing.config.resolver === 'group'">
                                    <label class="text-xs text-gray-500 block mb-1">Роль</label>
                                    <select x-model="editing.config.group_role"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <option value="">— любая —</option>
                                        <template x-for="r in roles" :key="r.code">
                                            <option :value="r.code" x-text="r.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Политика</label>
                                        <select x-model="editing.config.policy"
                                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                            <option value="any">Достаточно одного</option>
                                            <option value="all">Решают все</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Срок, рабочих дней</label>
                                        <input type="number" min="1" max="365" x-model.number="editing.config.sla_days"
                                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    </div>
                                </div>

                                <div x-show="['ack', 'opinion'].includes(editing.type)">
                                    <label class="flex items-center justify-between text-sm text-gray-700 cursor-pointer">
                                        Блокирующее звено
                                        <input type="checkbox" x-model="editing.config.is_blocking" class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    </label>
                                    <p x-show="!editing.config.is_blocking" class="text-xs text-gray-400 mt-1">
                                        Не держит маршрут: участники получают задачу, документ идёт дальше.
                                    </p>
                                </div>

                                <div x-show="types[editing.type].branching">
                                    <label class="text-xs text-gray-500 block mb-1">Если ветка «Нет» пуста</label>
                                    <select x-model="editing.config.on_reject"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        @foreach(WorkflowStage::ON_REJECT as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-400 mt-1">Когда в ветке «Нет» есть блоки, документ идёт по ней.</p>
                                </div>
                            </div>
                        </template>

                        {{-- Условие --}}
                        <template x-if="editing.type === 'condition'">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Проверять</label>
                                    <select x-model="editing.config.source"
                                            @change="editing.config.condition_operator = editing.config.source === 'initiator_department' ? 'in' : '='"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        @foreach(WorkflowNode::CONDITION_SOURCES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Отдел инициатора: разные авторы — разные цепочки согласования --}}
                                <template x-if="editing.config.source === 'initiator_department'">
                                    <div class="space-y-2">
                                        <select x-model="editing.config.condition_operator"
                                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                            @foreach(WorkflowNode::DEPARTMENT_OPERATORS as $value => $label)
                                                <option value="{{ $value }}">Инициатор {{ $label }} к отделу</option>
                                            @endforeach
                                        </select>

                                        <div class="border border-gray-200 rounded-lg divide-y divide-gray-50 max-h-48 overflow-y-auto">
                                            <template x-for="d in departments" :key="d.id">
                                                <label class="flex items-center gap-2 px-2.5 py-1.5 hover:bg-gray-50 cursor-pointer">
                                                    <input type="checkbox" :checked="editing.config.department_ids.includes(d.id)"
                                                           @change="toggleId(editing.config.department_ids, d.id)"
                                                           class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                                    <span class="text-sm text-gray-800" x-text="d.name"></span>
                                                </label>
                                            </template>
                                        </div>
                                        <p class="text-xs text-gray-400">
                                            Направление включает все свои отделы. Выход «Да» — инициатор из выбранных подразделений,
                                            «Нет» — из любых других.
                                        </p>
                                    </div>
                                </template>

                                <template x-if="editing.config.source !== 'initiator_department'">
                                    <div class="space-y-3">
                                <template x-if="parameters.length === 0">
                                    <p class="text-xs text-amber-600">Сначала добавьте параметр на шаге 3 — условию не на что ссылаться.</p>
                                </template>

                                <div class="flex gap-2">
                                    <select x-model="editing.config.condition_key"
                                            class="flex-1 text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <option value="">— выберите параметр —</option>
                                        <template x-for="p in parameters" :key="p.key">
                                            <option :value="p.key" x-text="p.label"></option>
                                        </template>
                                    </select>
                                    <select x-model="editing.config.condition_operator"
                                            class="w-32 text-sm border border-gray-200 rounded-lg px-1 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        @foreach(WorkflowStage::OPERATORS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <template x-if="conditionOptions(editing.config.condition_key).length > 0">
                                    <select x-model="editing.config.condition_value"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <template x-for="option in conditionOptions(editing.config.condition_key)" :key="option">
                                            <option :value="option" x-text="option"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="conditionOptions(editing.config.condition_key).length === 0">
                                    <input type="text" x-model="editing.config.condition_value" placeholder="значение"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Статус документа --}}
                        <template x-if="editing.type === 'status'">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Перевести документ в статус</label>
                                <select x-model="editing.config.status"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    @foreach(WorkflowNode::STATUSES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </template>

                        {{-- Почтовое сообщение --}}
                        <template x-if="editing.type === 'notify'">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Кому</label>
                                    <select x-model="editing.config.recipients"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        @foreach(WorkflowNode::RECIPIENTS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div x-show="editing.config.recipients === 'users'">
                                    <label class="text-xs text-gray-500 block mb-1">Сотрудники</label>
                                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-50 max-h-40 overflow-y-auto">
                                        <template x-for="u in users" :key="u.id">
                                            <label class="flex items-center gap-2 px-2.5 py-1.5 hover:bg-gray-50 cursor-pointer">
                                                <input type="checkbox" :checked="editing.config.user_ids.includes(u.id)"
                                                       @change="toggleId(editing.config.user_ids, u.id)"
                                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                                <span class="text-sm text-gray-800" x-text="u.name"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Текст сообщения</label>
                                    <textarea x-model="editing.config.text" rows="3" placeholder="Если пусто — уйдёт название документа"
                                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                                </div>
                            </div>
                        </template>

                        {{-- Завершение --}}
                        <template x-if="editing.type === 'end'">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Чем завершить процесс</label>
                                <select x-model="editing.config.result"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    @foreach(WorkflowNode::RESULTS as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 py-3 border-t border-gray-100 flex justify-end sticky bottom-0 bg-white rounded-b-xl">
                        <button type="button" @click="editing = null"
                                class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Готово</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Сохранение маршрута ────────────────────────────────────────────── --}}
    <form action="{{ route('admin.scenarios.route', $scenario) }}" method="POST" @submit="$refs.graphInput.value = serialize()">
        @csrf @method('PUT')
        <input type="hidden" name="graph" x-ref="graphInput" value="[]">

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
        @error('graph')<p class="text-xs text-red-500 mt-2">{{ $message }}</p>@enderror
    </div>
</div>
