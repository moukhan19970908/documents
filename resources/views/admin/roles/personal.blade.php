<x-app-layout>
    <x-slot name="title">Персональные права — Vamin</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Роли и доступы</h1>
    </div>

    @include('admin.roles.partials.tabs')

    <div x-data="{ modal: false }" class="max-w-5xl">

        <div class="flex items-center gap-3 bg-indigo-50/60 border border-indigo-100 rounded-xl px-4 py-3 mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-gray-600">
                Точечные исключения из ролевой матрицы для конкретного сотрудника. Используйте только для нетиповых случаев.
            </p>
        </div>

        <button type="button" @click="modal = true"
                class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Персональное право
        </button>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        @foreach(['Сотрудник', 'Право', 'Область', 'Выдал', 'Действует до'] as $col)
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-widest px-5 py-3">{{ $col }}</th>
                        @endforeach
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grants as $grant)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <img src="{{ $grant['user']->avatar_url }}" alt="" class="w-8 h-8 rounded-full shrink-0">
                                    <span class="text-sm font-semibold text-gray-900">{{ $grant['user']->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700">{{ $grant['permission'] }}</td>
                            <td class="px-5 py-3">
                                <span class="text-sm text-[#5B4FE8]">{{ $grant['scope'] }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500">{{ $grant['granted_by']->name }}</td>
                            <td class="px-5 py-3">
                                @if($grant['until'])
                                    <span class="text-sm font-mono text-gray-600">{{ $grant['until'] }}</span>
                                @else
                                    <span class="text-sm text-gray-400">бессрочно</span>
                                @endif
                            </td>
                            <td class="px-3">
                                <button type="button" class="w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-50 hover:text-red-500 flex items-center justify-center"
                                        title="Отозвать право">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-gray-500">Персональные права не выданы</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-400 mt-4">
            Верстка: права не сохраняются, строки собраны как заглушка.
        </p>

        {{-- Модалка нового персонального права --}}
        <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/40" @click="modal = false"></div>

            <div class="relative bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-5">Персональное право</h2>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Сотрудник</label>
                        <select class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Право</label>
                        <select class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($permissions as $permission)
                                <option value="{{ $permission['key'] }}">{{ $permission['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Область</label>
                        <input type="text" placeholder="Отдел дистрибуции"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Действует до</label>
                        <input type="date"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <p class="text-xs text-gray-400 mt-1">Пусто — право выдаётся бессрочно.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-6">
                    <button type="button" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Выдать право</button>
                    <button type="button" @click="modal = false" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
