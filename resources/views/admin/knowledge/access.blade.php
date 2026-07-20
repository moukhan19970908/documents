@php
    // Плоский список отделов и направлений для выбора.
    $flatDepts = collect();
    foreach ($directions as $dir) {
        $flatDepts->push(['id' => $dir->id, 'name' => $dir->name . ' (направление)']);
        foreach ($dir->children as $child) {
            $flatDepts->push(['id' => $child->id, 'name' => $dir->name . ' → ' . $child->name]);
        }
    }
@endphp

<x-app-layout>
    <x-slot name="title">Доступ: {{ $material->title }} — Vamin</x-slot>

    <div x-data="accessForm({
            depts: @js($flatDepts->values()),
            users: @js($users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values()),
            selectedDepts: @js($material->accessDepartments->pluck('id')->values()),
            selectedUsers: @js($material->allowedUsers->pluck('id')->values()),
            level: @js($material->access_level),
            general: @js((bool) $material->is_general),
         })"
         class="max-w-3xl">

        <a href="{{ route('knowledge.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← К материалам</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">Управление доступом</h1>
        <p class="text-sm text-gray-400 mb-5">Материал: «{{ $material->title }}»</p>

        @if(session('success'))
            <div class="mb-4 bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.knowledge.access.update', $material) }}" class="space-y-5">
            @csrf @method('PUT')

            <input type="hidden" name="access_level" :value="level">
            <input type="hidden" name="is_general" :value="general ? 1 : 0">
            <template x-for="id in selectedDepts" :key="id"><input type="hidden" name="departments[]" :value="id"></template>
            <template x-for="id in selectedUsers" :key="id"><input type="hidden" name="users[]" :value="id"></template>

            {{-- Общее для всех --}}
            <label class="flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-5 py-4 cursor-pointer">
                <input type="checkbox" x-model="general" class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                <span>
                    <span class="text-sm font-semibold text-gray-900">Общее для всех</span>
                    <span class="block text-xs text-gray-400">Материал увидят все сотрудники — правила ниже игнорируются.</span>
                </span>
            </label>

            {{-- Доступ по структуре --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5" :class="general && 'opacity-50 pointer-events-none'">
                <h2 class="font-semibold text-gray-900 mb-4">Доступ по структуре</h2>

                <label class="text-xs text-gray-500 block mb-1.5">Отдел / направление</label>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <template x-for="id in selectedDepts" :key="id">
                        <span class="inline-flex items-center gap-1.5 bg-indigo-50 text-[#5B4FE8] text-sm px-2.5 py-1 rounded-lg">
                            <span x-text="deptName(id)"></span>
                            <button type="button" @click="removeDept(id)" class="text-[#5B4FE8]/60 hover:text-[#5B4FE8]">✕</button>
                        </span>
                    </template>
                    <span x-show="selectedDepts.length === 0" class="text-sm text-gray-400">Отделы не выбраны</span>
                </div>
                <select @change="addDept($event.target.value); $event.target.value = ''"
                        class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <option value="">+ Добавить отдел / направление</option>
                    <template x-for="d in availableDepts()" :key="d.id">
                        <option :value="d.id" x-text="d.name"></option>
                    </template>
                </select>

                <label class="text-xs text-gray-500 block mt-5 mb-1.5">Уровень иерархии</label>
                <div class="flex gap-2">
                    @foreach($levels as $code => $label)
                        <button type="button" @click="level = '{{ $code }}'"
                                :class="level === '{{ $code }}' ? 'border-[#5B4FE8] bg-indigo-50 text-[#5B4FE8]' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                class="px-4 py-2 rounded-lg border text-sm font-medium transition">{{ $label }}</button>
                    @endforeach
                </div>

                <div class="mt-4 bg-indigo-50/60 border border-indigo-100 rounded-lg px-4 py-3">
                    <p class="text-sm text-gray-600" x-text="ruleHint()"></p>
                </div>
            </div>

            {{-- Точечный доступ --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5" x-data="{ q: '' }">
                <h2 class="font-semibold text-gray-900">Точечный доступ</h2>
                <p class="text-xs text-gray-400 mb-3">Дополнительно к правилу выше — конкретные сотрудники из справочника.</p>

                <div class="relative">
                    <input type="text" x-model="q" placeholder="Найти сотрудника…"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <div x-show="q.length > 0" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto">
                        <template x-for="u in userMatches(q)" :key="u.id">
                            <button type="button" @click="addUser(u.id); q = ''"
                                    class="w-full text-left text-sm px-3 py-2 hover:bg-gray-50" x-text="u.name"></button>
                        </template>
                        <p x-show="userMatches(q).length === 0" class="text-sm text-gray-400 px-3 py-2">Ничего не найдено</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-3">
                    <template x-for="id in selectedUsers" :key="id">
                        <span class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-700 text-sm px-2.5 py-1 rounded-lg">
                            <span x-text="userName(id)"></span>
                            <button type="button" @click="removeUser(id)" class="text-gray-400 hover:text-red-500">✕</button>
                        </span>
                    </template>
                    <span x-show="selectedUsers.length === 0" class="text-sm text-gray-400">Сотрудники не добавлены</span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сохранить доступ</button>
                <a href="{{ route('knowledge.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
            </div>
        </form>
    </div>

    <script>
        function accessForm(cfg) {
            return {
                depts: cfg.depts,
                users: cfg.users,
                selectedDepts: (cfg.selectedDepts ?? []).map(Number),
                selectedUsers: (cfg.selectedUsers ?? []).map(Number),
                level: cfg.level ?? 'employees',
                general: cfg.general ?? false,

                availableDepts() {
                    return this.depts.filter(d => !this.selectedDepts.includes(Number(d.id)));
                },
                addDept(id) {
                    id = Number(id);
                    if (id && !this.selectedDepts.includes(id)) this.selectedDepts.push(id);
                },
                removeDept(id) {
                    this.selectedDepts = this.selectedDepts.filter(x => x !== Number(id));
                },
                deptName(id) {
                    return this.depts.find(d => Number(d.id) === Number(id))?.name ?? ('#' + id);
                },

                userMatches(q) {
                    q = q.toLowerCase();
                    return this.users
                        .filter(u => !this.selectedUsers.includes(Number(u.id)) && u.name.toLowerCase().includes(q))
                        .slice(0, 20);
                },
                addUser(id) {
                    id = Number(id);
                    if (id && !this.selectedUsers.includes(id)) this.selectedUsers.push(id);
                },
                removeUser(id) {
                    this.selectedUsers = this.selectedUsers.filter(x => x !== Number(id));
                },
                userName(id) {
                    return this.users.find(u => Number(u.id) === Number(id))?.name ?? ('#' + id);
                },

                ruleHint() {
                    const labels = { employees: 'сотрудники', managers: 'руководители', directors: 'директора' };
                    if (this.general) return 'Материал увидят все сотрудники компании.';
                    if (this.selectedDepts.length === 0) return 'Выберите хотя бы один отдел или добавьте сотрудников точечно.';
                    const names = this.selectedDepts.map(id => this.deptName(id)).join(', ');
                    return `«${names}», уровень «${labels[this.level]}» — материал увидят ${labels[this.level]} выбранных подразделений.`;
                },
            };
        }
    </script>
</x-app-layout>
