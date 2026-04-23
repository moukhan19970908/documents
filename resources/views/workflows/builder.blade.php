<x-app-layout>
    <x-slot name="title">Конструктор маршрута — Vamin</x-slot>

    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('workflows.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $workflow->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Конструктор маршрута согласования</p>
        </div>
    </div>

    <div x-data="workflowBuilder()" class="flex gap-5">

        {{-- Stages panel --}}
        <div class="flex-1">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-semibold text-gray-700">Этапы согласования</p>
                <button @click="addStage()" type="button"
                        class="flex items-center gap-1.5 text-sm text-[#5B4FE8] font-medium hover:underline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Добавить этап
                </button>
            </div>

            <form action="{{ route('workflows.update', $workflow) }}" method="POST" id="wf-form">
                @csrf @method('PUT')
                <input type="hidden" name="name" value="{{ $workflow->name }}">

                <div class="space-y-3" id="stages-list">
                    <template x-for="(stage, index) in stages" :key="stage.id">
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            {{-- Stage header --}}
                            <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 border-b border-gray-100">
                                <div class="w-6 h-6 bg-[#5B4FE8] text-white rounded-full flex items-center justify-center text-xs font-bold" x-text="index + 1"></div>
                                <input type="text" :name="`stages[${index}][name]`" x-model="stage.name"
                                       class="flex-1 text-sm font-medium text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0"
                                       placeholder="Название этапа">
                                <button @click="removeStage(index)" type="button" class="text-gray-400 hover:text-red-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                            {{-- Stage settings --}}
                            <div class="px-4 py-3 space-y-3">
                                <div class="flex gap-3">
                                    <div class="flex-1">
                                        <label class="text-xs text-gray-500 block mb-1">Тип согласования</label>
                                        <select :name="`stages[${index}][stage_type]`" x-model="stage.stage_type"
                                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                            <option value="sequential">Все согласующие (последовательно)</option>
                                            <option value="parallel">Любой из согласующих (параллельно)</option>
                                        </select>
                                    </div>
                                    <div class="w-40">
                                        <label class="text-xs text-gray-500 block mb-1">Срок (часов)</label>
                                        <input type="number" :name="`stages[${index}][deadline_hours]`" x-model="stage.deadline_hours"
                                               min="1" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                    </div>
                                </div>

                                {{-- Approvers --}}
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-xs text-gray-500">Согласующие</label>
                                        <button @click="addApprover(index)" type="button" class="text-xs text-[#5B4FE8] hover:underline">+ Добавить</button>
                                    </div>
                                    <div class="space-y-2">
                                        <template x-for="(approver, ai) in stage.approvers" :key="ai">
                                            <div class="flex items-center gap-2">
                                                <select :name="`stages[${index}][approvers][${ai}]`" x-model="approver.approver_id"
                                                        class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                                    <option value="">— Выбрать сотрудника —</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role_label }})</option>
                                                    @endforeach
                                                </select>
                                                <button @click="removeApprover(index, ai)" type="button" class="text-gray-400 hover:text-red-500">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                        </template>
                                        <div x-show="stage.approvers.length === 0" class="text-xs text-gray-400 py-1">Согласующие не добавлены</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="stages.length === 0" class="bg-white rounded-xl border border-dashed border-gray-300 py-10 text-center text-gray-400 text-sm">
                        Добавьте первый этап согласования
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                        Сохранить маршрут
                    </button>
                    <a href="{{ route('workflows.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                        Отмена
                    </a>
                </div>
            </form>
        </div>

        {{-- Preview sidebar --}}
        <aside class="w-56 shrink-0">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-700 uppercase tracking-widest mb-3">Предпросмотр</p>
                <div class="space-y-2">
                    <template x-for="(stage, index) in stages" :key="stage.id">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-[#5B4FE8] text-white rounded-full flex items-center justify-center text-xs font-bold shrink-0" x-text="index + 1"></div>
                            <span class="text-sm text-gray-700 truncate" x-text="stage.name || 'Этап ' + (index + 1)"></span>
                        </div>
                    </template>
                    <div x-show="stages.length === 0" class="text-xs text-gray-400">Маршрут пустой</div>
                </div>
            </div>
        </aside>

    </div>

    <script>
    function workflowBuilder() {
        return {
            stages: @json($workflow->stages->sortBy('sort_order')->map(fn($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'stage_type'     => $s->stage_type,
                'deadline_hours' => $s->deadline_hours,
                'approvers'      => $s->approvers->map(fn($a) => ['approver_id' => $a->approver_id])->values()->toArray(),
            ])->values()),
            addStage() {
                this.stages.push({ id: Date.now(), name: '', stage_type: 'sequential', deadline_hours: 24, approvers: [] });
            },
            removeStage(index) {
                this.stages.splice(index, 1);
            },
            addApprover(stageIndex) {
                this.stages[stageIndex].approvers.push({ approver_id: '' });
            },
            removeApprover(stageIndex, approverIndex) {
                this.stages[stageIndex].approvers.splice(approverIndex, 1);
            },
        }
    }
    </script>
</x-app-layout>
