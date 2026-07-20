<x-app-layout>
    <x-slot name="title">Запуск процедуры — Vamin</x-slot>

    <div class="max-w-3xl mx-auto">
        <a href="{{ route('procedures.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← Процедуры</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1 mb-6">Запуск процедуры</h1>

        @if($errors->any())<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif

        @if(! $selected)
            {{-- Шаг 1: выбор сценария --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Выберите сценарий</h2>
                <div class="space-y-2">
                    @forelse($templates as $t)
                        <a href="{{ route('procedures.create', ['template' => $t->id]) }}"
                           class="block border border-gray-100 rounded-lg px-4 py-3 hover:border-[#5B4FE8] hover:bg-indigo-50/30 transition-colors">
                            <div class="font-medium text-gray-900">{{ $t->name }}</div>
                            @if($t->description)<div class="text-xs text-gray-400 mt-0.5">{{ $t->description }}</div>@endif
                        </a>
                    @empty
                        <p class="text-sm text-gray-400">Активных шаблонов нет. Обратитесь к администратору.</p>
                    @endforelse
                </div>
            </div>
        @else
            {{-- Шаг 2: первый этап (форма инициатора) --}}
            @php $first = $selected->stages->first(); @endphp
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">{{ $selected->name }}</h2>
                    <a href="{{ route('procedures.create') }}" class="text-xs text-gray-400 hover:text-gray-600">Сменить сценарий</a>
                </div>

                <form method="POST" action="{{ route('procedures.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="template_id" value="{{ $selected->id }}">

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Название процедуры</label>
                        <input name="title" value="{{ old('title') }}" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                               placeholder="Приём: Иванов И.И. — менеджер">
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">
                            {{ $first?->type === 'form' ? $first->title : 'Данные процедуры' }}
                        </label>
                        <textarea name="form_text" rows="5"
                                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                                  placeholder="Должность, ФИО, паспортные данные, прописка…">{{ old('form_text') }}</textarea>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">
                            Вложения @if($first?->require_attachments)<span class="text-red-500">— обязательны (резюме, сканы)</span>@endif
                        </label>
                        <input type="file" name="files[]" multiple
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-gray-100 file:text-gray-700">
                    </div>

                    <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Запустить процедуру</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
