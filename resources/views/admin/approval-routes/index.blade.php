<x-app-layout>
    <x-slot name="title">Маршруты согласования — Vamin</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Маршруты согласования</h1>
            <p class="text-sm text-gray-500 mt-1">Настройка цепочек согласования заявок</p>
        </div>
        <a href="{{ route('admin.approval-routes.create') }}"
           class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Добавить маршрут
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-5">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold">Название</th>
                        <th class="text-left px-5 py-3 font-semibold">Тип заявок</th>
                        <th class="text-left px-5 py-3 font-semibold">Отдел</th>
                        <th class="text-left px-5 py-3 font-semibold">Шагов</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($routes as $route)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5 font-medium text-gray-900">{{ $route->name }}</td>
                            <td class="px-5 py-3.5 text-gray-600">
                                {{ $route->request_type === 'trip' ? 'Командировка' : 'Отпуск' }}
                            </td>
                            <td class="px-5 py-3.5 text-gray-500">{{ $route->department?->name ?? 'Все отделы' }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $route->steps_count }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $route->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $route->is_active ? 'Активен' : 'Отключён' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3 justify-end">
                                    <a href="{{ route('admin.approval-routes.edit', $route) }}"
                                       class="text-[#5B4FE8] hover:text-indigo-700 text-xs font-medium">Изменить</a>
                                    <form action="{{ route('admin.approval-routes.toggle', $route) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-gray-400 hover:text-gray-700 text-xs font-medium">
                                            {{ $route->is_active ? 'Отключить' : 'Включить' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.approval-routes.destroy', $route) }}" method="POST"
                                          onsubmit="return confirm('Удалить маршрут?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600 text-xs font-medium">Удалить</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-gray-400 text-sm">
                                Маршрутов нет. <a href="{{ route('admin.approval-routes.create') }}" class="text-[#5B4FE8] hover:underline">Создать первый</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
