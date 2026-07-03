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
                    <template x-for="(stage, index) in stages" :key="stage._key">
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <input type="hidden" :name="`stages[${index}][id]`" :value="stage.id ?? ''">
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
                                            <option value="sequential">Любой из согласующих (кто-то один подписал — дальше)</option>
                                            <option value="parallel">Все согласующие (нужны подписи всех)</option>
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
                                            <div class="flex flex-col gap-1.5">
                                                <div class="flex items-center gap-2">
                                                    <select :name="`stages[${index}][approvers][${ai}][approver_id]`" x-model="approver.approver_id"
                                                            class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                                        <option value="">— Выбрать сотрудника —</option>
                                                        @foreach($users as $user)
                                                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role_label }})</option>
                                                        @endforeach
                                                    </select>
                                                    <button @click="removeApprover(index, ai)" type="button" class="text-gray-400 hover:text-red-500 shrink-0">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                                {{-- Participant type toggle --}}
                                                <div class="flex items-center gap-1 ml-0.5">
                                                    <span class="text-[11px] text-gray-400 mr-1">Роль:</span>
                                                    <button type="button"
                                                            @click="approver.participant_type = 'signatory'"
                                                            :class="approver.participant_type !== 'process' ? 'bg-[#5B4FE8] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                                            class="text-[11px] px-2.5 py-0.5 rounded-l-full font-medium transition-colors border border-r-0"
                                                            :style="approver.participant_type !== 'process' ? 'border-color:#5B4FE8' : 'border-color:#e5e7eb'">
                                                        Подписант
                                                    </button>
                                                    <button type="button"
                                                            @click="approver.participant_type = 'process'"
                                                            :class="approver.participant_type === 'process' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                                            class="text-[11px] px-2.5 py-0.5 rounded-r-full font-medium transition-colors border"
                                                            :style="approver.participant_type === 'process' ? 'border-color:#f97316' : 'border-color:#e5e7eb'">
                                                        Процесс
                                                    </button>
                                                    <span x-show="approver.participant_type === 'process'" class="text-[11px] text-orange-500 ml-1">(только Одобрить / Не одобрить)</span>
                                                </div>
                                                <input type="hidden" :name="`stages[${index}][approvers][${ai}][participant_type]`" :value="approver.participant_type">
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

                {{-- Folders --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 mt-4">
                    <p class="text-sm font-semibold text-gray-700 mb-3">Папки</p>
                    @php $selectedFolderIds = $workflow->folders->pluck('id')->all(); @endphp
                    <div class="space-y-2">
                        @foreach($folderTree as $rootFolder)
                            <div>
                                <p class="text-xs font-semibold text-gray-500 mb-1.5 flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                                    {{ $rootFolder->name }}
                                </p>
                                <div class="flex flex-wrap gap-2 pl-1">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                        <input type="checkbox" name="folder_ids[]" value="{{ $rootFolder->id }}"
                                               class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"
                                               {{ in_array($rootFolder->id, $selectedFolderIds) ? 'checked' : '' }}>
                                        <span class="text-sm text-gray-700">{{ $rootFolder->name }}</span>
                                    </label>
                                    @foreach($rootFolder->children as $child)
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                            <input type="checkbox" name="folder_ids[]" value="{{ $child->id }}"
                                                   class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"
                                                   {{ in_array($child->id, $selectedFolderIds) ? 'checked' : '' }}>
                                            <span class="text-sm text-gray-700">{{ $child->name }}</span>
                                        </label>
                                        @foreach($child->children as $grandchild)
                                            <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                                <input type="checkbox" name="folder_ids[]" value="{{ $grandchild->id }}"
                                                       class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"
                                                       {{ in_array($grandchild->id, $selectedFolderIds) ? 'checked' : '' }}>
                                                <span class="text-sm text-gray-500">{{ $child->name }} / {{ $grandchild->name }}</span>
                                            </label>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                            @if(!$loop->last)
                                <div class="border-t border-gray-100 my-2"></div>
                            @endif
                        @endforeach
                        @if($folderTree->isEmpty())
                            <p class="text-sm text-gray-400">Папки не созданы.</p>
                        @endif
                    </div>
                </div>

                {{-- Who can create the process --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 mt-4 space-y-5">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Кто может создать процесс</label>
                        <div class="relative" @click.outside="deptOpen = false">
                            <div class="min-h-[46px] w-full border border-gray-200 rounded-lg px-3 py-2 flex flex-wrap gap-1.5 cursor-text focus-within:ring-2 focus-within:ring-[#5B4FE8]"
                                 @click="deptOpen = true">
                                <template x-for="dept in selectedDepts" :key="dept.id">
                                    <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-md px-2 py-1">
                                        <span x-text="dept.name"></span>
                                        <button type="button" @click.stop="removeDept(dept.id)" class="hover:opacity-70 ml-0.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </span>
                                </template>
                                <input type="text" x-model="deptSearch" @focus="deptOpen = true"
                                       placeholder="Поиск отдела…"
                                       class="flex-1 min-w-[160px] text-sm outline-none bg-transparent py-0.5">
                            </div>
                            <div x-show="deptOpen && filteredDepts.length > 0" x-transition
                                 class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                                <template x-for="dept in filteredDepts" :key="dept.id">
                                    <div @click="addDept(dept)"
                                         class="px-4 py-2.5 text-sm text-gray-700 hover:bg-[#5B4FE8]/5 cursor-pointer" x-text="dept.name"></div>
                                </template>
                            </div>
                        </div>
                        <template x-for="dept in selectedDepts" :key="'di-' + dept.id">
                            <input type="hidden" name="allowed_department_ids[]" :value="dept.id">
                        </template>
                        <p class="mt-1.5 text-xs text-gray-400">Если не выбрать отдел — процесс доступен всем.</p>
                    </div>

                    <div x-show="selectedDepts.length > 0" x-transition>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Конкретные сотрудники <span class="font-normal normal-case text-gray-400">(необязательно)</span></label>
                        <div class="relative" @click.outside="userOpen = false">
                            <div class="min-h-[46px] w-full border border-gray-200 rounded-lg px-3 py-2 flex flex-wrap gap-1.5 cursor-text focus-within:ring-2 focus-within:ring-[#5B4FE8]"
                                 @click="userOpen = true">
                                <template x-for="user in selectedUsers" :key="'su-' + user.id">
                                    <span class="inline-flex items-center gap-1 bg-[#5B4FE8]/10 text-[#5B4FE8] text-xs font-medium rounded-md px-2 py-1">
                                        <span x-text="user.name"></span>
                                        <button type="button" @click.stop="removeUser(user.id)" class="hover:opacity-70 ml-0.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </span>
                                </template>
                                <input type="text" x-model="userSearch" @focus="userOpen = true"
                                       placeholder="Поиск сотрудника…"
                                       class="flex-1 min-w-[160px] text-sm outline-none bg-transparent py-0.5">
                            </div>
                            <div x-show="userOpen && filteredUsers.length > 0" x-transition
                                 class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                                <template x-for="user in filteredUsers" :key="'uo-' + user.id">
                                    <div @click="addUser(user)"
                                         class="px-4 py-2.5 text-sm text-gray-700 hover:bg-[#5B4FE8]/5 cursor-pointer flex items-center gap-2.5">
                                        <div class="w-7 h-7 shrink-0 rounded-full bg-[#5B4FE8]/20 flex items-center justify-center text-[#5B4FE8] text-xs font-semibold" x-text="user.name.charAt(0).toUpperCase()"></div>
                                        <div>
                                            <div x-text="user.name" class="font-medium leading-tight"></div>
                                            <div x-show="user.deptName" x-text="user.deptName" class="text-xs text-gray-400 leading-tight"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <template x-for="user in selectedUsers" :key="'ui-' + user.id">
                            <input type="hidden" name="allowed_user_ids[]" :value="user.id">
                        </template>
                        <p class="mt-1.5 text-xs text-gray-400">Если не выбрать — доступно всему отделу</p>
                    </div>
                </div>

                {{-- Process fields --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 mt-4">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-gray-700">Поля процесса</p>
                        <button @click="addField()" type="button"
                                class="flex items-center gap-1.5 text-sm text-[#5B4FE8] font-medium hover:underline">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Добавить поле
                        </button>
                    </div>

                    <div x-show="processFields.length === 0" class="text-sm text-gray-400 text-center py-6 border-2 border-dashed border-gray-100 rounded-xl">
                        Нет полей. Нажмите «Добавить поле».
                    </div>

                    <div class="space-y-2">
                        <template x-for="(field, fi) in processFields" :key="fi">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="`process_fields[${fi}][name]`" x-model="field.name"
                                       placeholder="Название поля"
                                       class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8]">
                                <select :name="`process_fields[${fi}][type]`" x-model="field.type"
                                        class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5B4FE8] bg-white">
                                    <option value="string">Строка</option>
                                    <option value="number">Число</option>
                                    <option value="date">Дата</option>
                                    <option value="file">Файл</option>
                                </select>
                                <button @click="removeField(fi)" type="button" class="p-2 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50 shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </template>
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
                    <template x-for="(stage, index) in stages" :key="stage._key">
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

    @php
        $stagesData = $workflow->stages->sortBy('sort_order')->map(fn($s) => [
            'id'             => $s->id,
            '_key'           => 'db-' . $s->id,
            'name'           => $s->name,
            'stage_type'     => $s->stage_type,
            'deadline_hours' => $s->deadline_hours,
            'approvers'      => $s->approvers->map(fn($a) => ['approver_id' => $a->approver_id, 'participant_type' => $a->participant_type ?? 'signatory'])->values()->toArray(),
        ])->values();

        $allowedDeptIds = $workflow->allowed_departments ?? [];
        $initialDepts   = $departments->whereIn('id', $allowedDeptIds)->map(fn($d) => ['id' => $d->id, 'name' => $d->name])->values();
        $allowedUserIds = $workflow->allowed_users ?? [];
        $initialUsers   = $usersForJs->whereIn('id', $allowedUserIds)->values();
    @endphp

    <script>
    function workflowBuilder() {
        return {
            stages: @json($stagesData),
            processFields: @json($workflow->process_fields ?? []),

            selectedDepts: @json($initialDepts),
            deptSearch: '',
            deptOpen: false,
            allDepts: @json($departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name])->values()),

            selectedUsers: @json($initialUsers),
            userSearch: '',
            userOpen: false,
            allUsers: @json($usersForJs),

            get filteredDepts() {
                const q = this.deptSearch.toLowerCase();
                return this.allDepts.filter(d =>
                    d.name.toLowerCase().includes(q)
                    && !this.selectedDepts.find(s => s.id === d.id)
                );
            },
            get filteredUsers() {
                const q = this.userSearch.toLowerCase();
                const deptIds = this.selectedDepts.map(d => d.id);
                return this.allUsers.filter(u =>
                    deptIds.includes(u.department_id)
                    && u.name.toLowerCase().includes(q)
                    && !this.selectedUsers.find(s => s.id === u.id)
                );
            },
            addDept(dept) {
                this.selectedDepts.push(dept);
                this.deptSearch = '';
                this.deptOpen = false;
            },
            removeDept(id) {
                this.selectedDepts = this.selectedDepts.filter(d => d.id !== id);
                const remainingDeptIds = this.selectedDepts.map(d => d.id);
                this.selectedUsers = this.selectedUsers.filter(u => remainingDeptIds.includes(u.department_id));
            },
            addUser(user) {
                this.selectedUsers.push(user);
                this.userSearch = '';
                this.userOpen = false;
            },
            removeUser(id) {
                this.selectedUsers = this.selectedUsers.filter(u => u.id !== id);
            },
            addField() {
                this.processFields.push({ name: '', type: 'string' });
            },
            removeField(index) {
                this.processFields.splice(index, 1);
            },
            addStage() {
                this.stages.push({ id: null, _key: 'new-' + Date.now() + '-' + Math.random(), name: '', stage_type: 'sequential', deadline_hours: 24, approvers: [] });
            },
            removeStage(index) {
                this.stages.splice(index, 1);
            },
            addApprover(stageIndex) {
                this.stages[stageIndex].approvers.push({ approver_id: '', participant_type: 'signatory' });
            },
            removeApprover(stageIndex, approverIndex) {
                this.stages[stageIndex].approvers.splice(approverIndex, 1);
            },
        }
    }
    </script>
</x-app-layout>
