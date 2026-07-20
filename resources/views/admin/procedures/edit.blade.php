<x-app-layout>
    <x-slot name="title">{{ $template->name }} — Шаблон процедуры</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('admin.procedures.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← Все шаблоны</a>
                <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $template->name }}</h1>
            </div>
            <form method="POST" action="{{ route('admin.procedures.destroy', $template) }}"
                  onsubmit="return confirm('Удалить шаблон целиком?')">
                @csrf @method('DELETE')
                <button class="px-3 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50">Удалить шаблон</button>
            </form>
        </div>

        @if(session('success'))<div class="px-4 py-3 rounded-lg bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif

        {{-- Основные поля --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Основное</h2>
            <form method="POST" action="{{ route('admin.procedures.update', $template) }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Название</label>
                    <input name="name" value="{{ old('name', $template->name) }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Описание</label>
                    <textarea name="description" rows="2"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('description', $template->description) }}</textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" @checked($template->is_active)
                           class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                    Шаблон активен (доступен для запуска)
                </label>
                <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сохранить</button>
            </form>
        </div>

        {{-- Этапы сценария --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{ addStage: false, editStage: null }">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold text-gray-900">Этапы сценария</h2>
                    <p class="text-xs text-gray-400">Проходятся по порядку. Развилка с негативным вердиктом останавливает процедуру.</p>
                </div>
                <button @click="addStage = !addStage" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">+ Этап</button>
            </div>

            <div class="space-y-2">
                @forelse($template->stages as $stage)
                    <div class="border border-gray-100 rounded-lg">
                        <div class="flex items-center gap-3 px-4 py-3">
                            <span class="w-6 h-6 shrink-0 rounded-full bg-gray-100 text-gray-500 text-xs flex items-center justify-center font-semibold">{{ $loop->iteration }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">{{ $stage->title }}</div>
                                <div class="text-xs text-gray-400">
                                    {{ $stage->typeLabel() }}
                                    @unless($stage->isAuto())
                                        · @switch($stage->executor_mode)
                                            @case('initiator') Инициатор @break
                                            @case('user') {{ $stage->executorUser?->name ?? '—' }} @break
                                            @case('role') Роль: {{ $stage->executor_role }} @break
                                        @endswitch
                                        @if($stage->require_attachments) · 📎 вложения обязательны @endif
                                    @endunless
                                </div>
                            </div>
                            <button @click="editStage = (editStage === {{ $stage->id }} ? null : {{ $stage->id }})" class="text-xs text-[#5B4FE8] hover:underline">Изменить</button>
                            <form method="POST" action="{{ route('admin.procedures.stages.destroy', [$template, $stage]) }}" onsubmit="return confirm('Удалить этап?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">Удалить</button>
                            </form>
                        </div>
                        <div x-show="editStage === {{ $stage->id }}" x-cloak class="px-4 pb-4 border-t border-gray-100 pt-3">
                            @include('admin.procedures._stage-form', [
                                'action' => route('admin.procedures.stages.update', [$template, $stage]),
                                'method' => 'PUT', 'stage' => $stage, 'submitLabel' => 'Сохранить этап', 'onCancel' => 'editStage = null',
                            ])
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-2">Этапов пока нет. Добавьте первый — обычно это «Форма (инициатор)».</p>
                @endforelse
            </div>

            <div x-show="addStage" x-cloak class="mt-4 border-t border-gray-100 pt-4">
                @include('admin.procedures._stage-form', [
                    'action' => route('admin.procedures.stages.store', $template),
                    'method' => 'POST', 'stage' => null, 'submitLabel' => 'Добавить этап', 'onCancel' => 'addStage = false',
                ])
            </div>
        </div>

        {{-- Чек-лист (ЧАСТЬ 1) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{ addItem: false, editItem: null }">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-semibold text-gray-900">Чек-лист · пресетные пункты (ЧАСТЬ 1)</h2>
                    <p class="text-xs text-gray-400">Инициатор при запуске добавит свои произвольные пункты (ЧАСТЬ 2).</p>
                </div>
                <button @click="addItem = !addItem" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">+ Пункт</button>
            </div>

            <div class="space-y-2">
                @forelse($template->checklistItems as $item)
                    <div class="border border-gray-100 rounded-lg">
                        <div class="flex items-center gap-3 px-4 py-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    @if($item->department)<span class="text-gray-400">{{ $item->department }}:</span> @endif{{ $item->title }}
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ $item->fieldTypeLabel() }}
                                    · @switch($item->executor_mode)
                                        @case('user') {{ $item->executorUser?->name ?? '—' }} @break
                                        @case('department_head') Руководитель отдела @break
                                        @case('initiator') Инициатор @break
                                    @endswitch
                                    @if($item->spawns_task) · ⚡ задача @endif
                                </div>
                            </div>
                            <button @click="editItem = (editItem === {{ $item->id }} ? null : {{ $item->id }})" class="text-xs text-[#5B4FE8] hover:underline">Изменить</button>
                            <form method="POST" action="{{ route('admin.procedures.items.destroy', [$template, $item]) }}" onsubmit="return confirm('Удалить пункт?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">Удалить</button>
                            </form>
                        </div>
                        <div x-show="editItem === {{ $item->id }}" x-cloak class="px-4 pb-4 border-t border-gray-100 pt-3">
                            @include('admin.procedures._item-form', [
                                'action' => route('admin.procedures.items.update', [$template, $item]),
                                'method' => 'PUT', 'item' => $item, 'submitLabel' => 'Сохранить пункт', 'onCancel' => 'editItem = null',
                            ])
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-2">Пресетных пунктов нет.</p>
                @endforelse
            </div>

            <div x-show="addItem" x-cloak class="mt-4 border-t border-gray-100 pt-4">
                @include('admin.procedures._item-form', [
                    'action' => route('admin.procedures.items.store', $template),
                    'method' => 'POST', 'item' => null, 'submitLabel' => 'Добавить пункт', 'onCancel' => 'addItem = false',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
