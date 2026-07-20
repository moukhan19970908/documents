{{-- Действие: гибридный чек-лист (ЧАСТЬ 1 — пресеты, ЧАСТЬ 2 — произвольные пункты инициатора) --}}
<form method="POST" action="{{ route('procedures.checklist', $procedure) }}"
      x-data="{ custom: [] }" class="space-y-5">
    @csrf

    {{-- ЧАСТЬ 1 — пресетные пункты --}}
    @if($procedure->template->checklistItems->isNotEmpty())
        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Пункты сценария</h3>
            @foreach($procedure->template->checklistItems as $item)
                <div class="border border-gray-100 rounded-lg px-4 py-3">
                    <div class="text-sm text-gray-900 mb-2">
                        @if($item->department)<span class="text-gray-400">{{ $item->department }}:</span> @endif{{ $item->title }}
                    </div>
                    @switch($item->field_type)
                        @case('checkbox')
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="presets[{{ $item->id }}][answer]" value="1"
                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"> Отметить
                            </label>
                            @break
                        @case('boolean')
                            <div class="flex gap-4 text-sm">
                                <label class="flex items-center gap-1.5"><input type="radio" name="presets[{{ $item->id }}][answer]" value="1"> Да</label>
                                <label class="flex items-center gap-1.5"><input type="radio" name="presets[{{ $item->id }}][answer]" value="0" checked> Нет</label>
                            </div>
                            @break
                        @case('boolean_text')
                            <div class="flex gap-4 text-sm mb-2">
                                <label class="flex items-center gap-1.5"><input type="radio" name="presets[{{ $item->id }}][answer]" value="1"> Да</label>
                                <label class="flex items-center gap-1.5"><input type="radio" name="presets[{{ $item->id }}][answer]" value="0" checked> Нет</label>
                            </div>
                            <input type="text" name="presets[{{ $item->id }}][text]" placeholder="Уточнение…"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @break
                        @case('select')
                            <select name="presets[{{ $item->id }}][answer]"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— не выбрано —</option>
                                @foreach(($item->options ?? []) as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                            @break
                        @default
                            <input type="text" name="presets[{{ $item->id }}][answer]"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @endswitch
                    <div class="text-xs text-gray-400 mt-1">
                        @if($item->spawns_task)Задача → {{ $item->executorUser?->name ?? ($item->executor_mode === 'department_head' ? 'руководитель отдела' : 'инициатор') }}@else Без задачи @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ЧАСТЬ 2 — произвольные пункты инициатора --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Дополнительные пункты</h3>
            <button type="button" @click="custom.push({title:'',department:'',field_type:'boolean',answer:'1',executor_id:'',spawns_task:true})"
                    class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs hover:bg-gray-200">+ Добавить пункт</button>
        </div>

        <template x-for="(row, i) in custom" :key="i">
            <div class="border border-indigo-100 bg-indigo-50/30 rounded-lg px-4 py-3 space-y-2">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input type="text" x-model="row.department" :name="`custom[${i}][department]`" placeholder="Отдел"
                           class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <input type="text" x-model="row.title" :name="`custom[${i}][title]`" placeholder="Пункт" required
                           class="sm:col-span-2 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <select x-model="row.field_type" :name="`custom[${i}][field_type]`"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="boolean">Да/Нет</option>
                        <option value="text">Текст</option>
                    </select>
                    <template x-if="row.field_type === 'boolean'">
                        <select x-model="row.answer" :name="`custom[${i}][answer]`"
                                class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option value="1">Да</option>
                            <option value="0">Нет</option>
                        </select>
                    </template>
                    <template x-if="row.field_type === 'text'">
                        <input type="text" x-model="row.answer" :name="`custom[${i}][answer]`" placeholder="Значение"
                               class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </template>
                    <select x-model="row.executor_id" :name="`custom[${i}][executor_id]`" required
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— кому задача —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" x-model="row.spawns_task" :name="`custom[${i}][spawns_task]`" value="1"
                               class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"> Порождать задачу
                    </label>
                    <button type="button" @click="custom.splice(i,1)" class="text-xs text-red-500 hover:underline">Удалить</button>
                </div>
            </div>
        </template>
    </div>

    <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Заполнить и распределить задачи</button>
</form>
