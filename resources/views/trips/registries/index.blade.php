<x-app-layout>
    <x-slot name="title">Реестры командировок — Vamin</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Реестры командировок</h1>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-5">{{ session('success') }}</div>
    @endif

    @if($incoming->isNotEmpty())
        {{-- Registries pending your approval --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6" x-data="{ rejectId: null, comment: '' }">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-800">На согласование</h2>
                <p class="text-xs text-gray-400 mt-0.5">Реестры, ожидающие вашего решения</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                            <th class="text-left px-5 py-3 font-semibold">№</th>
                            <th class="text-left px-5 py-3 font-semibold">Название</th>
                            <th class="text-left px-5 py-3 font-semibold">Создал</th>
                            <th class="text-left px-5 py-3 font-semibold">Заявок</th>
                            <th class="text-left px-5 py-3 font-semibold">Сумма</th>
                            <th class="text-left px-5 py-3 font-semibold">Шаг</th>
                            <th class="px-5 py-3 font-semibold text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($incoming as $registry)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-3.5 text-gray-500 text-xs font-mono">R-{{ $registry->id }}</td>
                                <td class="px-5 py-3.5 font-medium text-gray-900">{{ $registry->title }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $registry->creator->name }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $registry->items->count() }}</td>
                                <td class="px-5 py-3.5 font-medium text-gray-800">{{ number_format($registry->total_amount, 0, '.', ' ') }} ₽</td>
                                <td class="px-5 py-3.5 text-xs text-gray-500">{{ $registry->current_step }} / {{ $registry->route?->steps->count() ?? '?' }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-1.5 justify-end">
                                        <a href="{{ route('trips.registries.show', $registry) }}"
                                           class="p-1.5 text-gray-400 hover:text-[#5B4FE8] rounded transition-colors" title="Открыть">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                        <form action="{{ route('trips.registries.approve', $registry) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="p-1.5 text-gray-400 hover:text-green-600 rounded transition-colors" title="Согласовать">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        </form>
                                        <button type="button" @click="rejectId = {{ $registry->id }}; comment = ''"
                                                class="p-1.5 text-gray-400 hover:text-red-500 rounded transition-colors" title="Отклонить">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Reject modal --}}
            <div x-show="rejectId !== null" x-transition
                 class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" style="display:none">
                <div class="bg-white rounded-xl w-full max-w-md p-6" @click.outside="rejectId = null">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Причина отклонения</h3>
                    <form :action="`/trips/registries/${rejectId}/reject`" method="POST">
                        @csrf
                        <textarea name="comment" x-model="comment" rows="3" required
                                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-400 mb-4"
                                  placeholder="Укажите причину отклонения..."></textarea>
                        <div class="flex gap-2 justify-end">
                            <button type="button" @click="rejectId = null"
                                    class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Отмена</button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Отклонить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if(auth()->user()->isManager() && $availableTrips->isNotEmpty())
        {{-- Create registry form --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6" x-data="{ selected: [], open: false }">
            <h2 class="text-sm font-semibold text-gray-800 mb-4">Создать реестр</h2>
            <form action="{{ route('trips.registries.store') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название реестра *</label>
                    <input type="text" name="title"
                           value="{{ old('title', 'Реестр командировок №R-' . date('ym')) }}"
                           required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                <div class="mb-4">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-2">Выберите заявки *</label>
                    <div class="border border-gray-200 rounded-xl divide-y divide-gray-100 max-h-64 overflow-y-auto">
                        @foreach($availableTrips as $trip)
                            <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="trip_ids[]" value="{{ $trip->id }}"
                                       x-model="selected"
                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900">{{ $trip->user->name }} — {{ $trip->city }}</div>
                                    <div class="text-xs text-gray-400">{{ $trip->date_start->format('d.m.Y') }} — {{ $trip->date_end->format('d.m.Y') }}</div>
                                </div>
                                <div class="text-sm font-semibold text-gray-700 whitespace-nowrap">
                                    {{ number_format($trip->total_amount, 0, '.', ' ') }} ₽
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="mb-4">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Комментарий</label>
                    <textarea name="comment" rows="2"
                              class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                </div>
                <button type="submit" :disabled="selected.length === 0"
                        :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-indigo-700'"
                        class="px-5 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium transition-colors">
                    Создать реестр (<span x-text="selected.length"></span>)
                </button>
            </form>
        </div>
    @endif

    {{-- Registries list --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Мои реестры</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold">№</th>
                        <th class="text-left px-5 py-3 font-semibold">Название</th>
                        <th class="text-left px-5 py-3 font-semibold">Создал</th>
                        <th class="text-left px-5 py-3 font-semibold">Заявок</th>
                        <th class="text-left px-5 py-3 font-semibold">Сумма</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($registries as $registry)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5 text-gray-500 text-xs font-mono">R-{{ $registry->id }}</td>
                            <td class="px-5 py-3.5 font-medium text-gray-900">{{ $registry->title }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $registry->creator->name }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $registry->items->count() }}</td>
                            <td class="px-5 py-3.5 font-medium text-gray-800">{{ number_format($registry->total_amount, 0, '.', ' ') }} ₽</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $registry->status_color }}">
                                    {{ $registry->status_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('trips.registries.show', $registry) }}" class="text-[#5B4FE8] hover:text-indigo-700 text-xs font-medium">Открыть</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400 text-sm">Реестров нет</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($registries->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">{{ $registries->links() }}</div>
        @endif
    </div>

    {{-- Выбывшие (ТЗ 18.4): заявки, выведенные из моих реестров на доработку --}}
    @if(isset($droppedItems) && $droppedItems->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mt-6">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-800">Выбывшие</h2>
                <p class="text-xs text-gray-400 mt-0.5">Выведены из реестра на доработку — вернутся в пул после исправления</p>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($droppedItems as $item)
                    <div class="px-5 py-3.5 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $item->tripRequest?->user->name }} — {{ $item->tripRequest?->city }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Реестр «{{ $item->registry?->title }}» · вывел: {{ $item->dropper?->name ?? '—' }}@if($item->dropped_at) · {{ $item->dropped_at->format('d.m.Y') }}@endif
                            </p>
                            @if($item->drop_comment)<p class="text-xs text-gray-600 mt-0.5">{{ $item->drop_comment }}</p>@endif
                        </div>
                        <span class="shrink-0 text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">На доработке</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-app-layout>
