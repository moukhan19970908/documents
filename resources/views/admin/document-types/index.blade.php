<x-app-layout>
    <x-slot name="title">Типы документов — ArchManuscript</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Типы документов</h1>
        <a href="{{ route('admin.document-types.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новый тип
        </a>
    </div>

    <div class="space-y-4">
        @forelse($documentTypes as $type)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <h2 class="font-semibold text-gray-900">{{ $type->name }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Slug: {{ $type->slug }}</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('admin.document-types.edit', $type) }}" class="text-xs font-medium text-[#5B4FE8] border border-[#5B4FE8] px-3 py-1.5 rounded-lg hover:bg-indigo-50">Изменить</a>
                        <form action="{{ route('admin.document-types.destroy', $type) }}" method="POST" onsubmit="return confirm('Удалить тип?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50">Удалить</button>
                        </form>
                    </div>
                </div>
                @if($type->fields->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach($type->fields as $field)
                            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">
                                {{ $field->label }}
                                @if($field->is_required)<span class="text-red-400">*</span>@endif
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-400">Нет дополнительных полей</p>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">Типы документов не найдены</div>
        @endforelse
    </div>
</x-app-layout>
