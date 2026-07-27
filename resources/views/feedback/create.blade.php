<x-app-layout>
    <x-slot name="title">Новое обращение — Vamin</x-slot>

    <div class="flex items-center gap-2 text-sm text-gray-400 mb-3">
        <a href="{{ route('feedback.index') }}" class="hover:text-gray-700">Обратная связь</a>
        <span>›</span>
        <span class="text-gray-600">Новое обращение</span>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Новое обращение</h1>

    @if($errors->any())
        <div class="mb-4 max-w-2xl rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('feedback.store') }}" class="max-w-2xl bg-white border border-gray-200 rounded-xl p-6 space-y-5">
        @csrf

        <div>
            <label class="text-sm font-medium text-gray-700 block mb-2">Категория</label>
            <div class="flex flex-wrap gap-2" x-data="{ cat: '{{ old('category', 'bug') }}' }">
                @foreach(\App\Models\Feedback::CATEGORIES as $key => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="category" value="{{ $key }}" x-model="cat" class="sr-only">
                        <span class="inline-block px-4 py-2 rounded-lg text-sm font-medium border"
                              :class="cat === '{{ $key }}' ? 'bg-[#5B4FE8] text-white border-[#5B4FE8]' : 'bg-white text-gray-600 border-gray-200'">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700 block mb-1">Тема</label>
            <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="255"
                   placeholder="Кратко опишите суть"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700 block mb-1">Описание</label>
            <textarea name="body" rows="6" required maxlength="5000"
                      placeholder="Что произошло, чего не хватает или о чём вопрос"
                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('body') }}</textarea>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('feedback.index') }}" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
            <button type="submit" class="px-5 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Отправить</button>
        </div>
    </form>
</x-app-layout>
