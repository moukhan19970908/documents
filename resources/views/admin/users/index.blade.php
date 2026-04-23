<x-app-layout>
    <x-slot name="title">Пользователи — ArchManuscript</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Пользователи</h1>
        <a href="{{ route('admin.users.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Добавить пользователя
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-3 font-semibold">Пользователь</th>
                        <th class="text-left px-5 py-3 font-semibold">Роль</th>
                        <th class="text-left px-5 py-3 font-semibold">Отдел</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                        <th class="text-left px-5 py-3 font-semibold">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $user->avatar_url }}" class="w-8 h-8 rounded-full" alt="">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $user->role_label }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $user->department?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $user->is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 flex items-center gap-2">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-[#5B4FE8] text-xs font-medium hover:underline">Изменить</a>
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Удалить пользователя?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 text-xs font-medium hover:underline">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $users->links() }}</div>
        @endif
    </div>
</x-app-layout>
