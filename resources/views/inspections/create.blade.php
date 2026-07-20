<x-app-layout>
    <x-slot name="title">Новая проверка — Vamin</x-slot>

    <div class="max-w-3xl mx-auto">
        <a href="{{ route('inspections.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← Проверки</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1 mb-6">Новая проверка</h1>

        @if($errors->any())<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('inspections.store') }}"
              x-data="{ objectType: '{{ old('object_type', '') }}' }"
              class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            @csrf

            <div>
                <label class="text-xs text-gray-500 block mb-1">Название проверки</label>
                <input name="title" value="{{ old('title') }}" required
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                       placeholder="Проверка кадровой документации за 2 квартал">
            </div>

            <div>
                <label class="text-xs text-gray-500 block mb-1">Задача / что проверить</label>
                <textarea name="body_html" rows="3"
                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('body_html') }}</textarea>
            </div>

            <div>
                <label class="text-xs text-gray-500 block mb-1">Проверяющий (исполнитель)</label>
                <select name="executor_id" required
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <option value="">— выберите —</option>
                    @foreach($executors as $u)
                        <option value="{{ $u->id }}" @selected((int) old('executor_id') === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Объект проверки</label>
                    <select name="object_type" x-model="objectType"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— не указан —</option>
                        @foreach($objectTypes as $key => $label)
                            <option value="{{ $key }}" @selected(old('object_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="objectType === 'department' || objectType === 'direction'" x-cloak>
                    <label class="text-xs text-gray-500 block mb-1">Отдел / направление</label>
                    <select name="object_id"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— выберите —</option>
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}" @selected((int) old('object_id') === $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="objectType === 'employee'" x-cloak>
                    <label class="text-xs text-gray-500 block mb-1">Сотрудник</label>
                    <select name="object_id"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— выберите —</option>
                        @foreach($employees as $u)
                            <option value="{{ $u->id }}" @selected((int) old('object_id') === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Период с</label>
                    <input type="date" name="period_from" value="{{ old('period_from') }}"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Период по</label>
                    <input type="date" name="period_to" value="{{ old('period_to') }}"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Вид</label>
                    <select name="kind"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— не указан —</option>
                        @foreach($kinds as $key => $label)
                            <option value="{{ $key }}" @selected(old('kind') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs text-gray-500 block mb-1">Срок</label>
                <input type="date" name="due_at" value="{{ old('due_at') }}"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            </div>

            <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Создать проверку</button>
        </form>
    </div>
</x-app-layout>
