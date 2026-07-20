{{-- Действие: развилка (положительный / негативный вердикт) --}}
<form method="POST" action="{{ route('procedures.branch', $procedure) }}" enctype="multipart/form-data"
      x-data="{ verdict: 'positive' }" class="space-y-3">
    @csrf
    <p class="text-sm text-gray-600">Негативный вердикт остановит процедуру.</p>
    <div class="flex gap-3">
        <label class="flex-1 border rounded-lg px-4 py-3 cursor-pointer transition-colors"
               :class="verdict === 'positive' ? 'border-green-400 bg-green-50' : 'border-gray-200'">
            <input type="radio" name="verdict" value="positive" x-model="verdict" class="sr-only">
            <span class="text-sm font-medium text-gray-900">✓ Положительно</span>
            <div class="text-xs text-gray-400">Процедура идёт дальше</div>
        </label>
        <label class="flex-1 border rounded-lg px-4 py-3 cursor-pointer transition-colors"
               :class="verdict === 'negative' ? 'border-red-400 bg-red-50' : 'border-gray-200'">
            <input type="radio" name="verdict" value="negative" x-model="verdict" class="sr-only">
            <span class="text-sm font-medium text-gray-900">✗ Негативно</span>
            <div class="text-xs text-gray-400">Процедура останавливается</div>
        </label>
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">Комментарий <span x-show="verdict === 'negative'" class="text-red-500">— обязателен</span></label>
        <textarea name="comment" rows="2"
                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('comment') }}</textarea>
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">Вложения</label>
        <input type="file" name="files[]" multiple
               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-gray-100 file:text-gray-700">
    </div>
    <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Отправить вердикт</button>
</form>
