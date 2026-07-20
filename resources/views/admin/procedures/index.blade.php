<x-app-layout>
    <x-slot name="title">Шаблоны процедур — Vamin</x-slot>

    <div class="max-w-5xl mx-auto" x-data="{ creating: false }">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Шаблоны процедур</h1>
                <p class="text-sm text-gray-500 mt-1">Сценарии с этапами, развилками и гибридным чек-листом</p>
            </div>
            <button @click="creating = !creating"
                    class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                + Новый шаблон
            </button>
        </div>

        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
        @endif

        <div x-show="creating" x-cloak class="mb-6 bg-white rounded-xl border border-gray-200 p-5">
            <form method="POST" action="{{ route('admin.procedures.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Название сценария</label>
                    <input name="name" value="{{ old('name') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                           placeholder="Приём нового сотрудника">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Описание</label>
                    <textarea name="description" rows="2"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('description') }}</textarea>
                </div>
                <div class="flex gap-2">
                    <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Создать</button>
                    <button type="button" @click="creating = false" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase tracking-wider bg-gray-50">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold">Сценарий</th>
                        <th class="text-left px-5 py-3 font-semibold">Этапов</th>
                        <th class="text-left px-5 py-3 font-semibold">Пунктов чек-листа</th>
                        <th class="text-left px-5 py-3 font-semibold">Процедур</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($templates as $t)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.procedures.edit', $t) }}" class="font-medium text-gray-900 hover:text-[#5B4FE8]">{{ $t->name }}</a>
                                @if($t->description)<div class="text-xs text-gray-400 mt-0.5">{{ \Illuminate\Support\Str::limit($t->description, 80) }}</div>@endif
                            </td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $t->stages_count }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $t->checklist_items_count }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $t->procedures_count }}</td>
                            <td class="px-5 py-3.5">
                                @if($t->is_active)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Активен</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Выключен</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.procedures.edit', $t) }}" class="text-[#5B4FE8] text-sm font-medium hover:underline">Настроить</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">Шаблонов пока нет.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
