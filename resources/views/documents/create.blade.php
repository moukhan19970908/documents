<x-app-layout>
    <x-slot name="title">Новый документ — Vamin</x-slot>

    @php
        $workflowsData = $workflows->map(fn($w) => [
            'id'        => $w->id,
            'name'      => $w->name,
            'fields'    => $w->process_fields ?? [],
            'approvers' => $w->stages->flatMap(fn($s) => $s->approvers)->map(fn($a) => [
                'id'   => $a->user?->id,
                'name' => $a->user?->name ?? '—',
            ])->filter(fn($a) => $a['id'])->unique('id')->values(),
        ]);
    @endphp

    <script>
    window.__docCreateData = @json($workflowsData);
    window.__docCreateOldId = '{{ old('workflow_id', '') }}';

    document.addEventListener('alpine:init', () => {
        Alpine.data('documentCreate', () => ({
            allWorkflows: window.__docCreateData,
            workflowId: window.__docCreateOldId ? Number(window.__docCreateOldId) : '',
            selectedWorkflow: null,

            init() {
                if (this.workflowId) {
                    this.selectedWorkflow = this.allWorkflows.find(w => w.id === this.workflowId) || null;
                }
            },

            onWorkflowChange() {
                const id = Number(this.workflowId);
                this.selectedWorkflow = id ? (this.allWorkflows.find(w => w.id === id) || null) : null;
            },
        }));
    });
    </script>

    <div class="max-w-2xl" x-data="documentCreate">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Новый документ</h1>
        </div>

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf

            {{-- Main card --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

                {{-- Название --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название документа *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]"
                           placeholder="Введите название документа">
                    @error('title')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Тип документа (workflow) --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Тип документа</label>
                    <select name="workflow_id" x-model="workflowId" @change="onWorkflowChange()"
                            class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                        <option value="">— Выберите маршрут —</option>
                        <template x-for="wf in allWorkflows" :key="wf.id">
                            <option :value="wf.id" x-text="wf.name"></option>
                        </template>
                    </select>
                    @error('workflow_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Custom fields from selected workflow --}}
                <div x-show="selectedWorkflow && selectedWorkflow.fields && selectedWorkflow.fields.length > 0"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="border-t border-gray-100 pt-5 space-y-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Поля процесса</p>
                    <template x-for="(field, idx) in (selectedWorkflow ? selectedWorkflow.fields : [])" :key="idx">
                        <div>
                            <label class="text-xs font-medium text-gray-700 block mb-1" x-text="field.name"></label>

                            {{-- string --}}
                            <template x-if="field.type === 'string' || !field.type">
                                <input type="text"
                                       :name="`custom_fields[${field.name}]`"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                            </template>

                            {{-- number --}}
                            <template x-if="field.type === 'number'">
                                <input type="number"
                                       :name="`custom_fields[${field.name}]`"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                            </template>

                            {{-- date --}}
                            <template x-if="field.type === 'date'">
                                <input type="date"
                                       :name="`custom_fields[${field.name}]`"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                            </template>

                            {{-- file --}}
                            <template x-if="field.type === 'file'">
                                <input type="file"
                                       :name="`custom_fields[${field.name}]`"
                                       class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#6C5CE7] file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700">
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Approvers read-only block --}}
                <div x-show="selectedWorkflow && selectedWorkflow.approvers && selectedWorkflow.approvers.length > 0"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="border-t border-gray-100 pt-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Согласующие по маршруту</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="approver in (selectedWorkflow ? selectedWorkflow.approvers : [])" :key="approver.id">
                            <span class="inline-flex items-center gap-1.5 bg-[#6C5CE7]/8 text-[#6C5CE7] border border-[#6C5CE7]/20 rounded-full px-3 py-1 text-xs font-medium">
                                <span class="w-5 h-5 rounded-full bg-[#6C5CE7]/20 flex items-center justify-center text-[10px] font-bold shrink-0"
                                      x-text="approver.name.charAt(0).toUpperCase()"></span>
                                <span x-text="approver.name"></span>
                            </span>
                        </template>
                    </div>
                </div>

                {{-- Крайний срок --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Крайний срок</label>
                    <input type="date" name="deadline_at" value="{{ old('deadline_at') }}"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                    <p class="text-xs text-gray-400 mt-1">Необязательно</p>
                </div>

                {{-- Файл документа --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Файл документа</label>
                    <input type="file" name="file"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#6C5CE7] file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700">
                    <p class="text-xs text-gray-400 mt-1">Максимальный размер файла: 50 МБ</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#6C5CE7] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Создать документ
                </button>
                <a href="{{ route('documents.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                    Отмена
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
