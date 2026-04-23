<x-app-layout>
    <x-slot name="title">Отделы — Vamin</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Отделы</h1>
        <a href="{{ route('admin.departments.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новый отдел
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-100">
                    <th class="text-left px-5 py-3 font-semibold">Название</th>
                    <th class="text-left px-5 py-3 font-semibold">Родительский отдел</th>
                    <th class="text-left px-5 py-3 font-semibold">Руководитель</th>
                    <th class="text-left px-5 py-3 font-semibold">Сотрудников</th>
                    <th class="text-left px-5 py-3 font-semibold">Действия</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($departments as $dept)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3.5 font-medium text-gray-900">{{ $dept->name }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $dept->parent?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $dept->head?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $dept->users_count }}</td>
                        <td class="px-5 py-3.5 flex items-center gap-2">
                            <a href="{{ route('admin.departments.edit', $dept) }}" class="text-[#5B4FE8] text-xs font-medium hover:underline">Изменить</a>
                            <form action="{{ route('admin.departments.destroy', $dept) }}" method="POST" onsubmit="return confirm('Удалить?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 text-xs font-medium hover:underline">Удалить</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
