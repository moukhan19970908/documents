<x-app-layout>
    <x-slot name="title">Тех поддержка — Vamin</x-slot>

    <div x-data="handlersForm({
            users: @js($users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values()),
            selected: @js($handlerIds),
         })"
         class="max-w-2xl">

        <div class="mb-6">
            <a href="{{ route('feedback.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← Обращения</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Тех поддержка</h1>
            <p class="text-sm text-gray-500 mt-1">Кому попадают обращения пользователей.</p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.feedback.settings.update') }}">
            @csrf @method('PUT')
            <template x-for="id in selected" :key="id"><input type="hidden" name="users[]" :value="id"></template>

            <div class="bg-white border border-gray-200 rounded-xl p-5" x-data="{ q: '' }">
                <h2 class="font-semibold text-gray-900">Обработчики обращений</h2>
                <p class="text-xs text-gray-400 mb-3">
                    Видят все обращения, отвечают на них и получают счётчик новых в меню «Тех Поддержка».
                </p>

                <div class="relative">
                    <input type="text" x-model="q" placeholder="Найти сотрудника…"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <div x-show="q.length > 0" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto">
                        <template x-for="u in matches(q)" :key="u.id">
                            <button type="button" @click="add(u.id); q = ''"
                                    class="w-full text-left text-sm px-3 py-2 hover:bg-gray-50" x-text="u.name"></button>
                        </template>
                        <p x-show="matches(q).length === 0" class="text-sm text-gray-400 px-3 py-2">Ничего не найдено</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-3">
                    <template x-for="id in selected" :key="id">
                        <span class="inline-flex items-center gap-1.5 bg-indigo-50 text-[#5B4FE8] text-sm px-2.5 py-1 rounded-lg">
                            <span x-text="name(id)"></span>
                            <button type="button" @click="remove(id)" class="text-[#5B4FE8]/60 hover:text-red-500" aria-label="Убрать">✕</button>
                        </span>
                    </template>
                    <span x-show="selected.length === 0" class="text-sm text-gray-400">
                        Обработчики не назначены — обращения видят только роли с правом «Видеть все обращения».
                    </span>
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сохранить</button>
            </div>
        </form>
    </div>

    <script>
        function handlersForm(cfg) {
            return {
                users: cfg.users,
                selected: (cfg.selected ?? []).map(Number),

                matches(q) {
                    q = q.toLowerCase();
                    return this.users
                        .filter(u => !this.selected.includes(Number(u.id)) && u.name.toLowerCase().includes(q))
                        .slice(0, 20);
                },
                add(id) {
                    id = Number(id);
                    if (id && !this.selected.includes(id)) this.selected.push(id);
                },
                remove(id) {
                    this.selected = this.selected.filter(x => x !== Number(id));
                },
                name(id) {
                    return this.users.find(u => Number(u.id) === Number(id))?.name ?? ('#' + id);
                },
            };
        }
    </script>
</x-app-layout>
