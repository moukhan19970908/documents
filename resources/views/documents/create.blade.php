<x-app-layout>
    <x-slot name="title">Новый документ — ArchManuscript</x-slot>

    <div class="max-w-2xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Новый документ</h1>
        </div>

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название документа *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                           placeholder="Введите название документа">
                    @error('title')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div x-data="{ typeId: '{{ old('document_type_id') }}' }">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Тип документа *</label>
                    <select name="document_type_id" x-model="typeId" required
                            class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— Выберите тип —</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('document_type_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror

                    {{-- Dynamic fields --}}
                    @foreach($documentTypes as $type)
                        <template x-if="typeId == '{{ $type->id }}'">
                            <div class="mt-5 space-y-4 border-t border-gray-100 pt-5">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Дополнительные поля</p>
                                @foreach($type->fields as $field)
                                    <div>
                                        <label class="text-xs font-medium text-gray-700 block mb-1">
                                            {{ $field->label }}
                                            @if($field->is_required)<span class="text-red-500">*</span>@endif
                                        </label>
                                        @if($field->field_type === 'select')
                                            <select name="data[{{ $field->field_key }}]"
                                                    {{ $field->is_required ? 'required' : '' }}
                                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                                <option value="">— Выберите —</option>
                                                @foreach($field->options ?? [] as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($field->field_type === 'date')
                                            <input type="date" name="data[{{ $field->field_key }}]"
                                                   {{ $field->is_required ? 'required' : '' }}
                                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        @elseif($field->field_type === 'number')
                                            <input type="number" name="data[{{ $field->field_key }}]"
                                                   {{ $field->is_required ? 'required' : '' }}
                                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        @else
                                            <input type="text" name="data[{{ $field->field_key }}]"
                                                   {{ $field->is_required ? 'required' : '' }}
                                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </template>
                    @endforeach
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Файл документа</label>
                    <input type="file" name="file"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#5B4FE8] file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700">
                    <p class="text-xs text-gray-400 mt-1">Максимальный размер файла: 50 МБ</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Создать документ
                </button>
                <a href="{{ route('documents.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                    Отмена
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
