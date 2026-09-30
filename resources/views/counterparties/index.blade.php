<x-app-layout>
    <x-slot name="title">Справочник контрагентов — Vamin</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Справочник контрагентов</h1>
            <p class="text-sm text-gray-500 mt-1">Введите ИНН — данные организации загрузятся из Checko.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-end gap-3 mb-5">
        {{-- Добавление / синхронизация по ИНН --}}
        <form method="POST" action="{{ route('counterparties.store') }}" class="flex items-end gap-2">
            @csrf
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">ИНН организации</label>
                <input type="text" name="inn" value="{{ old('inn') }}" inputmode="numeric" maxlength="10" placeholder="10 цифр" required
                       class="w-44 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            </div>
            <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">Загрузить</button>
        </form>
        @error('inn')
            <p class="text-xs text-red-600 self-center">{{ $message }}</p>
        @enderror

        <form method="GET" class="flex items-end gap-2 ml-auto">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по названию или ИНН"
                   class="w-64 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <button type="submit" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Найти</button>
            @if(request('search'))
                <a href="{{ route('counterparties.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
            @endif
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold">Сокр. название</th>
                        <th class="text-left px-5 py-3 font-semibold">ИНН</th>
                        <th class="text-left px-5 py-3 font-semibold">Юр. адрес</th>
                        <th class="text-left px-5 py-3 font-semibold">Руководитель</th>
                        <th class="text-left px-5 py-3 font-semibold">Контакты</th>
                        <th class="text-left px-5 py-3 font-semibold">Обновлено</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3.5 font-medium text-gray-900">{{ $item->short_name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-gray-700 whitespace-nowrap">{{ $item->inn }}</td>
                            <td class="px-5 py-3.5 text-gray-600 max-w-xs">{{ $item->legal_address ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-gray-700">{{ $item->director_name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-gray-600 text-xs space-y-0.5">
                                @foreach($item->phones ?? [] as $phone)
                                    <div>{{ $phone }}</div>
                                @endforeach
                                @foreach($item->emails ?? [] as $email)
                                    <div><a href="mailto:{{ $email }}" class="text-[#5B4FE8] hover:underline">{{ $email }}</a></div>
                                @endforeach
                                @if($item->website)
                                    <div><a href="{{ str_starts_with($item->website, 'http') ? $item->website : 'https://' . $item->website }}" target="_blank" rel="noopener" class="text-[#5B4FE8] hover:underline">{{ $item->website }}</a></div>
                                @endif
                                @if(empty($item->phones) && empty($item->emails) && ! $item->website)
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">{{ $item->synced_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <form method="POST" action="{{ route('counterparties.refresh', $item) }}">
                                    @csrf
                                    <button type="submit" class="text-[#5B4FE8] text-xs font-medium hover:underline">Обновить</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center text-gray-500">
                                {{ request('search') ? 'Ничего не найдено.' : 'Справочник пуст. Введите ИНН, чтобы добавить организацию.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $items->links() }}</div>
</x-app-layout>
