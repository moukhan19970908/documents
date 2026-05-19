<x-app-layout>
    <x-slot name="title">Новый маршрут — Vamin</x-slot>

    <div class="max-w-2xl" x-data="workflowCreate()">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('workflows.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Новый маршрут</h1>
        </div>

        <form id="workflow-form" action="{{ route('workflows.store') }}" method="POST" class="space-y-5" @submit="validateAndSubmit">
            @csrf

            {{-- Card 1: Base info --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название *</label>
                    <input type="text" name="name" id="field-name" value="{{ old('name') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]"
                           placeholder="Например: Стандартное согласование">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Описание</label>
                    <textarea name="description" rows="3"
                              class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]"
                              placeholder="Краткое описание маршрута">{{ old('description') }}</textarea>
                </div>
            </div>

            {{-- Card 1b: Folders --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-xs font-semibold text-gray-600 uppercase tracking-widest mb-3">Папки</h2>
                <div class="space-y-2">
                    @foreach($folderTree as $rootFolder)
                        <div>
                            <p class="text-xs font-semibold text-gray-500 mb-1.5 flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                                {{ $rootFolder->name }}
                            </p>
                            <div class="flex flex-wrap gap-2 pl-1">
                                {{-- Root folder itself as option --}}
                                <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                    <input type="checkbox" name="folder_ids[]" value="{{ $rootFolder->id }}"
                                           class="rounded border-gray-300 text-[#6C5CE7] focus:ring-[#6C5CE7]"
                                           {{ in_array($rootFolder->id, old('folder_ids', [])) ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-700">{{ $rootFolder->name }}</span>
                                </label>
                                {{-- Sub-folders --}}
                                @foreach($rootFolder->children as $child)
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                        <input type="checkbox" name="folder_ids[]" value="{{ $child->id }}"
                                               class="rounded border-gray-300 text-[#6C5CE7] focus:ring-[#6C5CE7]"
                                               {{ in_array($child->id, old('folder_ids', [])) ? 'checked' : '' }}>
                                        <span class="text-sm text-gray-700">{{ $child->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @if(!$loop->last)
                            <div class="border-t border-gray-100 my-2"></div>
                        @endif
                    @endforeach
                    @if($folderTree->isEmpty())
                        <p class="text-sm text-gray-400">
                            Папки не созданы.
                            @if(auth()->user()->role === 'admin')
                                <a href="{{ route('admin.workflow-folders.create') }}" class="text-[#6C5CE7] hover:underline">Создать папки</a>
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            {{-- Card 2: Approvers, Departments, Approval type --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

                {{-- Выбор согласующих --}}
                <div  style="display: none;">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Выбор согласующих</label>
                    <div class="relative" @click.outside="approverOpen = false">
                        <div class="min-h-[46px] w-full border border-gray-200 rounded-lg px-3 py-2 flex flex-wrap gap-1.5 cursor-text focus-within:ring-2 focus-within:ring-[#6C5CE7]"
                             @click="approverOpen = true">
                            <template x-for="approver in approvers" :key="approver.id">
                                <span class="inline-flex items-center gap-1 bg-[#6C5CE7]/10 text-[#6C5CE7] text-xs font-medium rounded-md px-2 py-1">
                                    <span x-text="approver.name"></span>
                                    <button type="button" @click.stop="removeApprover(approver.id)" class="hover:opacity-70 ml-0.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </span>
                            </template>
                            <input type="text" x-model="approverSearch" @focus="approverOpen = true"
                                   placeholder="Поиск по имени или должности…"
                                   class="flex-1 min-w-[160px] text-sm outline-none bg-transparent py-0.5">
                        </div>
                        <div x-show="approverOpen && filteredApprovers.length > 0"
                             x-transition
                             class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                            <template x-for="user in filteredApprovers" :key="user.id">
                                <div @click="addApprover(user)"
                                     class="px-4 py-2.5 text-sm text-gray-700 hover:bg-[#6C5CE7]/5 cursor-pointer flex items-center gap-2.5">
                                    <div class="w-7 h-7 shrink-0 rounded-full bg-[#6C5CE7]/20 flex items-center justify-center text-[#6C5CE7] text-xs font-semibold" x-text="user.name.charAt(0).toUpperCase()"></div>
                                    <div>
                                        <div x-text="user.name" class="font-medium leading-tight"></div>
                                        <div x-show="user.position" x-text="user.position" class="text-xs text-gray-400 leading-tight"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <template x-for="approver in approvers" :key="'ai-' + approver.id">
                        <input type="hidden" name="approver_ids[]" :value="approver.id">
                    </template>
                </div>

                {{-- Кто может создать процесс --}}
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Кто может создать процесс</label>
                    <div class="relative" @click.outside="deptOpen = false">
                        <div class="min-h-[46px] w-full border border-gray-200 rounded-lg px-3 py-2 flex flex-wrap gap-1.5 cursor-text focus-within:ring-2 focus-within:ring-[#6C5CE7]"
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
                        <div x-show="deptOpen && filteredDepts.length > 0"
                             x-transition
                             class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                            <template x-for="dept in filteredDepts" :key="dept.id">
                                <div @click="addDept(dept)"
                                     class="px-4 py-2.5 text-sm text-gray-700 hover:bg-[#6C5CE7]/5 cursor-pointer" x-text="dept.name">
                                </div>
                            </template>
                        </div>
                    </div>
                    <template x-for="dept in selectedDepts" :key="'di-' + dept.id">
                        <input type="hidden" name="allowed_department_ids[]" :value="dept.id">
                    </template>
                </div>

                {{-- Конкретные сотрудники (опционально) --}}
                <div x-show="selectedDepts.length > 0" x-transition>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Конкретные сотрудники <span class="font-normal normal-case text-gray-400">(необязательно)</span></label>
                    <div class="relative" @click.outside="userOpen = false">
                        <div class="min-h-[46px] w-full border border-gray-200 rounded-lg px-3 py-2 flex flex-wrap gap-1.5 cursor-text focus-within:ring-2 focus-within:ring-[#6C5CE7]"
                             @click="userOpen = true">
                            <template x-for="user in selectedUsers" :key="'su-' + user.id">
                                <span class="inline-flex items-center gap-1 bg-[#6C5CE7]/10 text-[#6C5CE7] text-xs font-medium rounded-md px-2 py-1">
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
                        <div x-show="userOpen && filteredUsers.length > 0"
                             x-transition
                             class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                            <template x-for="user in filteredUsers" :key="'uo-' + user.id">
                                <div @click="addUser(user)"
                                     class="px-4 py-2.5 text-sm text-gray-700 hover:bg-[#6C5CE7]/5 cursor-pointer flex items-center gap-2.5">
                                    <div class="w-7 h-7 shrink-0 rounded-full bg-[#6C5CE7]/20 flex items-center justify-center text-[#6C5CE7] text-xs font-semibold" x-text="user.name.charAt(0).toUpperCase()"></div>
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

                {{-- Тип согласования --}}
                <div  style="display: none;">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-2">Тип согласования *</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="approval_type" value="sequential" x-model="approvalType" class="sr-only">
                            <div :class="approvalType === 'sequential' ? 'border-[#6C5CE7] bg-[#6C5CE7]/5 text-[#6C5CE7]' : 'border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50'"
                                 class="border-2 rounded-xl p-4 text-center transition-all select-none">
                                <div class="flex justify-center mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </div>
                                <span class="text-xs font-semibold">Последовательно</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="approval_type" value="parallel" x-model="approvalType" class="sr-only">
                            <div :class="approvalType === 'parallel' ? 'border-[#6C5CE7] bg-[#6C5CE7]/5 text-[#6C5CE7]' : 'border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50'"
                                 class="border-2 rounded-xl p-4 text-center transition-all select-none">
                                <div class="flex justify-center mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                </div>
                                <span class="text-xs font-semibold">Параллельно</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="approval_type" value="parallel_sequential" x-model="approvalType" class="sr-only">
                            <div :class="approvalType === 'parallel_sequential' ? 'border-[#6C5CE7] bg-[#6C5CE7]/5 text-[#6C5CE7]' : 'border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50'"
                                 class="border-2 rounded-xl p-4 text-center transition-all select-none">
                                <div class="flex justify-center mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h7m5 0h4M4 12h16M4 18h4m5 0h7"/></svg>
                                </div>
                                <span class="text-xs font-semibold leading-tight">Параллельно + Последовательно</span>
                            </div>
                        </label>
                    </div>
                </div>

            </div>

            {{-- Card 3: Process fields --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-800">Поля процесса</h2>
                    <button type="button" @click="addField()"
                            class="flex items-center gap-1.5 text-xs font-medium text-[#6C5CE7] hover:text-indigo-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Добавить поле
                    </button>
                </div>

                <div x-show="processFields.length === 0"
                     class="text-sm text-gray-400 text-center py-8 border-2 border-dashed border-gray-100 rounded-xl">
                    Нет полей. Нажмите «+ Добавить поле» для добавления.
                </div>

                <div class="space-y-2">
                    <template x-for="(field, index) in processFields" :key="index">
                        <div class="flex items-center gap-2">
                            <input type="text" :name="`process_fields[${index}][name]`" x-model="field.name"
                                   placeholder="Название поля"
                                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]">
                            <select :name="`process_fields[${index}][type]`" x-model="field.type"
                                    class="text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7] bg-white">
                                <option value="string">Строка</option>
                                <option value="number">Число</option>
                                <option value="date">Дата</option>
                                <option value="file">Файл</option>
                            </select>
                            <button type="button" @click="removeField(index)"
                                    class="p-2 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50 shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="px-6 py-2.5 bg-[#6C5CE7] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Создать и перейти к настройке
                </button>
                <a href="{{ route('workflows.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                    Отмена
                </a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    function workflowCreate() {
        return {
            approvers: [],
            approverSearch: '',
            approverOpen: false,
            allApprovers: @json($usersForJs),

            selectedDepts: [],
            deptSearch: '',
            deptOpen: false,
            allDepts: @json($departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name])),

            selectedUsers: [],
            userSearch: '',
            userOpen: false,
            allUsers: @json($usersForJs),

            approvalType: '{{ old('approval_type', 'sequential') }}',

            processFields: [],

            get filteredApprovers() {
                const q = this.approverSearch.toLowerCase();
                return this.allApprovers.filter(u =>
                    (u.name.toLowerCase().includes(q) || (u.position && u.position.toLowerCase().includes(q)))
                    && !this.approvers.find(a => a.id === u.id)
                );
            },

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
                    && (u.name.toLowerCase().includes(q))
                    && !this.selectedUsers.find(s => s.id === u.id)
                );
            },

            addApprover(user) {
                this.approvers.push(user);
                this.approverSearch = '';
            },

            removeApprover(id) {
                this.approvers = this.approvers.filter(a => a.id !== id);
            },

            addDept(dept) {
                this.selectedDepts.push(dept);
                this.deptSearch = '';
                this.deptOpen = false;
            },

            removeDept(id) {
                this.selectedDepts = this.selectedDepts.filter(d => d.id !== id);
                // remove users that belonged to this dept if no longer in any selected dept
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

            validateAndSubmit(e) {
                const nameEl = document.getElementById('field-name');
                if (!nameEl.value.trim()) {
                    e.preventDefault();
                    nameEl.focus();
                    nameEl.classList.add('ring-2', 'ring-red-400', 'border-red-300');
                    return;
                }
                for (let i = 0; i < this.processFields.length; i++) {
                    if (!this.processFields[i].name.trim()) {
                        e.preventDefault();
                        document.querySelectorAll('[name^="process_fields["]')[i * 2]?.focus();
                        return;
                    }
                }
            }
        };
    }
    </script>
    @endpush
</x-app-layout>
