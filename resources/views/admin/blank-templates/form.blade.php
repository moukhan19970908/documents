@php
    $isEdit = $template->exists;
@endphp

<x-app-layout>
    <x-slot name="title">{{ $isEdit ? 'Бланк: ' . $template->name : 'Новый бланк' }} — Vamin</x-slot>

    <div x-data="blankForm(@js($typesForForm), @js(old('document_type_id', $template->document_type_id)), @js(old('document_subtype_id', $template->document_subtype_id)))">
        <form action="{{ $isEdit ? route('admin.blank-templates.update', $template) : route('admin.blank-templates.store') }}" method="POST">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <a href="{{ route('admin.blank-templates.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← Все бланки</a>
                    <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $isEdit ? 'Шаблон бланка' : 'Новый бланк' }}</h1>
                </div>
                <button class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shrink-0">Сохранить</button>
            </div>

            @if(session('success'))
                <div class="mb-4 bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
            @endif

            {{-- Паспорт бланка --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4 grid grid-cols-4 gap-4">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Название</label>
                    <input type="text" name="name" value="{{ old('name', $template->name) }}" required
                           placeholder="Служебная записка"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-xs text-gray-500 block mb-1">Тип документа</label>
                    <select name="document_type_id" x-model.number="typeId" required
                            @change="subtypeId = ''"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— выберите —</option>
                        <template x-for="t in types" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                    @error('document_type_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-xs text-gray-500 block mb-1">Подтип</label>
                    <select name="document_subtype_id" x-model.number="subtypeId"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— любой —</option>
                        <template x-for="s in subtypes()" :key="s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Пусто — бланк предлагается на всём типе.</p>
                </div>

                <div>
                    <label class="text-xs text-gray-500 block mb-1">Пояснение</label>
                    <input type="text" name="description" value="{{ old('description', $template->description) }}"
                           placeholder="Когда применять"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mt-2">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true))
                               class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                        Активен
                    </label>
                </div>
            </div>

            {{-- Редактор --}}
            @include('partials.blank-editor', [
                'content'    => old('content', $template->content ?? ''),
                'withTokens' => true,
            ])

            @error('content')<p class="text-xs text-red-500 mt-2">{{ $message }}</p>@enderror

            <p class="text-xs text-gray-400 mt-3">
                Поля из списка «+ Поле» подставятся при создании документа: <code>{номер}</code> и <code>{дата}</code> до регистрации
                показываются как <code>___</code> и <code>__.__.____</code>.
            </p>
        </form>
    </div>

    <script>
        function blankForm(types, typeId, subtypeId) {
            return {
                types,
                typeId: typeId ?? '',
                subtypeId: subtypeId ?? '',

                type() {
                    return this.types.find(t => t.id === this.typeId) ?? null;
                },

                subtypes() {
                    return this.type()?.subtypes ?? [];
                },

                /** Токены бланка — те же, что в маске названия типа. */
                tokens() {
                    return this.type()?.tokens ?? [];
                },
            };
        }
    </script>
</x-app-layout>
