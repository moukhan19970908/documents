<x-app-layout>
    <x-slot name="title">Заявки — Vamin</x-slot>

    @include('requests.partials.nav', ['active' => 'mine', 'title' => 'Мои заявки'])

    <div x-data="myRequests()">

        {{-- Фильтры --}}
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button type="button" @click="type = 'all'"
                    :class="type === 'all' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    class="px-3 py-1.5 text-sm rounded-full border transition">Все</button>
            @foreach($typeChips as $chip)
                <button type="button" @click="type = '{{ $chip['key'] }}'"
                        :class="type === '{{ $chip['key'] }}' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                        class="px-3 py-1.5 text-sm rounded-full border transition">{{ $chip['label'] }}</button>
            @endforeach

            <span class="w-px h-5 bg-gray-200 mx-1"></span>

            <button type="button" @click="state = 'all'"
                    :class="state === 'all' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    class="px-3 py-1.5 text-sm rounded-full border transition">Все</button>
            <button type="button" @click="state = 'active'"
                    :class="state === 'active' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    class="px-3 py-1.5 text-sm rounded-full border transition">В работе</button>
            <button type="button" @click="state = 'done'"
                    :class="state === 'done' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    class="px-3 py-1.5 text-sm rounded-full border transition">Завершённые</button>

            <div class="relative ml-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                <input type="text" x-model="q" placeholder="Поиск по заявкам"
                       class="w-56 pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]/30 focus:border-[#5B4FE8]">
            </div>
        </div>

        {{-- Список --}}
        <div class="space-y-2.5">
            @forelse($myRequests as $r)
                <div x-show="match('{{ $r['type_key'] }}', '{{ $r['state_group'] }}', @js($r['title']))"
                     class="flex items-center gap-4 bg-white border border-gray-200 rounded-xl px-5 py-3.5">
                    {{-- Иконка --}}
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $r['kind'] === 'trip' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">
                        @if($r['kind'] === 'trip')
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @endif
                    </div>

                    {{-- Название + мета --}}
                    <div class="w-52 shrink-0 min-w-0">
                        <p class="font-medium text-gray-900 truncate">{{ $r['title'] }}</p>
                        <p class="text-xs text-gray-400">
                            {{ $r['submitted'] }}@if($r['stage_total'] > 0) · этап {{ $r['stage_pos'] }} из {{ $r['stage_total'] }}@endif
                        </p>
                    </div>

                    {{-- Цикл движения --}}
                    <div class="flex-1 min-w-0 overflow-x-auto">
                        <div class="flex items-start gap-1">
                            @foreach($r['steps'] as $s)
                                <div class="flex flex-col items-center shrink-0" style="min-width:3.5rem">
                                    <div @class([
                                        'w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0',
                                        'bg-green-500 text-white' => $s['state'] === 'done',
                                        'bg-blue-500 text-white ring-4 ring-blue-100' => $s['state'] === 'current' && $r['indicator'] === 'blue',
                                        'bg-orange-500 text-white ring-4 ring-orange-100' => $s['state'] === 'current' && $r['indicator'] === 'orange',
                                        'bg-red-500 text-white ring-4 ring-red-100' => $s['state'] === 'current' && $r['indicator'] === 'red',
                                        'bg-gray-400 text-white ring-4 ring-gray-100' => $s['state'] === 'current' && !in_array($r['indicator'], ['blue','orange','red']),
                                        'bg-gray-100 text-gray-400' => $s['state'] === 'pending',
                                    ])>
                                        @if($s['state'] === 'done')
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </div>
                                    <span @class([
                                        'mt-1 text-[10px] text-center leading-tight truncate max-w-[3.5rem]',
                                        'text-gray-700 font-medium' => $s['state'] !== 'pending',
                                        'text-gray-400' => $s['state'] === 'pending',
                                    ])>{{ $s['label'] }}</span>
                                </div>
                                @if(!$loop->last)
                                    <div @class([
                                        'mt-2.5 h-0.5 w-5 shrink-0',
                                        'bg-green-500' => $s['state'] === 'done',
                                        'bg-gray-200' => $s['state'] !== 'done',
                                    ])></div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Статус + Открыть --}}
                    <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $r['color'] }}">{{ $r['status'] }}</span>
                    <a href="{{ $r['url'] }}" class="shrink-0 px-3.5 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-700 hover:border-[#5B4FE8]/40 hover:text-[#5B4FE8] transition">Открыть</a>
                </div>
            @empty
                <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-12">
                    У вас пока нет заявок. Нажмите «Подать заявку» сверху.
                </div>
            @endforelse

            {{-- Пустой результат фильтра --}}
            @if($myRequests->isNotEmpty())
                <div x-show="!anyVisible()" class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-12">
                    Ничего не найдено по выбранным фильтрам.
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function myRequests() {
            return {
                type: 'all',
                state: 'all',
                q: '',
                rows: @js($myRequests->map(fn ($r) => ['t' => $r['type_key'], 's' => $r['state_group'], 'title' => $r['title']])->values()),
                match(t, s, title) {
                    return (this.type === 'all' || this.type === t)
                        && (this.state === 'all' || this.state === s)
                        && (this.q === '' || title.toLowerCase().includes(this.q.toLowerCase()));
                },
                anyVisible() {
                    return this.rows.some(r => this.match(r.t, r.s, r.title));
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
