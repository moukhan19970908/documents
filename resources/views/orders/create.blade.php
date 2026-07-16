<x-app-layout>
    <x-slot name="title">Издание приказа — Vamin</x-slot>

    @php
        $editing = isset($order);
        $today = now()->format('Y-m-d');
    @endphp

    <div x-data="orderCreate()" x-cloak>
        <div class="mb-5">
            <h1 class="text-xl font-bold text-gray-900">Издание приказа</h1>
        </div>

        {{-- Степпер --}}
        <div class="flex items-center gap-3 mb-6">
            @foreach(['Документ', 'Адресаты', 'Публикация'] as $i => $label)
                @php $n = $i + 1; @endphp
                @if($i > 0)
                    <svg class="w-3.5 h-3.5 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                @endif
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full text-xs font-semibold flex items-center justify-center"
                          :class="step === {{ $n }} ? 'bg-[#5B4FE8] text-white' : (step > {{ $n }} ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-400')">{{ $n }}</span>
                    <span class="text-sm font-medium" :class="step === {{ $n }} ? 'text-gray-900' : 'text-gray-400'">{{ $label }}</span>
                </div>
            @endforeach
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-100 rounded-xl px-4 py-3 mb-5">
                <ul class="text-sm text-red-600 list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $editing ? route('orders.update', $order) : route('orders.store') }}"
              enctype="multipart/form-data" x-ref="form">
            @csrf
            @if($editing) @method('PUT') @endif
            <input type="hidden" name="action" :value="action">
            <input type="hidden" name="source" :value="source">
            <input type="hidden" name="blank_template_id" :value="source === 'blank' ? blankId : ''">
            <input type="hidden" name="kind" :value="kind">
            <input type="hidden" name="title" :value="title">
            <input type="hidden" name="effective_at" :value="effectiveAt">
            <input type="hidden" name="ack_deadline" :value="ackDeadline">
            <input type="hidden" name="requires_approval" :value="requiresApproval ? 1 : 0">
            <template x-for="id in selectedDepts" :key="'d' + id"><input type="hidden" name="department_ids[]" :value="id"></template>
            <template x-for="id in selectedUsers" :key="'u' + id"><input type="hidden" name="user_ids[]" :value="id"></template>

            {{-- ─────────────── Шаг 1: документ ─────────────── --}}
            <div x-show="step === 1" class="flex gap-6">
                <div class="flex-1 min-w-0 space-y-4">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="source = 'file'"
                                :class="source === 'file' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:bg-gray-50'"
                                class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-200">Загрузить готовый файл</button>
                        <button type="button" @click="source = 'blank'"
                                :class="source === 'blank' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:bg-gray-50'"
                                class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-200">Создать из шаблона</button>
                    </div>

                    {{-- Бланки --}}
                    <div x-show="source === 'blank'">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Бланк приказа</p>
                        <div class="flex gap-3 flex-wrap">
                            @foreach($blanks as $blank)
                                <button type="button" @click="pickBlank({{ $blank->id }})"
                                        :class="blankId === {{ $blank->id }} ? 'border-[#5B4FE8] ring-1 ring-[#5B4FE8]' : 'border-gray-200 hover:border-gray-300'"
                                        class="bg-white border rounded-xl p-3 w-40 text-left shrink-0">
                                    <span class="block h-16 rounded-lg bg-gray-50 border border-gray-100 mb-2"></span>
                                    <span class="block text-xs font-semibold text-gray-700 truncate">{{ $blank->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="source === 'blank'">
                        @include('partials.blank-editor', ['content' => $editing ? ($order->body_html ?? '') : '', 'inputName' => 'body_html'])
                    </div>

                    <div x-show="source === 'file'" class="bg-white border border-gray-200 rounded-xl p-6">
                        <input type="file" name="file"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#5B4FE8] file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700">
                        <p class="text-xs text-gray-400 mt-2">Максимальный размер файла: 50 МБ</p>
                    </div>
                </div>

                {{-- Свойства --}}
                <aside class="w-80 shrink-0">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Свойства</p>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Вид приказа</label>
                            <select x-model="kind" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                @foreach($kinds as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Краткое название</label>
                            <input type="text" x-model="title" placeholder="О премировании…"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Дата вступления в силу</label>
                            <input type="date" x-model="effectiveAt"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>
                    </div>
                </aside>
            </div>

            {{-- ─────────────── Шаг 2: адресаты ─────────────── --}}
            <div x-show="step === 2" class="max-w-4xl space-y-5">
                <div class="flex items-center gap-2">
                    <button type="button" @click="audienceTab = 'dept'"
                            :class="audienceTab === 'dept' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:bg-gray-50'"
                            class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-200">По направлениям / отделам</button>
                    <button type="button" @click="audienceTab = 'people'"
                            :class="audienceTab === 'people' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:bg-gray-50'"
                            class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-200">По сотрудникам</button>
                </div>

                <div x-show="audienceTab === 'dept'" class="bg-white border border-gray-200 rounded-xl p-2">
                    @forelse($tree as $node)
                        @include('orders.partials.dept-node', ['node' => $node, 'depth' => 0])
                    @empty
                        <p class="text-sm text-gray-400 p-4">Отделы не заведены — используйте выбор по сотрудникам.</p>
                    @endforelse
                </div>

                <div x-show="audienceTab === 'people'" class="bg-white border border-gray-200 rounded-xl p-2 max-h-96 overflow-y-auto">
                    @foreach($people as $person)
                        <label class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" @change="toggleUser({{ $person['id'] }})" :checked="selectedUsers.includes({{ $person['id'] }})"
                                   class="w-5 h-5 rounded accent-[#5B4FE8] cursor-pointer">
                            <span class="text-sm text-gray-800">{{ $person['name'] }}</span>
                            <span class="ml-auto text-xs text-gray-400">{{ $person['position'] }}</span>
                        </label>
                    @endforeach
                </div>

                {{-- Итог по адресатам --}}
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-semibold text-gray-900">Адресаты</span>
                        <span class="text-sm font-semibold text-[#5B4FE8]">Всего адресатов: <span x-text="totalRecipients()"></span></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="chip in deptChips()" :key="'dc' + chip.id">
                            <span class="inline-flex items-center gap-1.5 bg-indigo-50 border border-indigo-100 rounded-full pl-3 pr-1.5 py-1 text-sm text-gray-700">
                                <span x-text="chip.name"></span>
                                <span class="text-xs text-gray-400" x-text="'(' + chip.count + ' чел.)'"></span>
                                <button type="button" @click="toggleDept(chip.id)" class="w-5 h-5 rounded-full text-gray-400 hover:bg-gray-200">×</button>
                            </span>
                        </template>
                        <template x-for="chip in userChips()" :key="'uc' + chip.id">
                            <span class="inline-flex items-center gap-1.5 bg-violet-50 border border-violet-100 rounded-full pl-3 pr-1.5 py-1 text-sm text-gray-700">
                                <span x-text="chip.name"></span>
                                <button type="button" @click="toggleUser(chip.id)" class="w-5 h-5 rounded-full text-gray-400 hover:bg-gray-200">×</button>
                            </span>
                        </template>
                        <span x-show="totalRecipients() === 0" class="text-sm text-gray-400">Выберите отделы или сотрудников</span>
                    </div>
                </div>

                <div class="max-w-xs">
                    <label class="text-xs text-gray-500 block mb-1">Срок ознакомления</label>
                    <input type="date" x-model="ackDeadline"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
            </div>

            {{-- ─────────────── Шаг 3: публикация ─────────────── --}}
            <div x-show="step === 3" class="max-w-3xl mx-auto space-y-4">
                <h2 class="text-base font-bold text-gray-900">Проверьте перед публикацией</h2>

                <div class="bg-white border border-gray-200 rounded-xl p-5 flex gap-5">
                    <div class="w-24 h-28 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0">
                        <svg class="w-8 h-8 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[11px] bg-indigo-50 text-[#5B4FE8] rounded px-2 py-0.5" x-text="kindLabel()"></span>
                            <span class="text-[11px] text-gray-400" x-show="effectiveAt" x-text="'Вступает в силу: ' + fmt(effectiveAt)"></span>
                        </div>
                        <p class="text-base font-semibold text-gray-900" x-text="title || '— название не указано —'"></p>
                        <p class="text-xs text-gray-400 mt-1.5">Приказ № ___ — номер будет присвоен автоматически при публикации.</p>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <p class="text-sm font-semibold text-gray-900"><span x-text="totalRecipients()"></span> адресатов</p>
                    <p class="text-sm text-gray-500 mt-1" x-text="audienceSummary()"></p>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <label class="text-xs text-gray-500 block mb-1">Срок ознакомления</label>
                    <input type="date" x-model="ackDeadline" class="text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>

                {{-- Опциональная фаза согласования --}}
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="requiresApproval" class="w-5 h-5 rounded accent-[#5B4FE8]">
                        <span class="text-sm font-medium text-gray-900">Требует согласования</span>
                        <span class="text-xs text-gray-400">— перед публикацией (юрист, кадры, финансы)</span>
                    </label>
                    <div x-show="requiresApproval" x-cloak class="mt-4 space-y-3 max-w-md">
                        <template x-for="role in approverRoles" :key="role.key">
                            <div class="flex items-center gap-3">
                                <span class="w-20 text-sm text-gray-600" x-text="role.label"></span>
                                <select :name="`approvers[${role.key}]`" class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    <option value="">— не требуется —</option>
                                    <template x-for="p in people" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                        <p class="text-xs text-gray-400">Выберите хотя бы одного согласующего.</p>
                    </div>
                </div>
            </div>

            {{-- Навигация --}}
            <div class="flex items-center justify-between mt-6 max-w-4xl">
                <div>
                    <button type="button" x-show="step > 1" @click="step--"
                            class="flex items-center gap-2 px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Назад
                    </button>
                    <a x-show="step === 1" href="{{ route('orders.index') }}"
                       class="inline-block px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" @click="submit('draft')"
                            class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сохранить черновик</button>

                    <button type="button" x-show="step === 1" @click="if (canStep1()) step = 2" :disabled="!canStep1()"
                            :class="canStep1() ? 'bg-[#5B4FE8] hover:bg-indigo-700' : 'bg-gray-200 cursor-not-allowed'"
                            class="flex items-center gap-2 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Далее: адресаты →</button>

                    <button type="button" x-show="step === 2" @click="if (canStep2()) step = 3" :disabled="!canStep2()"
                            :class="canStep2() ? 'bg-[#5B4FE8] hover:bg-indigo-700' : 'bg-gray-200 cursor-not-allowed'"
                            class="flex items-center gap-2 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Далее: публикация →</button>

                    <button type="button" x-show="step === 3" @click="submit('publish')"
                            class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700"
                            x-text="requiresApproval ? 'Отправить на согласование' : 'Опубликовать приказ'"></button>
                </div>
            </div>
        </form>
    </div>

    <script>
    function orderCreate() {
        return {
            tree: @json($tree),
            people: @json($people),
            blanks: @json($blanks->map(fn ($b) => ['id' => $b->id, 'content' => $b->content])->values()),

            step: 1,
            action: '',
            source: @js($editing && $order->file_path ? 'file' : 'blank'),
            blankId: @js($editing ? $order->blank_template_id : null),

            kind: @js($editing ? $order->kind : 'operational'),
            title: @js($editing ? $order->title : ''),
            effectiveAt: @js($editing ? optional($order->effective_at)->format('Y-m-d') : $today),
            ackDeadline: @js($editing ? optional($order->ack_deadline)->format('Y-m-d') : now()->addDays(10)->format('Y-m-d')),

            audienceTab: 'dept',
            selectedDepts: @js($editing ? ($order->audience['department_ids'] ?? []) : []),
            selectedUsers: @js($editing ? ($order->audience['user_ids'] ?? []) : []),

            requiresApproval: @js($editing ? (bool) $order->requires_approval : false),
            approverRoles: [{ key: 'legal', label: 'Юрист' }, { key: 'hr', label: 'Кадры' }, { key: 'finance', label: 'Финансы' }],

            depts: {},

            init() {
                const flatten = (nodes, parentId) => nodes.forEach(n => {
                    this.depts[n.id] = { id: n.id, name: n.name, count: n.count, user_ids: n.user_ids, parentId, childIds: n.children.map(c => c.id) };
                    flatten(n.children, n.id);
                });
                flatten(this.tree, null);

                if (this.source === 'blank' && this.blankId) this.pickBlank(this.blankId);
            },

            descendants(id) {
                const out = [id];
                (this.depts[id]?.childIds ?? []).forEach(c => out.push(...this.descendants(c)));
                return out;
            },

            toggleDept(id) {
                const ids = this.descendants(id);
                if (this.selectedDepts.includes(id)) {
                    this.selectedDepts = this.selectedDepts.filter(x => !ids.includes(x));
                } else {
                    this.selectedDepts = [...new Set([...this.selectedDepts, ...ids])];
                }
            },
            isDeptChecked(id) { return this.selectedDepts.includes(id); },

            toggleUser(id) {
                this.selectedUsers = this.selectedUsers.includes(id)
                    ? this.selectedUsers.filter(x => x !== id)
                    : [...this.selectedUsers, id];
            },

            recipientIds() {
                const set = new Set(this.selectedUsers);
                this.selectedDepts.forEach(id => (this.depts[id]?.user_ids ?? []).forEach(u => set.add(u)));
                return set;
            },
            totalRecipients() { return this.recipientIds().size; },

            deptChips() {
                return this.selectedDepts
                    .filter(id => !this.selectedDepts.includes(this.depts[id]?.parentId))
                    .map(id => this.depts[id])
                    .filter(Boolean);
            },
            userChips() {
                return this.selectedUsers.map(id => this.people.find(p => p.id === id)).filter(Boolean);
            },
            audienceSummary() {
                const parts = this.deptChips().map(d => `${d.name} (${d.count})`);
                const users = this.userChips().length;
                if (users) parts.push(`+ ${users} ${this.plural(users, 'сотрудник', 'сотрудника', 'сотрудников')}`);
                return parts.join(', ') || 'Адресаты не выбраны';
            },

            pickBlank(id) {
                this.source = 'blank';
                this.blankId = id;
                const blank = this.blanks.find(b => b.id === id);
                this.$nextTick(() => window.dispatchEvent(new CustomEvent('set-blank-content', { detail: { html: blank?.content ?? '' } })));
            },

            kindLabel() {
                const map = @json($kinds);
                return map[this.kind] ?? this.kind;
            },
            fmt(d) {
                if (!d) return '';
                const [y, m, day] = d.split('-');
                return `${day}.${m}.${y}`;
            },
            plural(n, one, few, many) {
                const t = n % 10, h = n % 100;
                if (t === 1 && h !== 11) return one;
                if ([2, 3, 4].includes(t) && ![12, 13, 14].includes(h)) return few;
                return many;
            },

            canStep1() { return (this.title ?? '').trim() !== '' && !!this.kind; },
            canStep2() { return this.totalRecipients() > 0; },

            submit(action) {
                this.action = action;
                this.$nextTick(() => this.$refs.form.submit());
            },
        };
    }
    </script>
</x-app-layout>
