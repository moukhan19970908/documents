<x-app-layout>
    <x-slot name="title">Виды заявок — Vamin</x-slot>

    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Виды заявок</h1>
            <p class="text-sm text-gray-500 mt-1">Настройка заявок: маршруты согласования, исполнители заданий, поля, реестр и замещение.</p>
        </div>
    </div>

    <div class="rounded-xl bg-[#5B4FE8]/5 border border-[#5B4FE8]/15 text-[#5B4FE8] text-sm px-4 py-3 mb-6">
        Отпуск и командировка — процессы бесшовного стека. Маршрут согласования подбирается по отделу и цепочке руководителей;
        задания порождаются по условиям заявки. Изменения маршрута применяются к новым заявкам.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        @foreach($types as $t)
            @php
                $c = ['emerald' => 'bg-emerald-50 text-emerald-600', 'blue' => 'bg-blue-50 text-blue-600'][$t['color']] ?? 'bg-gray-100 text-gray-500';
            @endphp
            <div class="bg-white border border-gray-200 rounded-2xl p-6">
                {{-- Шапка карточки --}}
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 {{ $c }}">
                        @if($t['key'] === 'trip')
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-gray-900">{{ $t['name'] }}</h2>
                        <p class="text-xs text-gray-500">{{ $t['desc'] }}</p>
                    </div>
                </div>

                {{-- Подвиды (отпуск) --}}
                @if(!empty($t['subtypes']))
                    <div class="mb-4">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-1.5">Подвиды</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($t['subtypes'] as $st)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $st }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Поля формы --}}
                <div class="mb-4">
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-1.5">Поля формы</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($t['fields'] as $f)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-50 border border-gray-200 text-gray-600">{{ $f }}</span>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">Поля этого вида фиксированы кодом.</p>
                </div>

                {{-- Маршруты согласования --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest">Маршруты согласования · {{ $t['routes']->count() }}</p>
                        <a href="{{ route('admin.request-types.edit', $t['key']) }}" class="text-xs text-[#5B4FE8] hover:underline">В конструкторе →</a>
                    </div>
                    @forelse($t['routes'] as $r)
                        <div class="flex items-center justify-between gap-2 text-sm py-1.5 border-b border-gray-50 last:border-0">
                            <span class="text-gray-700 truncate">{{ $r['dept'] }}</span>
                            <span class="text-xs text-gray-400 shrink-0">
                                {{ $r['level'] }} · {{ $r['steps'] }} шаг(ов)
                                @if($r['active'])<span class="text-emerald-600">● активен</span>@else<span class="text-gray-300">○ выкл</span>@endif
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Маршруты не настроены — заявки этого вида нельзя будет отправить.</p>
                    @endforelse
                </div>

                {{-- Реестр: свой маршрут --}}
                @if($t['registry'])
                    <div class="mb-4">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-1">Реестр</p>
                        <p class="text-xs text-gray-500">
                            Маршрутов реестра: {{ $t['reg_routes']->count() }}.
                            @if($t['reg_routes']->isEmpty())<span class="text-amber-600">не настроен — реестр нельзя отправить.</span>@endif
                        </p>
                    </div>
                @endif

                {{-- Задания-исполнители (командировка) --}}
                @if($t['executors'])
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest">Задания — исполнители</p>
                            <a href="{{ route('admin.trip-tasks.settings') }}" class="text-xs text-[#5B4FE8] hover:underline">Настроить →</a>
                        </div>
                        <div class="space-y-1">
                            @foreach($t['executors'] as $ex)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500 text-xs">{{ $ex['role'] }}</span>
                                    <span class="{{ $ex['name'] ? 'text-gray-800 font-medium' : 'text-amber-600' }}">{{ $ex['name'] ?? 'не назначен' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Флаги --}}
                <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                    <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700">Реестр включён</span>
                    <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700">Замещение включено</span>
                </div>

                <a href="{{ route('admin.request-types.edit', $t['key']) }}"
                   class="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v18m6-18v18M3 9h18M3 15h18"/></svg>
                    Открыть в конструкторе
                </a>
            </div>
        @endforeach

        {{-- Иное — заглушка --}}
        <div class="bg-gray-50 border border-dashed border-gray-200 rounded-2xl p-6 flex flex-col items-center justify-center text-center min-h-[220px]">
            <div class="w-11 h-11 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
            </div>
            <h2 class="text-base font-semibold text-gray-500">Иное</h2>
            <p class="text-xs text-gray-400 mt-1 max-w-xs">Отдельный вид заявки на бесшовном стеке появится следующим шагом — как продумаем его форму и маршрут.</p>
        </div>
    </div>
</x-app-layout>
