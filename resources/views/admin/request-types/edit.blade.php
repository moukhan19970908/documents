<x-app-layout>
    <x-slot name="title">{{ $typeLabel }} — конструктор заявки — Vamin</x-slot>

    <div x-data="requestGraph(@js($data))">
        {{-- Шапка --}}
        <div class="flex items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.request-types.index') }}" class="text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h1 class="text-lg font-bold text-gray-900">{{ $typeLabel }}</h1>
                    <p class="text-xs text-gray-400">
                        Конструктор процесса заявки ·
                        <span class="font-medium {{ $flow->status === 'published' ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $flow->status === 'published' ? 'опубликован (v'.$flow->version.')' : 'черновик' }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <form action="{{ route('admin.request-types.flow.update', $type) }}" method="POST" @submit="$refs.graphInput.value = serialize()">
                    @csrf @method('PUT')
                    <input type="hidden" name="graph" x-ref="graphInput" value="[]">
                    <button type="submit" class="px-5 py-2.5 border border-gray-200 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50">Сохранить</button>
                </form>
                <form action="{{ route('admin.request-types.flow.publish', $type) }}" method="POST"
                      onsubmit="return confirm('Опубликовать граф? Новые заявки этого вида пойдут по нему. Сначала сохраните изменения.')">
                    @csrf
                    <button type="submit" class="px-5 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">Опубликовать</button>
                </form>
            </div>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mb-4 text-xs text-amber-700">
            Сохранение — черновик. «Опубликовать» — новые заявки пойдут по графу; уже поданные идут по своей версии маршрута.
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">{{ session('success') }}</div>
        @endif

        <div class="flex gap-4 items-start">
            {{-- Схема --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-semibold text-gray-800">Схема процесса</p>
                    <p class="text-xs text-gray-400">
                        <span x-show="insertAt" class="text-[#5B4FE8] font-medium">Выберите блок справа — встанет в отмеченную точку</span>
                        <span x-show="!insertAt">Нажмите «+» на схеме, затем блок справа</span>
                    </p>
                </div>
                <div class="bg-[#F7F8FB] border border-gray-200 rounded-xl overflow-auto" style="max-height:72vh">
                    <div class="relative" :style="`width:${layout.width}px; height:${layout.height}px; min-width:100%`">
                        <svg class="absolute top-0 left-0 pointer-events-none" :width="layout.width" :height="layout.height">
                            <path :d="layout.linePath" fill="none" stroke="#c3cad8" stroke-width="1.5"></path>
                            <path :d="layout.arrowPath" fill="#c3cad8"></path>
                        </svg>

                        {{-- Начало --}}
                        <div class="absolute flex items-center justify-center bg-emerald-50 border border-emerald-300 rounded-lg text-sm font-medium text-emerald-700"
                             :style="`left:${layout.startX}px; top:24px; width:140px; height:40px`">Подача заявки</div>

                        {{-- Точки вставки --}}
                        <template x-for="(plus, i) in layout.pluses" :key="'p'+i">
                            <button type="button" @click="aim(plus)"
                                    :class="sameTarget(plus) ? 'bg-[#5B4FE8] text-white border-[#5B4FE8]' : 'bg-white text-gray-400 border-gray-300 hover:border-[#5B4FE8] hover:text-[#5B4FE8]'"
                                    class="absolute w-6 h-6 rounded-full border flex items-center justify-center text-sm leading-none -translate-x-1/2 -translate-y-1/2 z-10"
                                    :style="`left:${plus.x}px; top:${plus.y}px`">+</button>
                        </template>

                        {{-- Карточки узлов --}}
                        <template x-for="card in layout.cards" :key="card.uid">
                            <div class="absolute rounded-xl border bg-white shadow-sm"
                                 :class="editing && editing.uid === card.uid ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200'"
                                 :style="`left:${card.x}px; top:${card.y}px; width:248px`">
                                <div class="flex items-start gap-2 px-3 py-2.5">
                                    <span class="mt-0.5 w-2.5 h-2.5 rounded-full shrink-0" :class="dotClass(card.node.type)"></span>
                                    <div class="min-w-0 flex-1 cursor-pointer" @click="openNode(card.uid)">
                                        <p class="text-sm font-medium text-gray-900 truncate" x-text="card.node.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="summary(card.node)"></p>
                                    </div>
                                    <button type="button" @click="removeNode(card.uid)" class="text-gray-300 hover:text-red-500 shrink-0">×</button>
                                </div>
                                {{-- метки веток --}}
                                <template x-if="isBranching(card.node)">
                                    <div class="flex text-[10px] text-gray-400 border-t border-gray-100">
                                        <template x-for="b in branchList(card.node)" :key="b.key">
                                            <span class="flex-1 text-center py-1 truncate" x-text="b.label"></span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="nodes.length === 0">
                            <p class="absolute left-0 right-0 text-xs text-gray-400 text-center" :style="`top:${layout.height-40}px`">Пустой процесс — нажмите «+» и добавьте узел</p>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Палитра --}}
            <div class="w-64 shrink-0">
                <p class="text-xs text-gray-400 mb-2">Перетащите узел на холст</p>
                <template x-for="(groupLabel, groupKey) in groups" :key="groupKey">
                    <div class="mb-4">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-1.5" x-text="groupLabel"></p>
                        <div class="space-y-1.5">
                            <template x-for="(meta, typeKey) in typesOfGroup(groupKey)" :key="typeKey">
                                <button type="button" @click="addNode(typeKey)"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 bg-white border border-gray-200 rounded-lg text-left hover:border-[#5B4FE8]/50 transition">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="dotClass(typeKey)"></span>
                                    <span class="text-sm text-gray-700" x-text="meta.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Модалка настроек узла --}}
        <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="editing = null"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-y-auto" x-show="editing">
                <template x-if="editing">
                    <div>
                        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 sticky top-0 bg-white">
                            <p class="text-sm font-semibold text-gray-800" x-text="types[editing.type].label"></p>
                            <button type="button" @click="editing = null" class="text-gray-400 hover:text-gray-600">×</button>
                        </div>

                        <div class="p-5 space-y-4">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Заголовок узла</label>
                                <input type="text" x-model="editing.name" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            </div>

                            {{-- Согласующий (роль) --}}
                            <div x-show="editing.type === 'approver_role'">
                                <label class="text-xs text-gray-500 block mb-1">Роль согласующего</label>
                                <select x-model="editing.config.group_role" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                                    <option value="">— выберите роль —</option>
                                    <template x-for="r in roles" :key="r.code"><option :value="r.code" x-text="r.name"></option></template>
                                </select>
                            </div>

                            {{-- Согласующий (по оргструктуре) --}}
                            <div x-show="editing.type === 'approver_org'">
                                <label class="text-xs text-gray-500 block mb-1">Минимальный уровень руководителя (по цепочке от инициатора)</label>
                                <select x-model.number="editing.config.role_level" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                                    <template x-for="(lbl, lvl) in roleLevels" :key="lvl"><option :value="parseInt(lvl)" x-text="lbl"></option></template>
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Система поднимается по manager_id от инициатора до первого руководителя этого уровня.</p>
                            </div>

                            {{-- Задание исполнителю --}}
                            <div x-show="editing.type === 'task'" class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Исполнитель</label>
                                    <select x-model="editing.config.assignee" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                                        <option value="">— выберите —</option>
                                        <template x-for="e in executors" :key="e.key"><option :value="e.key" x-text="e.label"></option></template>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Что сделать</label>
                                    <input type="text" x-model="editing.config.title" placeholder="напр. Подобрать тур" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                                </div>
                            </div>

                            {{-- Уведомление --}}
                            <div x-show="editing.type === 'notify'">
                                <label class="text-xs text-gray-500 block mb-1">Текст уведомления</label>
                                <textarea x-model="editing.config.text" rows="2" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2"></textarea>
                            </div>

                            {{-- Ветвящиеся узлы: редактор веток --}}
                            <template x-if="isBranching(editing)">
                                <div class="space-y-3">
                                    {{-- Условие по полю: выбор поля --}}
                                    <div x-show="editing.type === 'cond_field'">
                                        <label class="text-xs text-gray-500 block mb-1">Поле для проверки</label>
                                        <select x-model="editing.config.field" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                                            <option value="">— выберите поле —</option>
                                            <template x-for="f in fields" :key="f.key"><option :value="f.key" x-text="f.label"></option></template>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1.5">Ветки</label>
                                        <div class="space-y-2">
                                            <template x-for="(b, i) in editing.config.branches" :key="b.key">
                                                <div class="flex items-center gap-2">
                                                    <input type="text" x-model="b.label" placeholder="Название ветки" class="flex-1 text-sm border border-gray-200 rounded-lg px-2.5 py-1.5">
                                                    {{-- значение поля (для условия по полю) --}}
                                                    <template x-if="editing.type === 'cond_field'">
                                                        <select x-model="b.value" class="w-32 text-sm border border-gray-200 rounded-lg px-1.5 py-1.5">
                                                            <option value="">иначе</option>
                                                            <template x-for="(optLabel, optKey) in fieldOptions(editing.config.field)" :key="optKey">
                                                                <option :value="optKey" x-text="optLabel"></option>
                                                            </template>
                                                        </select>
                                                    </template>
                                                    {{-- условие по инициатору: уровень роли + опционально отдел --}}
                                                    <template x-if="editing.type === 'cond_role'">
                                                        <div class="flex items-center gap-1.5">
                                                            <select x-model.number="b.role_level" class="w-28 text-sm border border-gray-200 rounded-lg px-1.5 py-1.5">
                                                                <option :value="0">любой уровень</option>
                                                                <template x-for="(lbl, lvl) in roleLevels" :key="lvl"><option :value="parseInt(lvl)" x-text="lbl"></option></template>
                                                            </select>
                                                            <select x-model.number="b.department_id" class="w-32 text-sm border border-gray-200 rounded-lg px-1.5 py-1.5">
                                                                <option :value="0">любой отдел</option>
                                                                <template x-for="d in departments" :key="d.id"><option :value="d.id" x-text="d.name"></option></template>
                                                            </select>
                                                        </div>
                                                    </template>
                                                    <button type="button" @click="removeBranch(editing, i)" class="text-gray-300 hover:text-red-500 shrink-0 px-1">×</button>
                                                </div>
                                            </template>
                                        </div>
                                        <button type="button" @click="addBranch(editing)" class="mt-2 text-xs text-[#5B4FE8] hover:underline">+ Добавить ветку</button>
                                        <p class="text-xs text-gray-400 mt-1">Каждая ветка — свой путь ниже развилки. То, что нарисовано после узла, продолжается для всех веток.</p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="['registry','auto','success','reject'].includes(editing.type)">
                                <p class="text-xs text-gray-400">Дополнительных настроек нет.</p>
                            </template>
                        </div>

                        <div class="px-5 py-3 border-t border-gray-100 flex justify-end sticky bottom-0 bg-white">
                            <button type="button" @click="editing = null" class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Готово</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const RG_NODE_W = 248, RG_NODE_H = 62, RG_GAP_V = 44, RG_GAP_H = 28, RG_PLUS_H = 28, RG_START_H = 40, RG_PAD = 24;
        let rgSeq = 0;
        const rgUid = () => 'n' + (++rgSeq) + Date.now().toString(36);

        function rgDefaultConfig(type) {
            switch (type) {
                case 'approver_role': return { group_role: '', policy: 'all' };
                case 'approver_org':  return { role_level: 2 };
                case 'task':          return { assignee: '', title: '' };
                case 'notify':        return { recipients: 'initiator', text: '' };
                case 'auto':          return { action: '' };
                case 'cond_field':    return { field: '', branches: [ { key: 'b1', label: 'Ветка 1', value: '' }, { key: 'else', label: 'Иначе', value: '' } ] };
                case 'cond_role':     return { branches: [ { key: 'linear', label: 'Линейный сотрудник', role_level: 1, department_id: 0 }, { key: 'manager', label: 'Руководитель', role_level: 2, department_id: 0 } ] };
                case 'parallel':      return { branches: [ { key: 'p1', label: 'Поток 1' }, { key: 'p2', label: 'Поток 2' } ] };
                default:              return {};
            }
        }

        function requestGraph(initial) {
            const isBranchingType = (type) => !!(initial.types[type] || {}).branching;

            const prepare = (chain) => (chain || []).map(n => {
                const node = { uid: rgUid(), type: n.type, name: n.name, config: { ...rgDefaultConfig(n.type), ...(n.config || {}) }, branches: {} };
                if (isBranchingType(n.type)) {
                    (node.config.branches || []).forEach(b => { node.branches[b.key] = prepare((n.branches || {})[b.key] || []); });
                }
                return node;
            });

            return {
                types: initial.types,
                groups: initial.groups,
                roles: initial.roles,
                roleLevels: initial.roleLevels,
                fields: initial.fields,
                executors: initial.executors,
                departments: initial.departments,
                nodes: prepare(initial.nodes),
                editing: null,
                insertAt: null,

                isBranching(node) { return isBranchingType(node.type); },
                branchList(node) { return node.config.branches || []; },
                typesOfGroup(g) { const o = {}; for (const k in this.types) if (this.types[k].group === g) o[k] = this.types[k]; return o; },
                fieldOptions(key) { const f = (this.fields || []).find(x => x.key === key); return f ? f.options : {}; },

                dotClass(type) {
                    const g = (this.types[type] || {}).group;
                    return { approval: 'bg-indigo-400', flow: 'bg-purple-400', tasks: 'bg-amber-400', end: 'bg-emerald-400' }[g] || 'bg-gray-300';
                },

                summary(node) {
                    const c = node.config || {};
                    if (node.type === 'approver_role') { const r = (this.roles.find(x => x.code === c.group_role) || {}).name; return r || 'роль не выбрана'; }
                    if (node.type === 'approver_org')  return this.roleLevels[c.role_level] || '—';
                    if (node.type === 'task')          { const e = (this.executors.find(x => x.key === c.assignee) || {}).label; return (c.title || 'задание') + (e ? ' · ' + e : ''); }
                    if (node.type === 'cond_field')    { const f = (this.fields.find(x => x.key === c.field) || {}).label; return f ? 'по полю: ' + f : 'поле не выбрано'; }
                    if (node.type === 'cond_role')     return 'по инициатору (роль/отдел)';
                    if (node.type === 'parallel')      return (c.branches || []).length + ' поток(ов)';
                    if (node.type === 'notify')        return c.text ? c.text.slice(0, 40) : 'без текста';
                    return this.types[node.type].label;
                },

                // ── схема ─────────────────────────────────────────────────
                get layout() {
                    const chain = this.measureChain(this.nodes);
                    const cards = [], edges = [], pluses = [];
                    const centerX = RG_PAD + Math.max(chain.w, RG_NODE_W) / 2;
                    const startBottom = RG_PAD + RG_START_H;
                    edges.push(this.vertical(centerX, startBottom, startBottom + RG_GAP_V));
                    this.placeChain(this.nodes, { centerX, top: startBottom + RG_GAP_V, parent: null, branch: 'main', cards, edges, pluses });
                    return {
                        width: Math.max(chain.w, RG_NODE_W) + RG_PAD * 2,
                        height: startBottom + RG_GAP_V + chain.h + RG_PAD,
                        startX: centerX - 70,
                        linePath: edges.map(e => e.d).join(' '),
                        arrowPath: edges.map(e => this.arrowhead(e.arrow)).join(' '),
                        cards, pluses,
                    };
                },

                measureChain(chain) {
                    let w = RG_NODE_W, h = 0;
                    if (!chain.length) return { w, h: RG_PLUS_H };
                    chain.forEach(n => { const m = this.measureNode(n); w = Math.max(w, m.w); h += m.h + RG_GAP_V; });
                    return { w, h: h + RG_PLUS_H };
                },

                measureNode(node) {
                    if (!this.isBranching(node)) return { w: RG_NODE_W, h: RG_NODE_H };
                    const keys = this.branchList(node).map(b => b.key);
                    const ms = keys.map(k => this.measureChain(node.branches[k] || []));
                    const w = ms.reduce((s, m) => s + m.w, 0) + RG_GAP_H * Math.max(0, keys.length - 1);
                    const h = Math.max(...ms.map(m => m.h), RG_PLUS_H);
                    return { w: Math.max(RG_NODE_W, w), h: RG_NODE_H + RG_GAP_V + h };
                },

                placeChain(chain, ctx) {
                    const { centerX, parent, branch, cards, edges, pluses } = ctx;
                    let y = ctx.top;
                    if (!chain.length) { pluses.push({ x: centerX, y: y + RG_PLUS_H / 2, parent, branch, index: 0 }); return y + RG_PLUS_H; }
                    chain.forEach((node, i) => {
                        cards.push({ uid: node.uid, node, x: centerX - RG_NODE_W / 2, y });
                        let bottom = y + RG_NODE_H;
                        if (this.isBranching(node)) {
                            const keys = this.branchList(node).map(b => b.key);
                            const ms = keys.map(k => this.measureChain(node.branches[k] || []));
                            const total = ms.reduce((s, m) => s + m.w, 0) + RG_GAP_H * Math.max(0, keys.length - 1);
                            let left = centerX - total / 2;
                            const branchTop = y + RG_NODE_H + RG_GAP_V;
                            const bottoms = [];
                            keys.forEach((k, bi) => {
                                const colX = left + ms[bi].w / 2;
                                edges.push(this.elbow(centerX, y + RG_NODE_H, colX, branchTop));
                                const b = this.placeChain(node.branches[k] || [], { ...ctx, centerX: colX, top: branchTop, parent: node.uid, branch: k });
                                bottoms.push({ x: colX, b });
                                left += ms[bi].w + RG_GAP_H;
                            });
                            const merge = branchTop + Math.max(...ms.map(m => m.h));
                            bottoms.forEach(bt => edges.push(this.elbow(bt.x, bt.b, centerX, merge, true)));
                            bottom = merge;
                        }
                        pluses.push({ x: centerX, y: bottom + RG_GAP_V / 2, parent, branch, index: i + 1 });
                        edges.push(this.vertical(centerX, bottom, bottom + RG_GAP_V));
                        y = bottom + RG_GAP_V;
                    });
                    pluses.push({ x: centerX, y: y + RG_PLUS_H / 2, parent, branch, index: chain.length });
                    return y + RG_PLUS_H;
                },

                arrowhead({ x, y }) { return `M ${x-4} ${y-7} L ${x+4} ${y-7} L ${x} ${y} Z`; },
                vertical(x, from, to) { return { d: `M ${x} ${from} L ${x} ${to}`, arrow: { x, y: to } }; },
                elbow(fromX, fromY, toX, toY, up = false) { const mid = up ? toY - RG_GAP_V / 2 : fromY + RG_GAP_V / 2; return { d: `M ${fromX} ${fromY} L ${fromX} ${mid} L ${toX} ${mid} L ${toX} ${toY}`, arrow: { x: toX, y: toY } }; },

                // ── операции ──────────────────────────────────────────────
                aim(t) { this.insertAt = this.sameTarget(t) ? null : t; },
                sameTarget(t) { const a = this.insertAt; return a && a.parent === t.parent && a.branch === t.branch && a.index === t.index; },

                addNode(type) {
                    const node = { uid: rgUid(), type, name: this.types[type].label, config: rgDefaultConfig(type), branches: {} };
                    if (this.isBranching(node)) (node.config.branches || []).forEach(b => node.branches[b.key] = []);
                    const target = this.insertAt || { parent: null, branch: 'main', index: this.nodes.length };
                    const chain = this.chainOf(target.parent, target.branch);
                    if (!chain) return;
                    chain.splice(target.index, 0, node);
                    this.insertAt = null;
                    this.editing = node;
                },

                removeNode(uid) { const f = this.find(uid); if (!f) return; f.chain.splice(f.index, 1); if (this.editing && this.editing.uid === uid) this.editing = null; },
                openNode(uid) { const f = this.find(uid); this.editing = f ? f.node : null; },

                chainOf(parentUid, branch) { if (!parentUid) return this.nodes; const f = this.find(parentUid); return f ? (f.node.branches[branch] = f.node.branches[branch] || []) : null; },

                find(uid, chain = null) {
                    const list = chain || this.nodes;
                    for (let i = 0; i < list.length; i++) {
                        if (list[i].uid === uid) return { node: list[i], chain: list, index: i };
                        const br = list[i].branches || {};
                        for (const k in br) { const nested = this.find(uid, br[k] || []); if (nested) return nested; }
                    }
                    return null;
                },

                addBranch(node) {
                    const key = 'b' + (Date.now().toString(36));
                    node.config.branches.push(node.type === 'cond_role' ? { key, label: 'Ветка', role_level: 0, department_id: 0 } : { key, label: 'Ветка', value: '' });
                    node.branches[key] = [];
                },
                removeBranch(node, i) {
                    const b = node.config.branches[i];
                    if (!b) return;
                    node.config.branches.splice(i, 1);
                    delete node.branches[b.key];
                },

                serialize() {
                    const clean = (chain) => chain.map(n => {
                        const out = { type: n.type, name: n.name, config: n.config };
                        if (this.isBranching(n)) { out.branches = {}; (n.config.branches || []).forEach(b => out.branches[b.key] = clean(n.branches[b.key] || [])); }
                        return out;
                    });
                    return JSON.stringify(clean(this.nodes));
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
