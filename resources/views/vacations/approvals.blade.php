<x-app-layout>
    <x-slot name="title">На согласование — Отпуска</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">На согласование</h1>
            <p class="text-sm text-gray-500 mt-1">Заявки на отпуск, ожидающие вашего решения</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-5">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ rejectId: null, revisionId: null, comment: '' }">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold">№</th>
                        <th class="text-left px-5 py-3 font-semibold">Сотрудник</th>
                        <th class="text-left px-5 py-3 font-semibold">Вид отпуска</th>
                        <th class="text-left px-5 py-3 font-semibold">Период</th>
                        <th class="text-left px-5 py-3 font-semibold">Дней</th>
                        <th class="text-left px-5 py-3 font-semibold">Шаг</th>
                        <th class="px-5 py-3 font-semibold">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($vacations as $vacation)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5 text-gray-500 text-xs font-mono">V-{{ $vacation->id }}</td>
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-gray-900">{{ $vacation->user->name }}</div>
                                <div class="text-xs text-gray-400">{{ $vacation->user->department?->name }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-700">{{ $vacation->vacation_type_label }}</td>
                            <td class="px-5 py-3.5 text-xs text-gray-600 whitespace-nowrap">
                                {{ $vacation->date_start->format('d.m.Y') }} — {{ $vacation->date_end->format('d.m.Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-gray-700">{{ $vacation->days_count }}</td>
                            <td class="px-5 py-3.5 text-xs text-gray-500">
                                {{ $vacation->current_step }} / {{ $vacation->route?->steps->count() ?? '?' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-1.5 justify-end">
                                    <a href="{{ route('vacations.show', $vacation) }}"
                                       class="p-1.5 text-gray-400 hover:text-[#5B4FE8] rounded transition-colors" title="Открыть">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    <form action="{{ route('vacations.approve', $vacation) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-green-600 rounded transition-colors" title="Согласовать">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </form>
                                    <button type="button" @click="rejectId = {{ $vacation->id }}; comment = ''"
                                            class="p-1.5 text-gray-400 hover:text-red-500 rounded transition-colors" title="Отклонить">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                    <button type="button" @click="revisionId = {{ $vacation->id }}; comment = ''"
                                            class="p-1.5 text-gray-400 hover:text-orange-500 rounded transition-colors" title="На доработку">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-400 text-sm">
                                Нет заявок на согласование
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="rejectId !== null" x-transition
             class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" style="display:none">
            <div class="bg-white rounded-xl w-full max-w-md p-6" @click.outside="rejectId = null">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Причина отклонения</h3>
                <form :action="`/vacations/${rejectId}/reject`" method="POST">
                    @csrf
                    <textarea name="comment" x-model="comment" rows="3" required
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-400 mb-4"
                              placeholder="Укажите причину отклонения..."></textarea>
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="rejectId = null"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Отмена</button>
                        <button type="submit" class="px-4 py-2 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Отклонить</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="revisionId !== null" x-transition
             class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" style="display:none">
            <div class="bg-white rounded-xl w-full max-w-md p-6" @click.outside="revisionId = null">
                <h3 class="text-base font-semibold text-gray-900 mb-3">На доработку</h3>
                <form :action="`/vacations/${revisionId}/revision`" method="POST">
                    @csrf
                    <textarea name="comment" x-model="comment" rows="3" required
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-400 mb-4"
                              placeholder="Что нужно исправить..."></textarea>
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="revisionId = null"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Отмена</button>
                        <button type="submit" class="px-4 py-2 text-sm bg-orange-500 text-white rounded-lg hover:bg-orange-600">На доработку</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
