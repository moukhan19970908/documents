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
</x-app-layout>
