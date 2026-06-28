<x-app-layout>
    <x-slot name="title">Внешние участники — Vamin</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Внешние участники</h1>
            <p class="text-sm text-gray-500 mt-1">Внешние пользователи, инициирующие свои сценарии</p>
        </div>
        <a href="{{ route('admin.external-participants.create') }}"
           class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Добавить участника
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 font-semibold">ФИО</th>
                    <th class="text-left px-5 py-3 font-semibold">Email</th>
                    <th class="text-left px-5 py-3 font-semibold">Статус</th>
                    <th class="text-left px-5 py-3 font-semibold">Добавлен</th>
                    <th class="text-left px-5 py-3 font-semibold">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($participants as $p)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 font-medium text-gray-900">{{ $p->name }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $p->email }}</td>
                        <td class="px-5 py-3.5">
                            @if($p->is_active)
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-700">Активен</span>
                            @else
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-500">Деактивирован</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $p->created_at->format('d.m.Y') }}</td>
                        <td class="px-5 py-3.5">
                            @if($p->is_active)
                                <div x-data="{ confirm: false }" class="flex items-center gap-2">
                                    <template x-if="!confirm">
                                        <button type="button" @click="confirm = true" class="text-red-600 text-xs font-medium hover:underline">Деактивировать</button>
                                    </template>
                                    <template x-if="confirm">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="text-xs text-gray-500">Уверены?</span>
                                            <form action="{{ route('admin.external-participants.destroy', $p) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 text-xs font-semibold hover:underline">Да</button>
                                            </form>
                                            <button type="button" @click="confirm = false" class="text-xs text-gray-400 hover:underline">Нет</button>
                                        </span>
                                    </template>
                                </div>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-500">Внешних участников пока нет</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($participants->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $participants->links() }}</div>
        @endif
    </div>
</x-app-layout>
