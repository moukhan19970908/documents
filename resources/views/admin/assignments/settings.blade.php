<x-app-layout>
    <x-slot name="title">Правила поручений — Vamin</x-slot>

    <div class="max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('assignments.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← Поручения</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Правила поручений</h1>
            <p class="text-sm text-gray-500 mt-1">Настройка постановки, дерева и приёмки (ТЗ 17.1). Это правила, а не маршрут.</p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.assignments.settings.update') }}" class="space-y-4">
            @csrf @method('PUT')

            {{-- Область постановки --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="font-semibold text-gray-900 mb-1">Область постановки</h2>
                <p class="text-xs text-gray-400 mb-4">ГД, руководитель аппарата и админ ставят на любых сотрудников всегда. Настройка — для локальных руководителей.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Корневое поручение (руководитель)</label>
                        <select name="manager_scope" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($scopes as $v => $label)
                                <option value="{{ $v }}" @selected($settings->manager_scope === $v)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Подпоручение (область шире)</label>
                        <select name="sub_scope" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($scopes as $v => $label)
                                <option value="{{ $v }}" @selected($settings->sub_scope === $v)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Дерево --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Дерево</h2>

                <label class="flex items-center gap-3 mb-3">
                    <input type="checkbox" name="allow_subassignments" value="1" @checked($settings->allow_subassignments) class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                    <span class="text-sm text-gray-700">Разрешить подпоручения</span>
                </label>

                <div class="mb-3">
                    <label class="text-xs text-gray-500 block mb-1">Максимальная глубина дерева (уровней)</label>
                    <input type="number" name="max_depth" value="{{ $settings->max_depth }}" min="1" max="20"
                           class="w-40 text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <p class="text-xs text-gray-400 mt-1">Защита от бесконечной вложенности.</p>
                </div>

                <label class="flex items-center gap-3 mb-2">
                    <input type="checkbox" name="aggregate_up" value="1" @checked($settings->aggregate_up) class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                    <span class="text-sm text-gray-700">Агрегация результатов вверх (файлы подтягиваются в родителя при приёмке)</span>
                </label>

                <div class="flex items-center gap-2 text-sm text-gray-400 mt-3 pt-3 border-t border-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Приёмка на каждом узле — включена всегда (обязательное требование).
                </div>
            </div>

            {{-- Роли на узле --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Участники узла</h2>
                <label class="flex items-center gap-3 mb-3">
                    <input type="checkbox" name="coexecutors_enabled" value="1" @checked($settings->coexecutors_enabled) class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                    <span class="text-sm text-gray-700">Соисполнители <span class="text-xs text-amber-500">(механика — следующий этап)</span></span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="controller_enabled" value="1" @checked($settings->controller_enabled) class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                    <span class="text-sm text-gray-700">Контролёр <span class="text-xs text-amber-500">(механика — следующий этап)</span></span>
                </label>
            </div>

            {{-- Срок --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="font-semibold text-gray-900 mb-1">Продление срока</h2>
                <p class="text-xs text-gray-400 mb-3">Исполнитель меняет срок с обязательным комментарием.</p>
                <select name="deadline_extension" class="w-full md:w-80 text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @foreach($deadlines as $v => $label)
                        <option value="{{ $v }}" @selected($settings->deadline_extension === $v)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Классификатор и бланк --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Классификатор и бланк</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Тип документа [ПОР]</label>
                        <select name="document_type_id" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option value="">— не выбрано —</option>
                            @foreach($types as $t)
                                <option value="{{ $t->id }}" @selected($settings->document_type_id == $t->id)>{{ $t->name }}@if($t->code) ({{ $t->code }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Шаблон бланка поручения</label>
                        <select name="blank_template_id" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option value="">— без бланка —</option>
                            @foreach($blanks as $b)
                                <option value="{{ $b->id }}" @selected($settings->blank_template_id == $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Подставляется в тело при создании поручения.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сохранить правила</button>
            </div>
        </form>
    </div>
</x-app-layout>
