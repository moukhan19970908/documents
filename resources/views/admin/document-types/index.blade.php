<x-app-layout>
    <x-slot name="title">Классификаторы и типы — Vamin</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Классификаторы и типы</h1>
            <p class="text-sm text-gray-500 mt-1">Тип задаёт маршрут, имя, номер и фильтры документа.</p>
        </div>
        <a href="{{ route('admin.document-types.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новый тип
        </a>
    </div>

    <div class="space-y-4">
        @forelse($documentTypes as $type)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                            @include('admin.partials.type-icon', ['icon' => $type->icon, 'class' => 'w-4 h-4'])
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="font-semibold text-gray-900">{{ $type->name }}</h2>
                                <span class="text-sm font-mono font-semibold text-amber-500">[{{ $type->code ?? '—' }}]</span>
                                <span class="flex items-center gap-1.5 text-xs font-medium {{ $type->is_active ? 'text-emerald-600' : 'text-gray-400' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $type->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                    {{ $type->is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ $type->documents_count }} документов
                                @if($type->numerator) · номер: <code class="text-gray-500">{{ $type->numerator->mask }}</code> @endif
                                @if($type->name_template) · маска имени: <code class="text-gray-500">{{ $type->name_template }}</code> @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <a href="{{ route('admin.document-types.edit', $type) }}" class="text-xs font-medium text-[#5B4FE8] border border-[#5B4FE8] px-3 py-1.5 rounded-lg hover:bg-indigo-50">Изменить</a>
                        <form action="{{ route('admin.document-types.destroy', $type) }}" method="POST" onsubmit="return confirm('Удалить тип?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50">Удалить</button>
                        </form>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-3">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5">Подтипы</p>
                        @forelse($type->subtypes as $subtype)
                            <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded mr-1 mb-1">{{ $subtype->name }}</span>
                        @empty
                            <p class="text-xs text-gray-400">Нет подтипов</p>
                        @endforelse
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5">Атрибуты</p>
                        @forelse($type->fields as $field)
                            <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded mr-1 mb-1">
                                {{ $field->label }}@if($field->is_required)<span class="text-red-400">*</span>@endif
                            </span>
                        @empty
                            <p class="text-xs text-gray-400">Нет атрибутов</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">Типы документов не найдены</div>
        @endforelse
    </div>
</x-app-layout>
