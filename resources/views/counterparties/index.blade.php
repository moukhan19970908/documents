<x-app-layout>
    <x-slot name="title">Справочник контрагентов — Vamin</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Справочник контрагентов</h1>
            <p class="text-sm text-gray-500 mt-1">Введите ИНН организации (10 цифр) или ИП (12 цифр) — данные загрузятся из Checko. Иностранную компанию добавьте вручную.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @php
        // Ручная форма работает в двух режимах: добавление и правка (?edit=id). Скрытое поле _form
        // отличает её ошибки от ошибок формы «по ИНН» — у обеих есть поле inn.
        $manualMode = $editing ? 'edit' : 'manual';
        $manualOld  = old('_form') === $manualMode;
    @endphp
    <div x-data="{ manual: @js($editing !== null || $manualOld) }">
    <div class="flex flex-wrap items-end gap-3 mb-5">
        {{-- Добавление / синхронизация по ИНН --}}
        <form method="POST" action="{{ route('counterparties.store') }}" class="flex items-end gap-2">
            @csrf
            <div>
                <label for="inn" class="text-xs text-gray-500 font-medium block mb-1">ИНН организации или ИП</label>
                <input type="text" id="inn" name="inn" value="{{ old('_form') === null ? old('inn') : '' }}" inputmode="numeric" maxlength="12" placeholder="10 или 12 цифр" required
                       class="w-44 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            </div>
            <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">Загрузить</button>
        </form>
        @if($editing)
            <a href="{{ route('counterparties.index') }}"
               class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50">Добавить вручную</a>
        @else
            <button type="button" @click="manual = !manual"
                    class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50">Добавить вручную</button>
        @endif
        @if(old('_form') === null)
            @error('inn')
                <p class="text-xs text-red-600 self-center">{{ $message }}</p>
            @enderror
        @endif

        <form method="GET" class="flex items-end gap-2 ml-auto">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по названию или ИНН"
                   class="w-64 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <button type="submit" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Найти</button>
            @if(request('search'))
                <a href="{{ route('counterparties.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
            @endif
        </form>
    </div>

    {{-- Ручная карточка: иностранная компания или ИНН, которого нет в Checko; та же форма — для правки --}}
    @php
        $field = 'w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]';
        // После ошибки — то, что ввёл пользователь; при правке — данные карточки; иначе пусто.
        $value = fn (string $key, $current = null) => $manualOld ? old($key) : $current;
    @endphp
    <form id="manual-form" x-show="manual" x-cloak method="POST"
          action="{{ $editing ? route('counterparties.update', $editing) : route('counterparties.manual') }}"
          class="bg-white border {{ $editing ? 'border-[#5B4FE8]/40' : 'border-gray-200' }} rounded-xl p-5 mb-5 space-y-3">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="_form" value="{{ $manualMode }}">
        <div>
            <h2 class="font-semibold text-gray-900">{{ $editing ? 'Изменить контрагента' : 'Новый контрагент вручную' }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ $editing ? 'Карточка внесена вручную — исправьте опечатку или дополните данные.' : 'Для компаний, которых нет в Checko, — например, иностранных. ИНН необязателен.' }}
            </p>
        </div>
        @if($manualOld && $errors->any())
            <p class="text-sm text-red-600">{{ $errors->first() }}</p>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
                <label for="m_short_name" class="text-xs text-gray-500 font-medium block mb-1">Наименование *</label>
                <input type="text" id="m_short_name" name="short_name" value="{{ $value('short_name', $editing?->short_name) }}" required maxlength="255" placeholder="Например, Agro Trade LLP" class="{{ $field }}">
            </div>
            <div>
                <label for="m_inn" class="text-xs text-gray-500 font-medium block mb-1">ИНН</label>
                <input type="text" id="m_inn" name="inn" value="{{ $value('inn', $editing?->inn) }}" inputmode="numeric" maxlength="12" placeholder="Если есть: 10 или 12 цифр" class="{{ $field }}">
            </div>
            <div class="md:col-span-2">
                <label for="m_address" class="text-xs text-gray-500 font-medium block mb-1">Адрес</label>
                <input type="text" id="m_address" name="legal_address" value="{{ $value('legal_address', $editing?->legal_address) }}" placeholder="Страна, город, адрес" class="{{ $field }}">
            </div>
            <div>
                <label for="m_director" class="text-xs text-gray-500 font-medium block mb-1">Руководитель</label>
                <input type="text" id="m_director" name="director_name" value="{{ $value('director_name', $editing?->director_name) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="m_phone" class="text-xs text-gray-500 font-medium block mb-1">Телефон</label>
                <input type="text" id="m_phone" name="phone" value="{{ $value('phone', $editing?->phones[0] ?? null) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="m_email" class="text-xs text-gray-500 font-medium block mb-1">Email</label>
                <input type="email" id="m_email" name="email" value="{{ $value('email', $editing?->emails[0] ?? null) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="m_website" class="text-xs text-gray-500 font-medium block mb-1">Сайт</label>
                <input type="text" id="m_website" name="website" value="{{ $value('website', $editing?->website) }}" class="{{ $field }}">
            </div>
        </div>
        <div class="flex justify-end gap-2">
            @if($editing)
                <a href="{{ route('counterparties.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
            @else
                <button type="button" @click="manual = false" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
            @endif
            <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">{{ $editing ? 'Сохранить' : 'Добавить' }}</button>
        </div>
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
                            <td class="px-5 py-3.5 text-gray-700 whitespace-nowrap">{{ $item->inn ?? '—' }}</td>
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
                            <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">{{ $item->synced_at?->format('d.m.Y H:i') ?? 'внесён вручную' }}</td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-y-1">
                                @unless($item->synced_at)
                                    <a href="{{ request()->fullUrlWithQuery(['edit' => $item->id]) }}#manual-form"
                                       class="block text-[#5B4FE8] text-xs font-medium hover:underline">Изменить</a>
                                @endunless
                                @if($item->inn)
                                    <form method="POST" action="{{ route('counterparties.refresh', $item) }}">
                                        @csrf
                                        <button type="submit" class="text-[#5B4FE8] text-xs font-medium hover:underline">{{ $item->synced_at ? 'Обновить' : 'Загрузить из Checko' }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center text-gray-500">
                                {{ request('search') ? 'Ничего не найдено.' : 'Справочник пуст. Введите ИНН или добавьте контрагента вручную.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $items->links() }}</div>
</x-app-layout>
