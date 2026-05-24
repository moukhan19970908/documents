<x-app-layout>
    <x-slot name="title">Управление доступами — Vamin</x-slot>

    <div
        x-data="accessControlPage(@js(['users' => $users, 'departments' => $departments, 'logs' => $logs]))"
        class="space-y-6"
    >
        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Управление доступами</h1>
                <p class="mt-1 text-sm text-gray-500">Настройте уровень доступа к страницам для пользователей и департаментов</p>
            </div>
        </div>

        {{-- Section picker --}}
        <div class="flex gap-2">
            <template x-for="s in sections" :key="s.key">
                <button
                    @click="activeSection = s.key"
                    :class="activeSection === s.key
                        ? 'bg-[#5B4FE8] text-white shadow-sm'
                        : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-xl text-sm font-medium transition-colors"
                    x-text="s.label"
                ></button>
            </template>
        </div>

        {{-- Main panel --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            {{-- Tabs + search --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-6 py-4 border-b border-gray-200">
                <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
                    <button
                        @click="activeTab = 'users'"
                        :class="activeTab === 'users' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-1.5 text-sm font-medium rounded-lg transition-all"
                    >
                        Пользователи
                        <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full" :class="activeTab === 'users' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-200 text-gray-500'">{{ count($users) }}</span>
                    </button>
                    <button
                        @click="activeTab = 'departments'"
                        :class="activeTab === 'departments' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-1.5 text-sm font-medium rounded-lg transition-all"
                    >
                        Департаменты
                        <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full" :class="activeTab === 'departments' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-200 text-gray-500'">{{ count($departments) }}</span>
                    </button>
                </div>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0z"/>
                    </svg>
                    <input x-model="search" type="text" placeholder="Поиск..." class="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none w-56">
                </div>
            </div>

            {{-- Users table --}}
            <div x-show="activeTab === 'users'" x-cloak>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователь</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Департамент</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" x-text="currentSection.label"></th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Действие</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="user in filteredUsers" :key="user.id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 text-xs font-semibold text-indigo-700" x-text="user.name.charAt(0)"></div>
                                        <div>
                                            <p class="font-medium text-gray-900" x-text="user.name"></p>
                                            <p class="text-xs text-gray-500" x-text="user.email"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="text-gray-600" x-text="user.department || '—'"></span>
                                    <span class="text-xs text-gray-400 ml-1" x-text="user.role ? '· ' + user.role : ''"></span>
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="flex flex-col gap-0.5">
                                        <span
                                            class="inline-flex w-fit items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="levelClass(userEffectiveLevel(user))"
                                            x-text="levelLabel(userEffectiveLevel(user))"
                                        ></span>
                                        <span x-show="!userRawLevel(user)" class="text-xs text-gray-400">унаследовано</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-right">
                                    <button @click="openModal('user', user)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors">Изменить</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredUsers.length === 0">
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400">Ничего не найдено</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Departments table --}}
            <div x-show="activeTab === 'departments'" x-cloak>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Департамент</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сотрудников</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" x-text="currentSection.label"></th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Действие</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="dept in filteredDepts" :key="dept.id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                        </div>
                                        <p class="font-medium text-gray-900" x-text="dept.name"></p>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-gray-600" x-text="dept.member_count + ' чел.'"></td>
                                <td class="px-6 py-3.5">
                                    <span
                                        class="inline-flex w-fit items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="levelClass(deptRawLevel(dept))"
                                        x-text="levelLabel(deptRawLevel(dept))"
                                    ></span>
                                </td>
                                <td class="px-6 py-3.5 text-right">
                                    <button @click="openModal('department', dept)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors">Изменить</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredDepts.length === 0">
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400">Ничего не найдено</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Change log --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Журнал изменений доступа</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Кто, кому, какой уровень и когда</p>
                </div>
                <span class="text-xs text-gray-400" x-text="logs.length + ' записей'"></span>
            </div>
            <div x-show="logs.length > 0" class="divide-y divide-gray-100">
                <template x-for="log in logs" :key="log.id">
                    <div class="px-6 py-3 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2 text-sm flex-wrap">
                            <span class="font-medium text-gray-900" x-text="log.actor"></span>
                            <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                            </svg>
                            <span class="text-gray-700" x-text="log.target"></span>
                            <span class="text-gray-400">:</span>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="levelClass(log.new_level)"
                                x-text="levelLabel(log.new_level)"
                            ></span>
                        </div>
                        <span class="text-xs text-gray-400 flex-shrink-0" x-text="log.timestamp"></span>
                    </div>
                </template>
            </div>
            <div x-show="logs.length === 0" class="px-6 py-10 text-center text-sm text-gray-400">
                История изменений пуста
            </div>
        </div>

        {{-- Modal --}}
        <div
            x-show="modal.open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @click.self="modal.open = false"
        >
            <div
                x-show="modal.open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="bg-white rounded-2xl shadow-xl w-full max-w-md"
                @click.stop
            >
                <div class="px-6 py-5 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Доступ к странице: <span x-text="currentSection.label"></span></h3>
                    <p class="mt-0.5 text-sm text-gray-500" x-text="modal.name"></p>
                </div>

                <div class="px-6 py-5 space-y-2">
                    <label
                        class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                        :class="modal.selectedLevel === 'none' ? 'border-red-300 bg-red-50' : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <input type="radio" name="modal_access_level" value="none" x-model="modal.selectedLevel" class="mt-0.5 accent-red-600">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Нет доступа</p>
                            <p class="text-xs text-gray-500 mt-0.5">Страница недоступна</p>
                        </div>
                    </label>
                    <template x-if="activeSection !== 'workflows'">
                        <label
                            class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                            :class="modal.selectedLevel === 'own' ? 'border-amber-300 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'"
                        >
                            <input type="radio" name="modal_access_level" value="own" x-model="modal.selectedLevel" class="mt-0.5 accent-amber-600">
                            <div>
                                <p class="text-sm font-medium text-gray-900" x-text="activeSection === 'tasks' ? 'Свои задачи' : 'Свои архивы'"></p>
                                <p class="text-xs text-gray-500 mt-0.5" x-text="activeSection === 'tasks' ? 'Видит только задачи, назначенные ему' : 'Видит только свои документы в архиве'"></p>
                            </div>
                        </label>
                    </template>
                    <label
                        class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                        :class="modal.selectedLevel === 'department' ? 'border-blue-300 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <input type="radio" name="modal_access_level" value="department" x-model="modal.selectedLevel" class="mt-0.5 accent-blue-600">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Свой департамент</p>
                            <p class="text-xs text-gray-500 mt-0.5">Видит данные только своего отдела</p>
                        </div>
                    </label>
                    <label
                        class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                        :class="modal.selectedLevel === 'full' ? 'border-emerald-300 bg-emerald-50' : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <input type="radio" name="modal_access_level" value="full" x-model="modal.selectedLevel" class="mt-0.5 accent-emerald-600">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Все</p>
                            <p class="text-xs text-gray-500 mt-0.5">Полный доступ ко всем данным</p>
                        </div>
                    </label>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                    <button
                        @click="modal.open = false"
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Отмена
                    </button>
                    <button
                        @click="saveAccess()"
                        :disabled="!modal.selectedLevel"
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Сохранить
                    </button>
                </div>
            </div>
        </div>

        {{-- Toasts --}}
        <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2" aria-live="polite">
            <template x-for="t in toasts" :key="t.id">
                <div
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium text-white max-w-xs"
                    :class="t.type === 'error' ? 'bg-red-600' : 'bg-gray-900'"
                    x-text="t.msg"
                ></div>
            </template>
        </div>
    </div>

    @push('scripts')
    <script>
    function accessControlPage({ users, departments, logs }) {
        return {
            activeTab: 'users',
            activeSection: 'workflows',
            search: '',
            users,
            departments,
            logs,
            toasts: [],
            _toastId: 0,
            modal: {
                open: false,
                scope: 'user',
                id: null,
                name: '',
                selectedLevel: null,
            },

            sections: [
                { key: 'workflows', label: 'Процессы',  userLevelKey: 'workflow_access_level', userEffKey: 'effective_access_level',         deptLevelKey: 'workflow_access_level', userUrl: (id) => `/admin/access-control/users/${id}/workflow-access`,    deptUrl: (id) => `/admin/access-control/departments/${id}/workflow-access` },
                { key: 'tasks',     label: 'Задачи',    userLevelKey: 'tasks_access_level',    userEffKey: 'effective_tasks_access_level',   deptLevelKey: 'tasks_access_level',    userUrl: (id) => `/admin/access-control/users/${id}/tasks-access`,       deptUrl: (id) => `/admin/access-control/departments/${id}/tasks-access` },
                { key: 'archive',   label: 'Архивы',    userLevelKey: 'archive_access_level',  userEffKey: 'effective_archive_access_level', deptLevelKey: 'archive_access_level',  userUrl: (id) => `/admin/access-control/users/${id}/archive-access`,     deptUrl: (id) => `/admin/access-control/departments/${id}/archive-access` },
            ],

            get currentSection() {
                return this.sections.find(s => s.key === this.activeSection);
            },

            userRawLevel(user) {
                return user[this.currentSection.userLevelKey] ?? null;
            },

            userEffectiveLevel(user) {
                return user[this.currentSection.userEffKey] ?? null;
            },

            deptRawLevel(dept) {
                return dept[this.currentSection.deptLevelKey] ?? null;
            },

            get filteredUsers() {
                if (!this.search) return this.users;
                const q = this.search.toLowerCase();
                return this.users.filter(u =>
                    u.name.toLowerCase().includes(q) ||
                    u.email.toLowerCase().includes(q) ||
                    (u.department || '').toLowerCase().includes(q)
                );
            },

            get filteredDepts() {
                if (!this.search) return this.departments;
                const q = this.search.toLowerCase();
                return this.departments.filter(d => d.name.toLowerCase().includes(q));
            },

            levelLabel(level) {
                return { full: 'Все', department: 'Свой департамент', own: 'Свои', none: 'Нет доступа' }[level] ?? 'Не настроен';
            },

            levelClass(level) {
                return {
                    full:       'bg-emerald-100 text-emerald-700',
                    department: 'bg-blue-100 text-blue-700',
                    own:        'bg-amber-100 text-amber-700',
                    none:       'bg-red-100 text-red-700',
                }[level] ?? 'bg-gray-100 text-gray-500';
            },

            openModal(scope, entity) {
                const levelKey = scope === 'user'
                    ? this.currentSection.userLevelKey
                    : this.currentSection.deptLevelKey;
                this.modal.scope         = scope;
                this.modal.id            = entity.id;
                this.modal.name          = entity.name;
                this.modal.selectedLevel = entity[levelKey] ?? null;
                this.modal.open          = true;
            },

            async saveAccess() {
                if (!this.modal.selectedLevel) {
                    this.toast('Выберите уровень доступа', 'error');
                    return;
                }
                const { scope, id, name, selectedLevel } = this.modal;
                const section = this.currentSection;
                const url = scope === 'user' ? section.userUrl(id) : section.deptUrl(id);

                const ok = await this.post(url, { access_level: selectedLevel });
                if (!ok) return;

                if (scope === 'user') {
                    const u = this.users.find(u => u.id === id);
                    if (u) {
                        u[section.userLevelKey] = selectedLevel;
                        u[section.userEffKey]   = selectedLevel;
                    }
                } else {
                    const d = this.departments.find(d => d.id === id);
                    if (d) d[section.deptLevelKey] = selectedLevel;
                }
                this.modal.open = false;
                this.toast(`Доступ обновлён: ${name} → ${this.levelLabel(selectedLevel)}`);
            },

            async post(url, body) {
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: JSON.stringify(body),
                    });
                    if (!res.ok) {
                        const data = await res.json().catch(() => ({}));
                        this.toast(data.message ?? 'Ошибка сервера', 'error');
                        return false;
                    }
                    return true;
                } catch {
                    this.toast('Нет соединения', 'error');
                    return false;
                }
            },

            toast(msg, type = 'success') {
                const id = ++this._toastId;
                this.toasts.push({ id, msg, type });
                setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3500);
            },
        };
    }
    </script>
    @endpush
</x-app-layout>