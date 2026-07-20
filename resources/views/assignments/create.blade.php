<x-app-layout>
    <x-slot name="title">Новое поручение — Vamin</x-slot>

    <div class="max-w-3xl" x-data="{ q: '' }">
        <div class="mb-6">
            <a href="{{ route('assignments.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← Поручения</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Новое поручение</h1>
        </div>

        @if($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('assignments.store') }}">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4 mb-4">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Тема поручения</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           placeholder="Подготовить акт сверки с поставщиком"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Исполнитель</label>
                        <input type="text" x-model="q" placeholder="Фильтр по имени…"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 mb-1.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <select name="executor_id" required size="6"
                                class="w-full text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($executors as $e)
                                <option value="{{ $e->id }}"
                                        x-show="q === '' || @js(mb_strtolower($e->name)).includes(q.toLowerCase())"
                                        @selected(old('executor_id') == $e->id)>
                                    {{ $e->name }}@if($e->position) — {{ $e->position }}@endif
                                </option>
                            @endforeach
                        </select>
                        @if($executors->isEmpty())
                            <p class="text-xs text-amber-600 mt-1">Нет сотрудников в вашей области постановки.</p>
                        @endif
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Крайний срок</label>
                        <input type="date" name="due_at" value="{{ old('due_at') }}"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                </div>

                @include('assignments._participants', ['settings' => $settings, 'executors' => $executors, 'people' => $people])
            </div>

            <label class="text-xs text-gray-500 block mb-1">Текст поручения</label>
            @include('partials.blank-editor', ['content' => old('body_html', $blankContent ?? ''), 'inputName' => 'body_html', 'withTokens' => false])

            <div class="flex items-center gap-2 mt-5">
                <button class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Поставить поручение</button>
                <a href="{{ route('assignments.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
            </div>
        </form>
    </div>
</x-app-layout>
