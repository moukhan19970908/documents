{{-- "Свой сценарий" — ad-hoc document creation modal.
     Initiator fills title, deadline, file, dynamic custom fields and picks approvers.
     Posts to documents.store with no workflow_id → ad-hoc approval path. --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('customScenario', () => ({
        open: false,
        search: '',
        selectedApprovers: [],
        fields: [],

        addField() {
            this.fields.push({ name: '', value: '' });
        },
        removeField(idx) {
            this.fields.splice(idx, 1);
        },
        matchesSearch(name, dept) {
            const q = this.search.toLowerCase().trim();
            if (!q) return true;
            return name.toLowerCase().includes(q) || dept.toLowerCase().includes(q);
        },
    }));
});
</script>

<div x-show="open"
     x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
     style="display:none" @click.self="open = false" @keydown.escape.window="open = false">

    <div x-show="open"
         x-transition:enter="transition transform duration-200 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition transform duration-150 ease-in" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col" style="max-height:90vh; display:none">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Свой сценарий</h2>
                <p class="text-xs text-gray-500 mt-0.5">Создайте документ и выберите согласующих</p>
            </div>
            <button type="button" @click="open = false" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data"
              class="flex flex-col min-h-0 overflow-hidden flex-1 text-left">
            @csrf

            <div class="overflow-y-auto px-6 py-5 space-y-5">

                {{-- Название --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название *</label>
                    <input type="text" name="title" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                           placeholder="Введите название документа">
                </div>

                {{-- Тип документа --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Тип документа</label>
                    <input type="text" value="Свой сценарий" readonly
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 bg-gray-50 text-gray-500 cursor-default">
                </div>

                {{-- Крайний срок --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Крайний срок</label>
                    <input type="date" name="deadline_at"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>

                {{-- Файл документа --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Файл документа</label>
                    <input type="file" name="file"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#5B4FE8] file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700">
                    <p class="text-xs text-gray-400 mt-1">Максимальный размер файла: 50 МБ</p>
                </div>

                {{-- Динамические поля --}}
                <div class="border-t border-gray-100 pt-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-widest">Дополнительные поля</p>
                        <button type="button" @click="addField()"
                                class="inline-flex items-center gap-1 text-xs font-medium text-[#5B4FE8] hover:underline">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Добавить поле
                        </button>
                    </div>
                    <p x-show="fields.length === 0" class="text-xs text-gray-400">Поля не добавлены</p>
                    <div class="space-y-2">
                        <template x-for="(field, idx) in fields" :key="idx">
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="field.name" placeholder="Название поля"
                                       class="w-2/5 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <input type="text" x-model="field.value" :name="field.name ? `custom_fields[${field.name}]` : ''" placeholder="Значение"
                                       class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <button type="button" @click="removeField(idx)" class="p-2 text-gray-400 hover:text-red-500 shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Согласующие --}}
                <div class="border-t border-gray-100 pt-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-widest">Согласующие</p>
                        <p class="text-xs text-gray-500">Выбрано: <span class="font-semibold text-[#5B4FE8]" x-text="selectedApprovers.length"></span></p>
                    </div>
                    <div class="relative mb-2">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="search" type="text" placeholder="Поиск по имени или отделу..."
                               class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div class="overflow-y-auto space-y-1.5 border border-gray-100 rounded-xl p-2" style="max-height:240px">
                        @forelse($approverCandidates as $candidate)
                            <label x-show="matchesSearch('{{ addslashes($candidate->name) }}', '{{ addslashes($candidate->department?->name ?? '') }}')"
                                   class="flex items-center gap-3 p-2.5 rounded-xl cursor-pointer hover:bg-gray-50 border border-transparent"
                                   :class="selectedApprovers.includes('{{ $candidate->id }}') ? 'border-[#5B4FE8] bg-indigo-50' : ''">
                                <input type="checkbox" name="approvers[]" value="{{ $candidate->id }}"
                                       x-model="selectedApprovers" class="w-4 h-4 rounded text-[#5B4FE8] border-gray-300 focus:ring-[#5B4FE8]">
                                <div class="w-8 h-8 rounded-full bg-[#5B4FE8] text-white flex items-center justify-center text-xs font-semibold shrink-0">
                                    {{ strtoupper(mb_substr($candidate->name, 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $candidate->name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $candidate->department?->name ?? '—' }}</p>
                                </div>
                            </label>
                        @empty
                            <p class="text-xs text-gray-400 px-2 py-3 text-center">Нет доступных согласующих</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex items-center gap-3 shrink-0">
                <button type="button" @click="open = false"
                        class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-50">Отмена</button>
                <button type="submit"
                        class="flex-1 bg-[#5B4FE8] text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
                    Создать документ
                </button>
            </div>
        </form>
    </div>
</div>
