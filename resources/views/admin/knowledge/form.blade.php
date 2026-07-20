@php $isEdit = $material->exists; @endphp

<x-app-layout>
    <x-slot name="title">{{ $isEdit ? 'Материал: ' . $material->title : 'Новый материал' }} — Vamin</x-slot>

    <div x-data="materialForm(
            @js($directionsJson),
            @js(old('direction_id', $material->direction_id)),
            @js(old('department_id', $material->department_id)),
            @js(old('type', $material->type ?? 'article'))
        )">
        <form action="{{ $isEdit ? route('admin.knowledge.update', $material) : route('admin.knowledge.store') }}" method="POST">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <a href="{{ route('knowledge.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← К материалам</a>
                    <h1 class="text-2xl font-bold text-gray-900 mt-1">Редактор материала</h1>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('knowledge.index') }}" class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
                    <button class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                        {{ $isEdit ? 'Сохранить' : 'Сохранить и опубликовать' }}
                    </button>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
            @endif

            {{-- Основное --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4 space-y-4">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Заголовок</label>
                    <input type="text" name="title" value="{{ old('title', $material->title) }}" required
                           placeholder="Порядок согласования договоров поставки"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1.5">Тип материала</label>
                        <input type="hidden" name="type" :value="type">
                        <div class="flex flex-wrap gap-2">
                            @foreach($types as $code => $label)
                                <button type="button" @click="type = '{{ $code }}'"
                                        :class="type === '{{ $code }}' ? 'border-[#5B4FE8] bg-indigo-50 text-[#5B4FE8]' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                        class="px-3 py-2 rounded-lg border text-sm font-medium transition">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Время на изучение (мин)</label>
                        <input type="number" name="study_minutes" value="{{ old('study_minutes', $material->study_minutes) }}" min="1" max="1000"
                               placeholder="7"
                               class="w-40 text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                </div>
            </div>

            {{-- Тело материала --}}
            <label class="text-xs text-gray-500 block mb-1">Тело материала</label>
            @include('partials.blank-editor', [
                'content'    => old('body', $material->body ?? ''),
                'inputName'  => 'body',
                'withTokens' => false,
            ])

            {{-- Привязка к структуре --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 mt-4">
                <h2 class="font-semibold text-gray-900">Привязка к структуре</h2>
                <p class="text-xs text-gray-400 mb-4">Определяет, в каком разделе дерева знаний появится материал.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Направление</label>
                        <select name="direction_id" x-model.number="directionId" @change="departmentId = ''"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option value="">— не выбрано —</option>
                            <template x-for="d in directions" :key="d.id">
                                <option :value="d.id" x-text="d.name"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Отдел</label>
                        <select name="department_id" x-model.number="departmentId"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option value="">— не выбрано —</option>
                            <template x-for="dep in departments()" :key="dep.id">
                                <option :value="dep.id" x-text="dep.name"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Уровень</label>
                        <select name="level"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option value="">— не выбрано —</option>
                            @foreach($levels as $code => $label)
                                <option value="{{ $code }}" @selected(old('level', $material->level) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-3">Кто именно увидит материал — настраивается отдельно на странице «Управление доступом» после сохранения.</p>
            </div>
        </form>
    </div>

    <script>
        function materialForm(directions, directionId, departmentId, type) {
            return {
                directions,
                directionId: directionId ?? '',
                departmentId: departmentId ?? '',
                type: type ?? 'article',

                direction() {
                    return this.directions.find(d => d.id === this.directionId) ?? null;
                },
                departments() {
                    return this.direction()?.departments ?? [];
                },
            };
        }
    </script>
</x-app-layout>
