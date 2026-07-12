@php
    $stagesData = old('stages', $scenario->stages->map(fn ($s) => [
        'name'                => $s->name,
        'phase'               => $s->phase ?? 'approval',
        'resolver'            => $s->resolver ?? 'user',
        'approver_ids'        => $s->approvers->pluck('approver_id')->all(),
        'group_department_id' => $s->group_department_id,
        'group_role'          => $s->group_role,
        'policy'              => $s->policy ?? 'all',
        'sla_days'            => $s->sla_days,
        'is_blocking'         => (bool) $s->is_blocking,
        'on_reject'           => $s->on_reject ?? 'return_initiator',
        'condition_key'       => $s->condition_key,
        'condition_operator'  => $s->condition_operator ?? '=',
        'condition_value'     => $s->condition_value,
    ])->values()->all());

    $parametersForRoute = $scenario->parameters->map(fn ($p) => [
        'key'     => $p->key,
        'label'   => $p->label,
        'options' => $p->options ?? [],
        'type'    => $p->type,
    ])->values();

    $usersForRoute = $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();
    $departmentsForRoute = $departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values();
@endphp

<div x-data="routeBuilder()" x-show="step === 'route'">
    <form action="{{ route('admin.scenarios.route', $scenario) }}" method="POST">
        @csrf @method('PUT')

        <div class="flex gap-5">
            {{-- Палитра --}}
            <div class="w-56 shrink-0">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Палитра</p>
                <div class="space-y-2">
                    @foreach(\App\Models\WorkflowStage::PHASES as $phase => $phaseLabel)
                        <button type="button" @click="addStage('{{ $phase }}')"
                                class="w-full flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:border-[#5B4FE8] hover:text-[#5B4FE8]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Звено {{ mb_strtolower(str_replace('Фаза ', '', $phaseLabel)) }}
                        </button>
                    @endforeach

                    <div class="pt-2 mt-2 border-t border-gray-100 space-y-2">
                        <p class="text-xs text-gray-400 px-1">Параллельная группа и условие настраиваются в свойствах звена: политика «все из группы» и блок «Условное звено».</p>
                        <div class="opacity-40 space-y-2">
                            <div class="w-full flex items-center gap-2 bg-white border border-dashed border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-500" title="Следующий этап">Чек-лист</div>
                            <div class="w-full flex items-center gap-2 bg-white border border-dashed border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-500" title="Следующий этап">Подпроцесс</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Канвас --}}
            <div class="flex-1 bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-5 min-h-[420px]">
                @foreach(\App\Models\WorkflowStage::PHASES as $phase => $phaseLabel)
                    <div x-show="stagesOf('{{ $phase }}').length > 0"
                         class="border rounded-xl p-4 {{ ['approval' => 'bg-[#5B4FE8]/5 border-[#5B4FE8]/20', 'approve' => 'bg-emerald-50 border-emerald-100', 'ack' => 'bg-amber-50 border-amber-100', 'intake' => 'bg-gray-100 border-gray-200'][$phase] }}">
                        <p class="text-[10px] font-semibold uppercase tracking-widest mb-3 {{ ['approval' => 'text-[#5B4FE8]', 'approve' => 'text-emerald-600', 'ack' => 'text-amber-600', 'intake' => 'text-gray-500'][$phase] }}">{{ $phaseLabel }}</p>

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

                        <div>
                            <label class="text-xs text-gray-500 block mb-1.5">Резолвер исполнителя</label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer py-0.5">
                                <input type="radio" value="user" x-model="current().resolver" class="border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                Конкретный сотрудник
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer py-0.5">
                                <input type="radio" value="group" x-model="current().resolver" class="border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                Роль / группа
                            </label>
                        </div>

                        <div x-show="current().resolver === 'user'">
                            <label class="text-xs text-gray-500 block mb-1">Сотрудники</label>
                            <select multiple size="5" x-model.number="current().approver_ids"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <template x-for="u in allUsers" :key="u.id">
                                    <option :value="u.id" x-text="u.name"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="current().resolver === 'group'" class="space-y-2">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Отдел</label>
                                <select x-model.number="current().group_department_id"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    <option :value="null">— любой —</option>
                                    <template x-for="d in allDepartments" :key="d.id">
                                        <option :value="d.id" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Роль</label>
                                <select x-model="current().group_role"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    <option value="">— любая —</option>
                                    @foreach(\App\Models\DocumentType::CREATOR_ROLES as $role => $label)
                                        <option value="{{ $role }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="text-xs text-gray-400">Группа разворачивается в конкретных людей в момент публикации.</p>
                        </div>

                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Политика</label>
                            <select x-model="current().policy" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="any">Любой из группы</option>
                                <option value="all">Все участники</option>
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

                        <label class="flex items-center justify-between text-sm text-gray-700 cursor-pointer">
                            Блокирующее звено
                            <input type="checkbox" x-model="current().is_blocking" class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                        </label>
                        <p x-show="!current().is_blocking" class="text-xs text-gray-400 -mt-2">Не держит маршрут: участники получают задачу, документ идёт дальше.</p>

                        <div class="border-t border-gray-100 pt-3">
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mb-2">
                                <input type="checkbox" :checked="!!current().condition_key"
                                       @change="toggleCondition($event.target.checked)"
                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                Условное звено
                            </label>

                            <div x-show="current().condition_key !== null && current().condition_key !== ''" class="space-y-2">
                                <template x-if="parameters.length === 0">
                                    <p class="text-xs text-amber-600">Сначала добавьте параметр на шаге 3 — условию не на что ссылаться.</p>
                                </template>

                                <div class="flex gap-2">
                                    <select x-model="current().condition_key" class="flex-1 text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <template x-for="p in parameters" :key="p.key">
                                            <option :value="p.key" x-text="p.label"></option>
                                        </template>
                                    </select>
                                    <select x-model="current().condition_operator" class="w-20 text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        <option value="=">=</option>
                                        <option value="!=">≠</option>
                                        <option value="in">in</option>
                                        <option value=">">&gt;</option>
                                        <option value="<">&lt;</option>
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

                        <div>
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
        </div>

        {{-- Скрытые поля: источник истины — массив stages --}}
        <template x-for="(stage, index) in stages" :key="stage.uid">
            <div class="hidden">
                <input type="hidden" :name="`stages[${index}][name]`" :value="stage.name">
                <input type="hidden" :name="`stages[${index}][phase]`" :value="stage.phase">
                <input type="hidden" :name="`stages[${index}][resolver]`" :value="stage.resolver">
                <input type="hidden" :name="`stages[${index}][group_department_id]`" :value="stage.group_department_id ?? ''">
                <input type="hidden" :name="`stages[${index}][group_role]`" :value="stage.group_role ?? ''">
                <input type="hidden" :name="`stages[${index}][policy]`" :value="stage.policy">
                <input type="hidden" :name="`stages[${index}][sla_days]`" :value="stage.sla_days ?? ''">
                <input type="hidden" :name="`stages[${index}][is_blocking]`" :value="stage.is_blocking ? 1 : 0">
                <input type="hidden" :name="`stages[${index}][on_reject]`" :value="stage.on_reject">
                <input type="hidden" :name="`stages[${index}][condition_key]`" :value="stage.condition_key ?? ''">
                <input type="hidden" :name="`stages[${index}][condition_operator]`" :value="stage.condition_operator ?? '='">
                <input type="hidden" :name="`stages[${index}][condition_value]`" :value="stage.condition_value ?? ''">
                <template x-for="userId in (stage.resolver === 'user' ? stage.approver_ids : [])" :key="userId">
                    <input type="hidden" :name="`stages[${index}][approver_ids][]`" :value="userId">
                </template>
            </div>
        </template>

        <div class="flex items-center justify-between mt-6">
            <button type="button" @click="step = 'parameters'" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">← Назад</button>
            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сохранить маршрут</button>
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
        selected: null,

        current() {
            return this.stages.find(s => s.uid === this.selected) || null;
        },

        stagesOf(phase) {
            return this.stages.filter(s => s.phase === phase);
        },

        addStage(phase) {
            const stage = {
                uid: 'st' + Date.now(),
                name: '', phase, resolver: 'user', approver_ids: [],
                group_department_id: null, group_role: '',
                policy: phase === 'ack' ? 'any' : 'all',
                sla_days: 2,
                is_blocking: phase !== 'ack',
                on_reject: 'return_initiator',
                condition_key: '', condition_operator: '=', condition_value: '',
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
            const who = stage.resolver === 'group'
                ? 'роль · ' + (stage.policy === 'any' ? 'любой из группы' : 'все из группы')
                : stage.approver_ids.map(id => (this.allUsers.find(u => u.id === id) || {}).name).filter(Boolean).join(', ');

            const sla = stage.sla_days ? ` · ${stage.sla_days} р.д.` : '';
            return (who || 'исполнитель не назначен') + sla + (stage.is_blocking ? '' : ' · не блокирует');
        },
    };
}
</script>
