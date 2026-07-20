@php
    /** @var \App\Models\ProcedureChecklistItem|null $item */
    $item = $item ?? null;
    $optionsRaw = $item && is_array($item->options) ? implode("\n", $item->options) : '';
@endphp
<form method="POST" action="{{ $action }}"
      x-data="{
          fieldType: '{{ old('field_type', $item->field_type ?? 'boolean') }}',
          mode: '{{ old('executor_mode', $item->executor_mode ?? 'user') }}'
      }"
      class="space-y-3">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Отдел</label>
            <input name="department" value="{{ old('department', $item->department ?? '') }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                   placeholder="IT-отдел">
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">Пункт</label>
            <input name="title" value="{{ old('title', $item->title ?? '') }}" required
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                   placeholder="Нужна учётка в 1С?">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Тип поля</label>
            <select name="field_type" x-model="fieldType"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                @foreach($fieldTypes as $key => $label)
                    <option value="{{ $key }}" @selected(old('field_type', $item->field_type ?? 'boolean') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="fieldType === 'select'" x-cloak>
            <label class="text-xs text-gray-500 block mb-1">Варианты списка (по строке на вариант)</label>
            <textarea name="options_raw" rows="2"
                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                      placeholder="ЧБ&#10;МФУ&#10;со сканом">{{ old('options_raw', $optionsRaw) }}</textarea>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Исполнитель задачи</label>
            <select name="executor_mode" x-model="mode"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                @foreach($itemModes as $key => $label)
                    <option value="{{ $key }}" @selected(old('executor_mode', $item->executor_mode ?? 'user') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="mode === 'user'" x-cloak>
            <label class="text-xs text-gray-500 block mb-1">Пользователь</label>
            <select name="executor_user_id"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">— выберите —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((int) old('executor_user_id', $item->executor_user_id ?? 0) === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="spawns_task" value="1" @checked(old('spawns_task', $item->spawns_task ?? true))
               class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
        Порождать задачу при положительном/заполненном ответе
    </label>

    <div class="flex gap-2 pt-1">
        <button class="px-3 py-1.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">{{ $submitLabel ?? 'Сохранить' }}</button>
        @isset($onCancel)
            <button type="button" @click="{{ $onCancel }}" class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
        @endisset
    </div>
</form>
