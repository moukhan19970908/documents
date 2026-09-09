<x-app-layout>
    <x-slot name="title">Сотрудники — Vamin.ru</x-slot>

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Сотрудники</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $totalUsers }} активных сотрудников</p>
        </div>
        {{-- Legend --}}
        <div class="flex items-center gap-3 text-[11px] text-gray-500 flex-wrap">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-200 border border-amber-300 inline-block"></span> Организация</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-cyan-100 border border-cyan-300 inline-block"></span> Дирекция</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-white border border-gray-200 inline-block"></span> Отдел</span>
            <span class="flex items-center gap-1.5"><span class="text-[9px] font-bold px-1 rounded bg-blue-100 text-blue-600 leading-none">Б24</span> Из Битрикс24</span>
        </div>
        @if($canManage)
            <div class="flex items-center gap-2 flex-shrink-0" x-data>
                <button type="button"
                        @click="$dispatch('user-create')"
                        class="flex items-center gap-2 border border-gray-200 bg-white text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Добавить сотрудника
                </button>
                <button type="button"
                        @click="$dispatch('dept-form', { mode: 'create' })"
                        class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Добавить отдел
                </button>
            </div>
        @endif
    </div>

    @if($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tabs --}}
    <div x-data="{ tab: 'chart' }" class="space-y-6">
        <div class="flex gap-1 bg-gray-100 rounded-lg p-1 w-fit">
            <button @click="tab = 'chart'"
                :class="tab === 'chart' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition-all">
                Схема
            </button>
            <button @click="tab = 'list'"
                :class="tab === 'list' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition-all">
                Список
            </button>
        </div>

        {{-- ── ORG CHART TAB ── --}}
        <div x-show="tab === 'chart'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="bg-[#F5F8FF] rounded-2xl border border-gray-200 shadow-sm p-8 overflow-auto">
                @if($tree->isEmpty())
                    <div class="py-20 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-5.197-3.787M9 20H4v-2a4 4 0 015.197-3.787M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="font-medium text-gray-500">Отделы не найдены</p>
                        <p class="text-sm text-gray-400 mt-1">Синхронизируйте данные из Битрикс24 или добавьте отделы вручную.</p>
                    </div>
                @else
                    <div class="inline-flex flex-row gap-16 justify-center items-start min-w-max mx-auto pb-4">
                        @foreach($tree as $rootDept)
                            <x-org-node :dept="$rootDept" :depth="0" :can-manage="$canManage" />
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ── LIST TAB ── --}}
        <div x-show="tab === 'list'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @php
                $roleMap    = ['admin' => 'Администратор', 'director' => 'Директор', 'linear' => 'Сотрудник', 'archiver' => 'Архивариус'];
                $roleColors = ['admin' => 'bg-purple-50 text-purple-700', 'director' => 'bg-blue-50 text-blue-700', 'linear' => 'bg-gray-100 text-gray-600', 'archiver' => 'bg-amber-50 text-amber-700'];
                $allUsers   = \App\Models\User::with('department')->where('is_active', true)->orderBy('name')->get();
            @endphp

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">Список сотрудников</h2>
                    <span class="text-xs text-gray-400">{{ $totalUsers }} чел.</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold">Сотрудник</th>
                                <th class="px-6 py-3 text-left font-semibold">Отдел</th>
                                <th class="px-6 py-3 text-left font-semibold">Должность</th>
                                <th class="px-6 py-3 text-left font-semibold">Email</th>
                                <th class="px-6 py-3 text-left font-semibold">Статус</th>
                                @if($canManage)
                                    <th class="px-6 py-3 text-right font-semibold">Действия</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($allUsers as $user)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 bg-indigo-100 flex items-center justify-center">
                                                @if($user->avatar)
                                                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                                                @else
                                                    <span class="text-xs font-bold text-indigo-600">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                                @endif
                                            </div>
                                            <span class="font-medium text-gray-900">{{ $user->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-gray-600">{{ $user->department?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $user->position ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-500">{{ $user->email ?? '—' }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                            Активен
                                        </span>
                                    </td>
                                    @if($canManage)
                                        <td class="px-6 py-3">
                                            <div class="flex items-center justify-end gap-3">
                                                <button type="button"
                                                        @click="$dispatch('user-form', @js(['id' => $user->id, 'name' => $user->name, 'department_id' => $user->department_id, 'department_name' => $user->department?->name]))"
                                                        class="text-[#5B4FE8] text-xs font-medium hover:underline">Перевести</button>
                                                <button type="button"
                                                        @click="$dispatch('user-password', @js(['id' => $user->id, 'name' => $user->name]))"
                                                        class="text-[#5B4FE8] text-xs font-medium hover:underline">Пароль</button>
                                                @if($user->id !== auth()->id())
                                                    <form action="{{ route('employees.users.deactivate', $user) }}" method="POST"
                                                          onsubmit="return confirm('Деактивировать сотрудника ' + @js($user->name) + '?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-500 text-xs font-medium hover:underline">Деактивировать</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManage ? 6 : 5 }}" class="px-6 py-10 text-center text-gray-400">Сотрудники не найдены</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    {{-- ── DEPARTMENT FORM MODAL (admin only) ── --}}
    @if($canManage)
        <div x-data="{
                 open: false,
                 mode: 'create',
                 id: null,
                 name: '',
                 parentId: '',
                 headId: '',
                 parentName: '',
                 bitrix: false,
                 show(d) {
                     this.mode       = d.mode;
                     this.id         = d.id ?? null;
                     this.name       = d.name ?? '';
                     this.parentId   = d.parent_id ?? '';
                     this.headId     = d.head_user_id ?? '';
                     this.parentName = d.parent_name ?? '';
                     this.bitrix     = d.bitrix ?? false;
                     this.open       = true;
                     this.$nextTick(() => this.$refs.name.focus());
                 }
             }"
             @dept-form.window="show($event.detail)"
             @keydown.escape.window="open = false"
             x-cloak>
            <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/40" @click="open = false"></div>

                <form method="POST"
                      :action="mode === 'edit' ? '{{ url('employees/departments') }}/' + id : '{{ route('employees.departments.store') }}'"
                      class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
                    @csrf
                    <input type="hidden" name="_method" :value="mode === 'edit' ? 'PUT' : 'POST'">

                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900" x-text="mode === 'edit' ? 'Редактировать отдел' : 'Новый отдел'"></h2>
                        <p class="text-xs text-gray-400 mt-0.5" x-show="mode === 'create' && parentName">
                            Внутри отдела «<span x-text="parentName"></span>»
                        </p>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название *</label>
                            <input type="text" name="name" x-model="name" x-ref="name" required maxlength="255"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Родительский отдел</label>
                            <select name="parent_id" x-model="parentId"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— Нет (корневой отдел) —</option>
                                @foreach($formDepartments as $d)
                                    <option value="{{ $d->id }}" :disabled="id === {{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Руководитель</label>
                            <select name="head_user_id" x-model="headId"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— Не назначен —</option>
                                @foreach($formUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <p x-show="bitrix" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            Отдел синхронизируется с Битрикс24: название и подчинение будут перезаписаны при следующей синхронизации.
                        </p>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="button" @click="open = false"
                                class="px-5 py-2 border border-gray-200 bg-white text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700"
                                x-text="mode === 'edit' ? 'Сохранить' : 'Создать отдел'"></button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── USER TRANSFER MODAL (admin only) ── --}}
    @if($canManage)
        <div x-data="{
                 open: false,
                 id: null,
                 name: '',
                 deptId: '',
                 deptName: '',
                 show(d) {
                     this.id       = d.id;
                     this.name     = d.name;
                     this.deptId   = d.department_id ?? '';
                     this.deptName = d.department_name ?? '';
                     this.open     = true;
                 }
             }"
             @user-form.window="show($event.detail)"
             @keydown.escape.window="open = false"
             x-cloak>
            <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/40" @click="open = false"></div>

                <form method="POST" :action="'{{ url('employees/users') }}/' + id"
                      class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
                    @csrf
                    @method('PUT')

                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">Перевести сотрудника</h2>
                        <p class="text-xs text-gray-400 mt-0.5" x-text="name"></p>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Текущий отдел</label>
                            <p class="text-sm text-gray-500" x-text="deptName || '— Без отдела —'"></p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Новый отдел</label>
                            <select name="department_id" x-model="deptId"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— Без отдела —</option>
                                @foreach($formDepartments as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            Для сотрудников из Битрикс24 отдел будет перезаписан при следующей синхронизации.
                        </p>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="button" @click="open = false"
                                class="px-5 py-2 border border-gray-200 bg-white text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Перевести</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── NEW EMPLOYEE MODAL (admin only) ── --}}
    @if($canManage)
        <div x-data="{ open: false }"
             @user-create.window="open = true; $nextTick(() => $refs.newName.focus())"
             @keydown.escape.window="open = false"
             x-cloak>
            <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/40" @click="open = false"></div>

                <form method="POST" action="{{ route('employees.users.store') }}"
                      class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
                    @csrf

                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">Новый сотрудник</h2>
                    </div>

                    <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">ФИО *</label>
                            <input type="text" name="name" x-ref="newName" required maxlength="255"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Email *</label>
                            <input type="email" name="email" required
                                   class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Пароль *</label>
                            <input type="password" name="password" required minlength="8" autocomplete="new-password"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <p class="text-xs text-gray-400 mt-1">Минимум 8 символов.</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Повторите пароль *</label>
                            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Роль *</label>
                            <select name="role" required
                                    class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                @foreach($formRoles as $r)
                                    <option value="{{ $r->code }}" @selected($r->code === 'linear')>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Отдел</label>
                            <select name="department_id"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— Без отдела —</option>
                                @foreach($formDepartments as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Должность</label>
                            <input type="text" name="position" maxlength="255"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="button" @click="open = false"
                                class="px-5 py-2 border border-gray-200 bg-white text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Добавить</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── PASSWORD MODAL (admin only) ── --}}
    @if($canManage)
        <div x-data="{
                 open: false,
                 id: null,
                 name: '',
                 show(d) {
                     this.id   = d.id;
                     this.name = d.name;
                     this.open = true;
                     this.$nextTick(() => this.$refs.pwd.focus());
                 }
             }"
             @user-password.window="show($event.detail)"
             @keydown.escape.window="open = false"
             x-cloak>
            <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/40" @click="open = false"></div>

                <form method="POST" :action="'{{ url('employees/users') }}/' + id + '/password'"
                      class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
                    @csrf
                    @method('PUT')

                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">Изменить пароль</h2>
                        <p class="text-xs text-gray-400 mt-0.5" x-text="name"></p>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Новый пароль *</label>
                            <input type="password" name="password" x-ref="pwd" required minlength="8" autocomplete="new-password"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <p class="text-xs text-gray-400 mt-1">Минимум 8 символов.</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Повторите пароль *</label>
                            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="button" @click="open = false"
                                class="px-5 py-2 border border-gray-200 bg-white text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</x-app-layout>
