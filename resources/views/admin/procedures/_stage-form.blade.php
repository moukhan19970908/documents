@php
    /** @var \App\Models\ProcedureStage|null $stage */
    $stage = $stage ?? null;
    $autoTypes = \App\Models\ProcedureStage::AUTO_TYPES;
@endphp
<form method="POST" action="{{ $action }}"
      x-data="{
          type: '{{ old('type', $stage->type ?? 'approval') }}',
          mode: '{{ old('executor_mode', $stage->executor_mode ?? 'user') }}',
          autoTypes: {{ \Illuminate\Support\Js::from($autoTypes) }},
          get isAuto() { return this.autoTypes.includes(this.type); }
      }"
      class="space-y-3">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Тип этапа</label>
            <select name="type" x-model="type"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                @foreach($stageTypes as $key => $label)
                    <option value="{{ $key }}" @selected(old('type', $stage->type ?? 'approval') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Название этапа</label>
            <input name="title" value="{{ old('title', $stage->title ?? '') }}" required
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="!isAuto" x-cloak>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Исполнитель</label>
            <select name="executor_mode" x-model="mode"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                @foreach($stageModes as $key => $label)
                    <option value="{{ $key }}" @selected(old('executor_mode', $stage->executor_mode ?? 'user') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="mode === 'user'" x-cloak>
            <label class="text-xs text-gray-500 block mb-1">Пользователь</label>
            <select name="executor_user_id"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">— выберите —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((int) old('executor_user_id', $stage->executor_user_id ?? 0) === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="mode === 'role'" x-cloak>
            <label class="text-xs text-gray-500 block mb-1">Роль</label>
            <select name="executor_role"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">— выберите —</option>
                @foreach($roles as $r)
                    <option value="{{ $r->code }}" @selected(old('executor_role', $stage->executor_role ?? '') === $r->code)>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700" x-show="!isAuto" x-cloak>
        <input type="checkbox" name="require_attachments" value="1" @checked(old('require_attachments', $stage->require_attachments ?? false))
               class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
        Обязательные вложения перед прохождением этапа
    </label>

    <div class="flex gap-2 pt-1">
        <button class="px-3 py-1.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">{{ $submitLabel ?? 'Сохранить' }}</button>
        @isset($onCancel)
            <button type="button" @click="{{ $onCancel }}" class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
        @endisset
    </div>
</form>
