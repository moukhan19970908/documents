<x-app-layout>
    <x-slot name="title">Шаблоны бланков — Vamin</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Шаблоны бланков</h1>
            <p class="text-sm text-gray-500 mt-1">Готовое тело документа: сотрудник открывает бланк, поля уже подставлены.</p>
        </div>
        <a href="{{ route('admin.blank-templates.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новый бланк
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif

    {{-- Фильтры --}}
    @php $hasFilters = $search !== '' || $typeId || $subtypeId || $authorId || in_array($status, ['active', 'inactive'], true); @endphp
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
        <div class="relative flex-1 min-w-56">
            <label class="text-xs text-gray-500 font-medium block mb-1">Поиск</label>
            <svg class="absolute left-3 top-[2.15rem] w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="search" value="{{ $search }}" placeholder="Название, описание…"
                   class="w-full text-sm border border-gray-200 rounded-lg pl-10 pr-4 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>

        <div class="w-52">
            <label class="text-xs text-gray-500 font-medium block mb-1">Тип документа</label>
            <select name="type" onchange="this.form.querySelector('[name=subtype]')?.remove(); this.form.submit()"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Все типы</option>
                @foreach($types as $t)
                    <option value="{{ $t->id }}" {{ $typeId === $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        @if($typeId && $subtypes->isNotEmpty())
            <div class="w-52">
                <label class="text-xs text-gray-500 font-medium block mb-1">Подтип</label>
                <select name="subtype" onchange="this.form.submit()"
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <option value="">Все подтипы</option>
                    @foreach($subtypes as $s)
                        <option value="{{ $s->id }}" {{ $subtypeId === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="w-52">
            <label class="text-xs text-gray-500 font-medium block mb-1">Автор</label>
            <select name="author" onchange="this.form.submit()"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Все авторы</option>
                @foreach($authors as $a)
                    <option value="{{ $a->id }}" {{ $authorId === $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-44">
            <label class="text-xs text-gray-500 font-medium block mb-1">Статус</label>
            <select name="status" onchange="this.form.submit()"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Все</option>
                <option value="active"   {{ $status === 'active' ? 'selected' : '' }}>Активные</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Неактивные</option>
            </select>
        </div>

        <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Найти</button>
        @if($hasFilters)
            <a href="{{ route('admin.blank-templates.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
        @endif
    </form>

    <div class="space-y-3">
        @forelse($templates as $template)
            <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-[#5B4FE8] flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-semibold text-gray-900">{{ $template->name }}</h2>
                            <span class="flex items-center gap-1.5 text-xs font-medium {{ $template->is_active ? 'text-emerald-600' : 'text-gray-400' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $template->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                {{ $template->is_active ? 'Активен' : 'Неактивен' }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ $template->type?->name ?? 'тип удалён' }}
                            @if($template->subtype) · {{ $template->subtype->name }} @endif
                            @if($template->author) · {{ $template->author->name }} @endif
                        </p>
                        @if($template->description)
                            <p class="text-xs text-gray-500 mt-1">{{ $template->description }}</p>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 shrink-0">
                    <form action="{{ route('admin.blank-templates.toggle', $template) }}" method="POST">
                        @csrf @method('PATCH')
                        <button class="text-xs font-medium text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50">
                            {{ $template->is_active ? 'Отключить' : 'Включить' }}
                        </button>
                    </form>
                    <a href="{{ route('admin.blank-templates.edit', $template) }}" class="text-xs font-medium text-[#5B4FE8] border border-[#5B4FE8] px-3 py-1.5 rounded-lg hover:bg-indigo-50">Изменить</a>
                    <form action="{{ route('admin.blank-templates.destroy', $template) }}" method="POST" onsubmit="return confirm('Удалить шаблон бланка?')">
                        @csrf @method('DELETE')
                        <button class="text-xs font-medium text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50">Удалить</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
                @if($hasFilters)
                    <p class="text-gray-500">Ничего не найдено по заданным фильтрам</p>
                    <p class="text-sm text-gray-400 mt-1">Измените параметры фильтра или <a href="{{ route('admin.blank-templates.index') }}" class="text-[#5B4FE8] hover:underline">сбросьте их</a>.</p>
                @else
                    <p class="text-gray-500">Шаблонов бланков пока нет</p>
                    <p class="text-sm text-gray-400 mt-1">Создайте бланк — и сотрудник получит готовое тело документа вместо пустого файла.</p>
                @endif
            </div>
        @endforelse
    </div>
</x-app-layout>
