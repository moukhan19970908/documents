<x-app-layout>
    <x-slot name="title">{{ isset($documentType) ? 'Редактировать тип' : 'Новый тип документа' }} — Vamin</x-slot>

    <div class="max-w-2xl">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ isset($documentType) ? 'Редактировать тип' : 'Новый тип документа' }}</h1>

        <form action="{{ isset($documentType) ? route('admin.document-types.update', $documentType) : route('admin.document-types.store') }}" method="POST" class="space-y-5">
            @csrf
            @if(isset($documentType)) @method('PUT') @endif

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название *</label>
                    <input type="text" name="name" value="{{ old('name', $documentType->name ?? '') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $documentType->slug ?? '') }}"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                           placeholder="Оставьте пустым — заполнится автоматически">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Маршрут по умолчанию</label>
                    <select name="default_workflow_id" class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— Не назначен —</option>
                        @foreach($workflows as $wf)
                            <option value="{{ $wf->id }}" {{ old('default_workflow_id', $documentType->default_workflow_id ?? '') == $wf->id ? 'selected' : '' }}>{{ $wf->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Custom fields --}}
            <div x-data="fieldsBuilder()" class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm font-semibold text-gray-800">Дополнительные поля</p>
                    <button @click="addField()" type="button" class="text-sm text-[#5B4FE8] font-medium hover:underline flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Добавить поле
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(field, index) in fields" :key="field.id">
                        <div class="border border-gray-200 rounded-lg p-3 space-y-3">
                            <div class="flex gap-3">
                                <div class="flex-1">
                                    <label class="text-xs text-gray-500 block mb-1">Метка</label>
                                    <input type="text" :name="`fields[${index}][label]`" x-model="field.label" placeholder="Например: Контрагент"
                                           class="w-full text-sm border border-gray-200 rounded px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                </div>
                                <div class="w-36">
                                    <label class="text-xs text-gray-500 block mb-1">Ключ</label>
                                    <input type="text" :name="`fields[${index}][field_key]`" x-model="field.field_key" placeholder="contractor"
                                           class="w-full text-sm border border-gray-200 rounded px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                </div>
                                <div class="w-32">
                                    <label class="text-xs text-gray-500 block mb-1">Тип</label>
                                    <select :name="`fields[${index}][field_type]`" x-model="field.field_type"
                                            class="w-full text-sm border border-gray-200 rounded px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                        <option value="text">Текст</option>
                                        <option value="number">Число</option>
                                        <option value="date">Дата</option>
                                        <option value="select">Список</option>
                                        <option value="textarea">Textarea</option>
                                    </select>
                                </div>
                                <div class="flex items-end pb-1.5">
                                    <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                                        <input type="checkbox" :name="`fields[${index}][is_required]`" x-model="field.is_required" class="rounded">
                                        Обяз.
                                    </label>
                                </div>
                                <div class="flex items-end pb-1.5">
                                    <button @click="removeField(index)" type="button" class="text-gray-400 hover:text-red-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="fields.length === 0" class="text-sm text-gray-400 py-4 text-center">Поля не добавлены</div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                    {{ isset($documentType) ? 'Сохранить' : 'Создать тип' }}
                </button>
                <a href="{{ route('admin.document-types.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
            </div>
        </form>
    </div>

    <script>
    function fieldsBuilder() {
        return {
            fields: @json(isset($documentType) ? $documentType->fields->map(fn($f) => [
                'id' => $f->id, 'label' => $f->label, 'field_key' => $f->field_key,
                'field_type' => $f->field_type, 'is_required' => (bool)$f->is_required,
            ])->values() : []),
            addField() {
                this.fields.push({ id: Date.now(), label: '', field_key: '', field_type: 'text', is_required: false });
            },
            removeField(i) { this.fields.splice(i, 1); },
        };
    }
    </script>
</x-app-layout>
