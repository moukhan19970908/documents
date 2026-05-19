<x-app-layout>
    <x-slot name="title">Отпуска — Vamin</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Отпуска</h1>
            <p class="text-sm text-gray-500 mt-1">Мои заявки на отпуск</p>
        </div>
        <a href="{{ route('vacations.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новая заявка
        </a>
    </div>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
        <div class="w-44">
            <label class="text-xs text-gray-500 font-medium block mb-1">Статус</label>
            <select name="status" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                <option value="">Все статусы</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Черновик</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>На согласовании</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Согласовано</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Отклонено</option>
                <option value="revision" {{ request('status') === 'revision' ? 'selected' : '' }}>На доработке</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">Найти</button>
        @if(request('status'))
            <a href="{{ route('vacations.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold">№</th>
                        <th class="text-left px-5 py-3 font-semibold">Сотрудник</th>
                        <th class="text-left px-5 py-3 font-semibold">Вид отпуска</th>
                        <th class="text-left px-5 py-3 font-semibold">Даты</th>
                        <th class="text-left px-5 py-3 font-semibold">Дней</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                        <th class="text-left px-5 py-3 font-semibold">Обновлён</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($vacations as $vacation)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5 text-gray-500 text-xs font-mono">V-{{ $vacation->id }}</td>
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-gray-900 text-sm">{{ $vacation->user->name }}</div>
                                <div class="text-xs text-gray-400">{{ $vacation->user->department?->name }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-700">{{ $vacation->vacation_type_label }}</td>
                            <td class="px-5 py-3.5 text-gray-600 text-xs whitespace-nowrap">
                                {{ $vacation->date_start->format('d.m.Y') }} — {{ $vacation->date_end->format('d.m.Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-gray-700">{{ $vacation->days_count }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $vacation->status_color }}">
                                    {{ $vacation->status_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-400">{{ $vacation->updated_at->diffForHumans() }}</td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('vacations.show', $vacation) }}" class="text-[#5B4FE8] hover:text-indigo-700 text-xs font-medium">Открыть</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-gray-400 text-sm">
                                Заявок нет
                                <div class="mt-2">
                                    <a href="{{ route('vacations.create') }}" class="text-[#5B4FE8] hover:underline">Создать первую заявку</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vacations->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">{{ $vacations->links() }}</div>
        @endif
    </div>
</x-app-layout>
