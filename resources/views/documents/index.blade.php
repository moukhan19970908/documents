<x-app-layout>
    @php $listRoute = $processMeta['index_route'] ?? 'documents.index'; @endphp
    <x-slot name="title">{{ $processMeta['label'] ?? 'Документы' }} — Vamin</x-slot>

    <div class="flex items-center justify-between mb-6" x-data="customScenario">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $processMeta['label'] ?? 'Документы' }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $processMeta ? 'Документы процесса «' . $processMeta['label'] . '»' : 'Все документы рабочего пространства' }}</p>
        </div>
        @if($processMeta)
            <a href="{{ route($processMeta['create_route']) }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Создать
            </a>
        @elseif(auth()->user()->isExternal())
            {{-- Внешние участники не попадают на страницу создания — им остаётся модалка ад-хок. --}}
            <button type="button" @click="open = true" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Свой сценарий
            </button>
            @include('documents.partials.custom-scenario-modal')
        @else
            {{-- «Свой сценарий» открывает полноценный конструктор маршрута (как «Создать документ»). --}}
            <a href="{{ route('documents.create', ['own' => 1]) }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Свой сценарий
            </a>
        @endif
    </div>

    @php
        // Ссылка на этот же список с изменёнными параметрами; null убирает параметр.
        $listUrl = fn (array $overrides) => route($listRoute, array_filter(
            array_merge(request()->except('page'), $overrides),
            fn ($value) => $value !== null && $value !== '',
        ));

        $isTeamScope = in_array($scope, ['department', 'all'], true);
        $filterKeys  = ['search', 'type', 'subtype', 'status', 'direction', 'period', 'author'];
        $hasFilters  = request()->hasAny($filterKeys) || ($isTeamScope && $quick);

        $statusOptions = [
            'draft'            => 'Черновик',
            'in_review'        => 'На одобрении',
            'requires_changes' => 'Требует изменений',
            'approved'         => 'Одобрено',
            'archived'         => 'Архив',
        ];

        $dayWord = fn (int $n) => ($n % 10 === 1 && $n % 100 !== 11) ? 'день'
            : (($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20)) ? 'дня' : 'дней');

        $kindBadge = [
            'soglas'    => 'bg-indigo-50 text-[#5B4FE8]',
            'zakl'      => 'bg-sky-50 text-sky-700',
            'oznak'     => 'bg-emerald-50 text-emerald-700',
            'priem'     => 'bg-amber-50 text-amber-700',
            'dorabotka' => 'bg-orange-50 text-orange-700',
        ];

        $pill       = 'inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-medium border transition-colors';
        $pillIdle   = 'bg-white border-gray-200 text-gray-700 hover:border-gray-300';
        $pillActive = 'bg-[#5B4FE8] border-[#5B4FE8] text-white';
        $pillAlert  = 'bg-red-50 border-red-200 text-red-700 hover:border-red-300';
        $field      = 'w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]';
        $label      = 'text-[11px] text-gray-500 font-semibold uppercase tracking-wider block mb-1';
    @endphp

    {{-- Вкладки: отношение к документу. «Мой отдел» и «Все» — только у тех, кому положено по роли. --}}
    <nav class="flex gap-6 border-b border-gray-200 mb-5 overflow-x-auto">
        @foreach($scopes as $key => $tabLabel)
            <a href="{{ route($listRoute, ['scope' => $key]) }}"
               class="flex items-center gap-1.5 pb-3 -mb-px border-b-2 text-sm font-medium whitespace-nowrap transition-colors {{ $scope === $key ? 'border-[#5B4FE8] text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $tabLabel }}
                @if($key === 'all')
                    <span class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">админ</span>
                @elseif(($counts[$key] ?? 0) > 0)
                    <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full {{ $key === 'waiting' ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-500' }}">{{ $counts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    {{-- Быстрые фильтры вкладки --}}
    @if($scope === 'waiting')
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <a href="{{ $listUrl(['quick' => null]) }}" class="{{ $pill }} {{ $quick === null ? $pillActive : $pillIdle }}">
                Все <span class="{{ $quick === null ? 'text-white/80' : 'text-gray-400' }}">{{ $counts['waiting'] }}</span>
            </a>
            @foreach($taskKinds as $kind => $kindLabel)
                @if($kind !== 'dorabotka' || ($quickCounts[$kind] ?? 0) > 0)
                    <a href="{{ $listUrl(['quick' => $kind]) }}" class="{{ $pill }} {{ $quick === $kind ? $pillActive : $pillIdle }}">
                        {{ $kindLabel }} <span class="{{ $quick === $kind ? 'text-white/80' : 'text-gray-400' }}">{{ $quickCounts[$kind] ?? 0 }}</span>
                    </a>
                @endif
            @endforeach
            @if(($quickCounts['overdue'] ?? 0) > 0)
                <span class="w-px h-6 bg-gray-200 mx-1"></span>
                <a href="{{ $listUrl(['quick' => 'overdue']) }}" class="{{ $pill }} {{ $quick === 'overdue' ? $pillActive : $pillAlert }}">
                    Просрочено <span>{{ $quickCounts['overdue'] }}</span>
                </a>
            @endif
        </div>
    @elseif($scope === 'department')
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <div class="inline-flex rounded-full border border-gray-200 bg-white p-0.5">
                <a href="{{ $listUrl(['sub' => null, 'author' => null]) }}"
                   class="px-3.5 py-1.5 rounded-full text-sm font-medium transition-colors {{ $withSubdepartments ? 'text-gray-700 hover:text-gray-900' : 'bg-[#5B4FE8] text-white' }}">Только мой отдел</a>
                <a href="{{ $listUrl(['sub' => 1, 'author' => null]) }}"
                   class="px-3.5 py-1.5 rounded-full text-sm font-medium transition-colors {{ $withSubdepartments ? 'bg-[#5B4FE8] text-white' : 'text-gray-700 hover:text-gray-900' }}">С подотделами</a>
            </div>
            <select name="author" form="doc-filters" aria-label="Сотрудник" onchange="this.form.requestSubmit()"
                    class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">{{ $withSubdepartments ? 'Все сотрудники' : 'Все сотрудники отдела' }}</option>
                @foreach($departmentEmployees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) request('author') === $employee->id)>
                        {{ $employee->name }}{{ $employee->is_active ? '' : ' (уволен)' }}
                    </option>
                @endforeach
            </select>
            <span class="w-px h-6 bg-gray-200 mx-1"></span>
            <a href="{{ $listUrl(['quick' => $quick === 'overdue' ? null : 'overdue']) }}" class="{{ $pill }} {{ $quick === 'overdue' ? $pillActive : $pillAlert }}">
                Просрочено в отделе <span>{{ $quickCounts['overdue'] }}</span>
            </a>
            <a href="{{ $listUrl(['quick' => $quick === 'fired' ? null : 'fired']) }}" class="{{ $pill }} {{ $quick === 'fired' ? $pillActive : $pillIdle }}">
                Висят у уволенных <span class="{{ $quick === 'fired' ? 'text-white/80' : 'text-gray-400' }}">{{ $quickCounts['fired'] }}</span>
            </a>
        </div>
    @endif

    {{-- Фильтры. Применяются сразу при выборе; поиск — по Enter.
         Подтип каскадом от типа: виден, только если у выбранного типа есть подтипы. --}}
    @php $subtypeTypes = $documentTypes->filter(fn ($t) => $t->subtypes->isNotEmpty()); @endphp
    <form id="doc-filters" method="GET"
          x-data="{ type: @js((string) request('type', '')), subtype: @js((string) request('subtype', '')), withSubtypes: @js($subtypeTypes->pluck('slug')->values()) }"
          class="bg-gray-50 rounded-xl border border-gray-200 p-4 mb-3 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="scope" value="{{ $scope }}">
        @if($quick)
            <input type="hidden" name="quick" value="{{ $quick }}">
        @endif
        @if($withSubdepartments)
            <input type="hidden" name="sub" value="1">
        @endif
        <div class="flex-1 min-w-56">
            <label for="filter-search" class="{{ $label }}">Поиск</label>
            <input type="search" id="filter-search" name="search" value="{{ request('search') }}" placeholder="Название, номер, контрагент" class="{{ $field }}">
        </div>
        <div class="w-40">
            <label for="filter-type" class="{{ $label }}">Тип</label>
            <select id="filter-type" name="type" x-model="type" @change="subtype = ''; $nextTick(() => $el.form.requestSubmit())" class="{{ $field }}">
                <option value="">Все типы</option>
                @foreach($documentTypes as $type)
                    <option value="{{ $type->slug }}" @selected(request('type') === $type->slug)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        @if($subtypeTypes->isNotEmpty())
            <div class="w-40" x-show="withSubtypes.includes(type)" x-cloak>
                <label for="filter-subtype" class="{{ $label }}">Подтип</label>
                <select id="filter-subtype" name="subtype" x-model="subtype" :disabled="!withSubtypes.includes(type)"
                        @change="$el.form.requestSubmit()" class="{{ $field }}">
                    <option value="">Все подтипы</option>
                    @foreach($subtypeTypes as $subtypeType)
                        @foreach($subtypeType->subtypes as $sub)
                            {{-- x-show прячет чужие подтипы, :disabled страхует на случай браузера,
                                 который display:none у <option> игнорирует. --}}
                            <option value="{{ $sub->id }}"
                                    x-show="type === @js($subtypeType->slug)"
                                    :disabled="type !== @js($subtypeType->slug)"
                                    @selected((int) request('subtype') === $sub->id)>{{ $sub->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
        @endif
        <div class="w-40">
            <label for="filter-status" class="{{ $label }}">Статус</label>
            <select id="filter-status" name="status" onchange="this.form.requestSubmit()" class="{{ $field }}">
                <option value="">Все статусы</option>
                @foreach($statusOptions as $value => $statusLabel)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </div>
        @if($isTeamScope && $directions->isNotEmpty())
            <div class="w-44">
                <label for="filter-direction" class="{{ $label }}">Направление</label>
                <select id="filter-direction" name="direction" onchange="this.form.requestSubmit()" class="{{ $field }}">
                    <option value="">Все направления</option>
                    @foreach($directions as $dir)
                        <option value="{{ $dir->id }}" @selected((int) request('direction') === $dir->id)>{{ $dir->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="w-40">
            <label for="filter-period" class="{{ $label }}">Период</label>
            <select id="filter-period" name="period" onchange="this.form.requestSubmit()" class="{{ $field }}">
                <option value="">За всё время</option>
                @foreach($periods as $value => $periodLabel)
                    <option value="{{ $value }}" @selected(request('period') === $value)>{{ $periodLabel }}</option>
                @endforeach
            </select>
        </div>
        @if(!$isTeamScope && $hasFilters)
            <a href="{{ route($listRoute, ['scope' => $scope]) }}" class="py-2 text-sm text-gray-500 underline hover:text-gray-700">Сбросить</a>
        @endif
    </form>

    {{-- Активные фильтры командных вкладок: видно, что сужает список, и любой снимается крестиком. --}}
    @if($isTeamScope)
        @php
            $activeChips = collect([
                'search'    => request('search') ? '«' . request('search') . '»' : null,
                'type'      => $documentTypes->firstWhere('slug', request('type'))?->name,
                'subtype'   => $documentTypes->flatMap->subtypes->firstWhere('id', (int) request('subtype'))?->name,
                'status'    => $statusOptions[request('status')] ?? null,
                'direction' => $directions->firstWhere('id', (int) request('direction'))?->name,
                'period'    => $periods[request('period')] ?? null,
                'author'    => $departmentEmployees->firstWhere('id', (int) request('author'))?->name,
                'quick'     => ['overdue' => 'Просрочено', 'fired' => 'Висят у уволенных'][$quick] ?? null,
            ])->filter();
        @endphp
        <div class="flex flex-wrap items-center gap-2 mb-4 min-h-8"
             x-data="{
                 key: @js('documents-filter:' . $listRoute),
                 saved: null,
                 init() { try { this.saved = localStorage.getItem(this.key) } catch (e) {} },
                 get isSaved() { return this.saved === location.search },
                 toggle() {
                     try {
                         this.isSaved ? localStorage.removeItem(this.key) : localStorage.setItem(this.key, location.search);
                         this.saved = localStorage.getItem(this.key);
                     } catch (e) {}
                 },
             }">
            @if($activeChips->isNotEmpty())
                <span class="text-xs text-gray-500">Активно:</span>
                @foreach($activeChips as $param => $chipLabel)
                    <a href="{{ $listUrl([$param => null] + ($param === 'type' ? ['subtype' => null] : [])) }}"
                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-gray-200 bg-white text-xs text-gray-700 hover:border-gray-300"
                       aria-label="Убрать фильтр {{ $chipLabel }}">
                        {{ $chipLabel }} <span class="text-gray-400" aria-hidden="true">&times;</span>
                    </a>
                @endforeach
                <a href="{{ route($listRoute, ['scope' => $scope]) }}" class="text-xs text-gray-500 underline hover:text-gray-700">Сбросить всё</a>
            @endif
            <button type="button" @click="toggle()"
                    class="ml-auto px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-[#5B4FE8] border border-indigo-100 hover:bg-indigo-100"
                    x-text="isSaved ? 'Убрать мой фильтр' : 'Сохранить как мой фильтр'">Сохранить как мой фильтр</button>
        </div>
    @endif

    {{-- Таблица. Свои вкладки отвечают «что от меня нужно», командные — «где документ застрял». --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold">Документ</th>
                        @if($scope === 'waiting')
                            <th class="text-left px-5 py-3 font-semibold">Моя роль</th>
                        @endif
                        <th class="text-left px-5 py-3 font-semibold">Инициатор</th>
                        @if($isTeamScope)
                            <th class="text-left px-5 py-3 font-semibold">Отдел</th>
                        @endif
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                        @if($scope === 'waiting')
                            <th class="text-left px-5 py-3 font-semibold">Срок</th>
                        @else
                            <th class="text-left px-5 py-3 font-semibold">Ждёт кого</th>
                            <th class="text-left px-5 py-3 font-semibold">В работе</th>
                        @endif
                        <th class="px-5 py-3"><span class="sr-only">Действия</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($documents as $doc)
                        @php
                            $hasOverdue = $doc->pendingTasks->contains(fn ($t) => $t->isOverdue());
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('documents.show', $doc) }}" class="font-medium text-gray-900 hover:text-[#5B4FE8]">{{ $doc->title }}</a>
                                <span class="text-xs text-gray-400 ml-1">{{ $doc->number ? '№ ' . $doc->number : 'D-' . $doc->id }}</span>
                            </td>

                            @if($scope === 'waiting')
                                @php $kind = $myTasks[$doc->id]['kind'] ?? null; @endphp
                                <td class="px-5 py-3.5">
                                    @if($kind)
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $kindBadge[$kind] }}">{{ $taskKinds[$kind] }}</span>
                                    @endif
                                </td>
                            @endif

                            <td class="px-5 py-3.5">
                                @if($doc->initiator)
                                    <div class="flex items-center gap-2">
                                        <img src="{{ $doc->initiator->avatar_url }}" class="w-6 h-6 rounded-full flex-shrink-0" alt="">
                                        <span class="text-gray-700">{{ Str::limit($doc->initiator->name, 24) }}</span>
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            @if($isTeamScope)
                                <td class="px-5 py-3.5 text-gray-600">{{ $doc->initiator?->department?->name ?? '—' }}</td>
                            @endif

                            <td class="px-5 py-3.5">
                                @include('documents.partials.status-cell', ['doc' => $doc])
                            </td>

                            @if($scope === 'waiting')
                                @php
                                    $deadline  = $myTasks[$doc->id]['deadline'] ?? null;
                                    $isOverdue = $myTasks[$doc->id]['is_overdue'] ?? false;
                                    $daysLeft  = $deadline ? (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false) : null;
                                @endphp
                                <td class="px-5 py-3.5 {{ $isOverdue ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                    @if($daysLeft === null)
                                        —
                                    @elseif($daysLeft < -1)
                                        {{ abs($daysLeft) }} {{ $dayWord(abs($daysLeft)) }} назад
                                    @elseif($daysLeft === -1)
                                        вчера
                                    @elseif($daysLeft === 0)
                                        сегодня
                                    @elseif($daysLeft === 1)
                                        завтра
                                    @else
                                        {{ $daysLeft }} {{ $dayWord($daysLeft) }}
                                    @endif
                                </td>
                            @else
                                @php $waitingFor = $doc->pendingTasks->pluck('assignee')->filter()->unique('id')->values(); @endphp
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        @forelse($waitingFor->take(2) as $assignee)
                                            <div class="flex items-center gap-1.5" title="{{ $assignee->name }}">
                                                <img src="{{ $assignee->avatar_url }}" class="w-6 h-6 rounded-full flex-shrink-0 {{ $assignee->is_active ? '' : 'grayscale opacity-60' }}" alt="">
                                                @if($assignee->is_active)
                                                    <span class="text-gray-700">{{ Str::limit($assignee->name, 20) }}</span>
                                                @else
                                                    <span class="text-red-600 font-medium">уволен ⚠</span>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="text-gray-400">—</span>
                                        @endforelse
                                        @if($waitingFor->count() > 2)
                                            <span class="text-xs text-gray-400">+{{ $waitingFor->count() - 2 }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if(in_array($doc->status, ['in_review', 'requires_changes'], true))
                                        @php $inWork = (int) ($doc->latestApproval?->created_at ?? $doc->created_at)->copy()->startOfDay()->diffInDays(now()->startOfDay()); @endphp
                                        <span class="{{ $hasOverdue ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $inWork }} {{ $dayWord($inWork) }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endif

                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                @can('delete', $doc)
                                    <div class="inline-flex items-center gap-2" x-data="{ confirm: false }">
                                        <button type="button" x-show="!confirm" @click="confirm = true" class="text-xs text-gray-400 hover:text-red-600">Удалить</button>
                                        <span x-show="confirm" x-cloak class="inline-flex items-center gap-2">
                                            <span class="text-xs text-gray-500">Удалить?</span>
                                            <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 text-xs font-semibold hover:underline">Да</button>
                                            </form>
                                            <button type="button" @click="confirm = false" class="text-xs text-gray-400 hover:underline">Нет</button>
                                        </span>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-500">
                                {{ $scope === 'waiting' && !$hasFilters && !$quick ? 'Сейчас ничего не ждёт вашего решения' : 'Документы не найдены' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $documents->links() }}
            </div>
        @endif
    </div>

    {{-- «Мой фильтр»: при входе в раздел без параметров открываем сохранённый вид. --}}
    @if(!request()->query())
        @push('scripts')
        <script>
            try {
                const saved = localStorage.getItem(@js('documents-filter:' . $listRoute));
                if (saved) location.replace(saved);
            } catch (e) {}
        </script>
        @endpush
    @endif
</x-app-layout>
