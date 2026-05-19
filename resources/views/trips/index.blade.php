<x-app-layout>
    <x-slot name="title">Командировки — Vamin</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Командировки</h1>
            <p class="text-sm text-gray-500 mt-1">Мои заявки на командировку</p>
        </div>
        <a href="{{ route('trips.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новая заявка
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="text-xs text-gray-500 font-medium block mb-1">Поиск</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Город или цель..."
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>
        <div class="w-44">
            <label class="text-xs text-gray-500 font-medium block mb-1">Статус</label>
            <select name="status" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                <option value="">Все статусы</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Черновик</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>На согласовании</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Согласовано</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Отклонено</option>
                <option value="revision" {{ request('status') === 'revision' ? 'selected' : '' }}>На доработке</option>
                <option value="in_registry" {{ request('status') === 'in_registry' ? 'selected' : '' }}>В реестре</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">Найти</button>
        @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('trips.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold">№</th>
                        <th class="text-left px-5 py-3 font-semibold">Сотрудник</th>
                        <th class="text-left px-5 py-3 font-semibold">Город / Цель</th>
                        <th class="text-left px-5 py-3 font-semibold">Даты</th>
                        <th class="text-left px-5 py-3 font-semibold">Сумма</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                        <th class="text-left px-5 py-3 font-semibold">Обновлён</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($trips as $trip)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5 text-gray-500 text-xs font-mono">T-{{ $trip->id }}</td>
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-gray-900 text-sm">{{ $trip->user->name }}</div>
                                <div class="text-xs text-gray-400">{{ $trip->user->department?->name }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-gray-800">{{ $trip->city }}</div>
                                <div class="text-xs text-gray-400 line-clamp-1">{{ $trip->purpose }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 text-xs whitespace-nowrap">
                                {{ $trip->date_start->format('d.m.Y') }} — {{ $trip->date_end->format('d.m.Y') }}
                                <div class="text-gray-400">{{ $trip->days_count }} дн.</div>
                            </td>
                            <td class="px-5 py-3.5 font-medium text-gray-800 whitespace-nowrap">
                                {{ number_format($trip->total_amount, 0, '.', ' ') }} ₽
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $trip->status_color }}">
                                    {{ $trip->status_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-400">{{ $trip->updated_at->diffForHumans() }}</td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('trips.show', $trip) }}" class="text-[#5B4FE8] hover:text-indigo-700 text-xs font-medium">Открыть</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-gray-400 text-sm">
                                Заявок нет
                                <div class="mt-2">
                                    <a href="{{ route('trips.create') }}" class="text-[#5B4FE8] hover:underline">Создать первую заявку</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($trips->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">
                {{ $trips->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
