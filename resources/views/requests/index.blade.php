<x-app-layout>
    <x-slot name="title">Заявки — Vamin</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Заявки</h1>
        <p class="text-sm text-gray-500 mt-1">Отпуска, командировки и прочие заявки в одном месте.</p>
    </div>

    {{-- Быстрое создание --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <a href="{{ route('vacations.create') }}" class="group bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md hover:border-[#5B4FE8]/40 transition">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900">Отпуск</h3>
            <p class="text-sm text-gray-500">Ежегодный, за свой счёт, больничный, иное</p>
        </a>

        <a href="{{ route('trips.create') }}" class="group bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md hover:border-[#5B4FE8]/40 transition">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900">Командировка</h3>
            <p class="text-sm text-gray-500">Транспорт, проживание, регион</p>
        </a>

        <div class="bg-gray-50 border border-dashed border-gray-200 rounded-xl p-5 opacity-70">
            <div class="w-10 h-10 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            </div>
            <h3 class="font-semibold text-gray-500">Иное</h3>
            <p class="text-sm text-gray-400">Появится на следующем этапе</p>
        </div>
    </div>

    {{-- На согласование --}}
    @php $pendingTotal = $pending['trip'] + $pending['vacation'] + $pending['trip_reg'] + $pending['vacation_reg']; @endphp
    @if($pendingTotal > 0)
        <div class="mb-8">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-widest mb-3">Требует моего согласования</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @if($pending['vacation'] > 0)
                    <a href="{{ route('vacations.approvals') }}" class="flex items-center justify-between bg-white border border-gray-200 rounded-xl px-5 py-4 hover:border-[#5B4FE8]/40">
                        <span class="text-sm font-medium text-gray-800">Отпуска — заявки</span>
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 text-xs font-bold rounded-full bg-[#5B4FE8] text-white">{{ $pending['vacation'] }}</span>
                    </a>
                @endif
                @if($pending['vacation_reg'] > 0)
                    <a href="{{ route('vacations.registries.index') }}" class="flex items-center justify-between bg-white border border-gray-200 rounded-xl px-5 py-4 hover:border-[#5B4FE8]/40">
                        <span class="text-sm font-medium text-gray-800">Отпуска — реестры</span>
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 text-xs font-bold rounded-full bg-[#5B4FE8] text-white">{{ $pending['vacation_reg'] }}</span>
                    </a>
                @endif
                @if($pending['trip'] > 0)
                    <a href="{{ route('trips.approvals') }}" class="flex items-center justify-between bg-white border border-gray-200 rounded-xl px-5 py-4 hover:border-[#5B4FE8]/40">
                        <span class="text-sm font-medium text-gray-800">Командировки — заявки</span>
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 text-xs font-bold rounded-full bg-[#5B4FE8] text-white">{{ $pending['trip'] }}</span>
                    </a>
                @endif
                @if($pending['trip_reg'] > 0)
                    <a href="{{ route('trips.registries.index') }}" class="flex items-center justify-between bg-white border border-gray-200 rounded-xl px-5 py-4 hover:border-[#5B4FE8]/40">
                        <span class="text-sm font-medium text-gray-800">Командировки — реестры</span>
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 text-xs font-bold rounded-full bg-[#5B4FE8] text-white">{{ $pending['trip_reg'] }}</span>
                    </a>
                @endif
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Мои заявки --}}
        <div class="lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-widest">Мои заявки</h2>
                <span class="text-xs text-gray-400">{{ $counts['active'] }} в работе · {{ $counts['total'] }} всего</span>
            </div>
            <div class="space-y-2">
                @forelse($myRequests as $r)
                    <a href="{{ $r['url'] }}" class="flex items-center gap-4 bg-white border border-gray-200 rounded-xl px-5 py-3.5 hover:border-[#5B4FE8]/40 transition">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $r['kind'] === 'trip' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">
                            @if($r['kind'] === 'trip')
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ $r['title'] }}</p>
                            <p class="text-xs text-gray-500">{{ $r['dates'] }}</p>
                        </div>
                        <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $r['color'] }}">{{ $r['status'] }}</span>
                    </a>
                @empty
                    <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-12">
                        У вас пока нет заявок. Создайте первую сверху.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Реестры --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-widest">Мои реестры</h2>
            </div>
            <div class="space-y-2">
                @forelse($myRegistries as $reg)
                    <a href="{{ $reg->type === 'trip' ? route('trips.registries.show', $reg) : route('vacations.registries.show', $reg) }}"
                       class="block bg-white border border-gray-200 rounded-xl px-4 py-3 hover:border-[#5B4FE8]/40 transition">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-800 truncate">{{ $reg->title ?: 'Реестр #' . $reg->id }}</span>
                            <span class="shrink-0 text-xs px-2 py-0.5 rounded-full {{ $reg->status_color }}">{{ $reg->status_label }}</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $reg->type === 'trip' ? 'Командировки' : 'Отпуска' }}</p>
                    </a>
                @empty
                    <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-8 text-sm">
                        Реестров нет.
                    </div>
                @endforelse
            </div>
            <div class="mt-3 flex flex-col gap-1.5 text-sm">
                <a href="{{ route('vacations.registries.index') }}" class="text-[#5B4FE8] hover:underline">Реестры отпусков →</a>
                <a href="{{ route('trips.registries.index') }}" class="text-[#5B4FE8] hover:underline">Реестры командировок →</a>
                <a href="{{ route('trips.tasks.index') }}" class="text-[#5B4FE8] hover:underline">Задания командировок →</a>
            </div>
        </div>
    </div>
</x-app-layout>
