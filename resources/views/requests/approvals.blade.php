<x-app-layout>
    <x-slot name="title">На согласовании — Vamin</x-slot>

    @include('requests.partials.nav', ['active' => 'approve', 'title' => 'На согласовании'])

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">{{ session('error') }}</div>
    @endif

    <div x-data="{ sub: 'individual' }">

        {{-- Под-вкладки --}}
        <div class="inline-flex items-center gap-1 bg-gray-100 rounded-xl p-1 mb-5">
            <button type="button" @click="sub = 'individual'"
                    :class="sub === 'individual' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                    class="px-4 py-1.5 text-sm font-medium rounded-lg transition">
                Индивидуальные заявки <span class="text-xs text-gray-400">{{ $individual->count() }}</span>
            </button>
            <button type="button" @click="sub = 'pool'"
                    :class="sub === 'pool' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                    class="px-4 py-1.5 text-sm font-medium rounded-lg transition">
                Пул для реестра <span class="text-xs text-gray-400">{{ $poolByDept->sum(fn ($g) => $g['items']->count()) }}</span>
            </button>
            <button type="button" @click="sub = 'registries'"
                    :class="sub === 'registries' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                    class="px-4 py-1.5 text-sm font-medium rounded-lg transition">
                Реестры в пути <span class="text-xs text-gray-400">{{ $inTransit['incoming']->count() + $inTransit['mine']->count() }}</span>
            </button>
        </div>

        {{-- ── Индивидуальные заявки ──────────────────────────────────────── --}}
        <div x-show="sub === 'individual'" class="space-y-2.5">
            @forelse($individual as $row)
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4" x-data="{ rejecting: false }">
                    <div class="flex items-center gap-4">
                        <div class="w-9 h-9 rounded-full bg-[#5B4FE8]/10 text-[#5B4FE8] flex items-center justify-center text-sm font-bold shrink-0">
                            {{ mb_substr($row['initiator'], 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate">
                                {{ $row['initiator'] }}
                                @if($row['position'])<span class="text-gray-400 font-normal">· {{ $row['position'] }}</span>@endif
                            </p>
                            <p class="text-xs text-gray-500">{{ $row['summary'] }}</p>
                        </div>
                        <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $row['kind'] === 'trip' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">{{ $row['type_label'] }}</span>

                        <div class="flex items-center gap-2 shrink-0">
                            <form action="{{ $row['approve_url'] }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3.5 py-1.5 bg-emerald-500 text-white rounded-lg text-sm font-medium hover:bg-emerald-600 transition">Согласовать</button>
                            </form>
                            <button type="button" @click="rejecting = !rejecting"
                                    class="px-3.5 py-1.5 border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition">Отклонить</button>
                            <a href="{{ $row['show_url'] }}" class="px-3.5 py-1.5 border border-gray-200 text-gray-700 rounded-lg text-sm hover:border-[#5B4FE8]/40 hover:text-[#5B4FE8] transition">Посмотреть</a>
                        </div>
                    </div>

                    {{-- Панель отклонения с обязательным комментарием --}}
                    <form x-show="rejecting" x-cloak x-transition action="{{ $row['reject_url'] }}" method="POST" class="mt-3 flex items-start gap-2">
                        @csrf
                        <textarea name="comment" rows="2" required placeholder="Причина отклонения (обязательно)…"
                                  class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-300"></textarea>
                        <button type="submit" class="px-3.5 py-2 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 transition shrink-0">Отклонить</button>
                    </form>
                </div>
            @empty
                <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-12">
                    Нет заявок, ожидающих вашего решения.
                </div>
            @endforelse
        </div>

        {{-- ── Пул для реестра ────────────────────────────────────────────── --}}
        <div x-show="sub === 'pool'" x-cloak>
            @php $poolTotal = $poolByDept->sum(fn ($g) => $g['items']->count()); @endphp
            @if($poolTotal === 0)
                <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-12">
                    Пул пуст — согласованных заявок линейных сотрудников для реестра нет.
                </div>
            @else
                <form action="{{ route('requests.registries.store') }}" method="POST"
                      x-data="{ count: 0, recount() { this.count = [...$el.querySelectorAll('.pool-cb')].filter(c => c.checked).length; } }">
                    @csrf
                    <div class="rounded-xl bg-[#5B4FE8]/5 border border-[#5B4FE8]/15 text-[#5B4FE8] text-sm px-4 py-3 mb-4">
                        Заявки линейных сотрудников группируются в реестры. Выберите заявки и сформируйте реестр для отправки следующему руководителю.
                    </div>

                    <div class="space-y-5">
                        @foreach($poolByDept as $group)
                            <div>
                                <label class="flex items-center gap-2 mb-2 cursor-pointer">
                                    <input type="checkbox"
                                           @change="[...$root.querySelectorAll('.pool-cb[data-dept=&quot;{{ $loop->index }}&quot;]')].forEach(c => c.checked = $event.target.checked); recount()"
                                           class="w-4 h-4 rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-widest">{{ $group['dept'] }}</span>
                                    <span class="text-xs text-gray-400">{{ $group['items']->count() }}</span>
                                </label>

                                <div class="space-y-2">
                                    @foreach($group['items'] as $item)
                                        <label class="flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-4 py-3 cursor-pointer hover:border-[#5B4FE8]/40 transition">
                                            <input type="checkbox" class="pool-cb w-4 h-4 rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"
                                                   data-dept="{{ $loop->parent->index }}"
                                                   name="{{ $item['kind'] === 'trip' ? 'trip_ids' : 'vacation_ids' }}[]"
                                                   value="{{ $item['id'] }}" @change="recount()">
                                            <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-xs font-bold shrink-0">
                                                {{ mb_substr($item['name'], 0, 1) }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                    {{ $item['name'] }}
                                                    <span class="ml-1 text-xs px-2 py-0.5 rounded-full {{ $item['kind'] === 'trip' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">{{ $item['type_label'] }}</span>
                                                </p>
                                                <p class="text-xs text-gray-400">{{ $item['summary'] }} · {{ $item['submitted'] }}</p>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="sticky bottom-0 mt-5 -mx-1 px-1 py-3 bg-gradient-to-t from-white via-white to-transparent flex items-center justify-end gap-3">
                        <span class="text-sm text-gray-500">Выбрано: <span x-text="count"></span> из {{ $poolTotal }}</span>
                        <button type="submit" :disabled="count === 0"
                                :class="count === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-indigo-700'"
                                class="px-4 py-2 bg-[#5B4FE8] text-white rounded-xl text-sm font-semibold transition">
                            Сформировать реестр (<span x-text="count"></span>)
                        </button>
                    </div>
                </form>
            @endif
        </div>

        {{-- ── Реестры в пути ─────────────────────────────────────────────── --}}
        <div x-show="sub === 'registries'" x-cloak class="space-y-6">
            @if($inTransit['incoming']->isNotEmpty())
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Ждут вашего решения</h3>
                    <div class="space-y-2">
                        @foreach($inTransit['incoming'] as $reg)
                            @include('requests.partials.registry-row', ['reg' => $reg])
                        @endforeach
                    </div>
                </div>
            @endif

            @if($inTransit['mine']->isNotEmpty())
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Мои реестры в пути</h3>
                    <div class="space-y-2">
                        @foreach($inTransit['mine'] as $reg)
                            @include('requests.partials.registry-row', ['reg' => $reg])
                        @endforeach
                    </div>
                </div>
            @endif

            @if($inTransit['incoming']->isEmpty() && $inTransit['mine']->isEmpty())
                <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-12">
                    Реестров в пути нет.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
