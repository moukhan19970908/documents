{{-- Действие: форма / согласование --}}
<form method="POST" action="{{ route('procedures.stage', $procedure) }}" enctype="multipart/form-data" class="space-y-3">
    @csrf
    <div>
        <label class="text-xs text-gray-500 block mb-1">Комментарий</label>
        <textarea name="comment" rows="2"
                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('comment') }}</textarea>
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">
            Вложения @if($active->stage?->require_attachments)<span class="text-red-500">— обязательны</span>@endif
        </label>
        <input type="file" name="files[]" multiple
               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-gray-100 file:text-gray-700">
    </div>
    <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
        {{ $active->type === 'form' ? 'Отправить' : 'Согласовать' }}
    </button>
</form>
