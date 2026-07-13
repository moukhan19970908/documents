<x-app-layout>
    <x-slot name="title">{{ isset($role) ? $role->name : 'Новая роль' }} — Vamin</x-slot>

    @php
        $selectedUsers = collect(old('users', isset($role) ? $role->users->pluck('id')->all() : []))
            ->map(fn ($id) => (int) $id)->all();

        $swatches = [
            'indigo'  => 'bg-indigo-500',
            'blue'    => 'bg-blue-500',
            'emerald' => 'bg-emerald-500',
            'amber'   => 'bg-amber-500',
            'rose'    => 'bg-rose-500',
            'slate'   => 'bg-slate-500',
        ];
    @endphp

    <div class="max-w-3xl"
         x-data="{ icon: @js(old('icon', $role->icon ?? 'user')), color: @js(old('color', $role->color ?? 'indigo')), q: '' }">

        <div class="mb-6">
            <a href="{{ route('admin.roles.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← Роли и доступы</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ isset($role) ? 'Настройка роли' : 'Новая роль' }}</h1>
        </div>

        <form action="{{ isset($role) ? route('admin.roles.update', $role) : route('admin.roles.store') }}" method="POST" class="space-y-5">
            @csrf
            @if(isset($role)) @method('PUT') @endif

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название *</label>
                        <input type="text" name="name" value="{{ old('name', $role->name ?? '') }}" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Код *</label>
                        <input type="text" name="code" value="{{ old('code', $role->code ?? '') }}" required
                               @if($role->is_system ?? false) disabled @endif
                               placeholder="head_unit"
                               class="w-full text-sm font-mono border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] disabled:bg-gray-50 disabled:text-gray-400">
                        <p class="text-xs text-gray-400 mt-1">
                            @if($role->is_system ?? false)
                                Код системной роли используется в коде приложения и не меняется.
                            @else
                                Латиница в нижнем регистре, цифры и «_». Используется в проверках прав.
                            @endif
                        </p>
                        @error('code')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Описание</label>
                    <input type="text" name="description" value="{{ old('description', $role->description ?? '') }}"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @error('description')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-2">Иконка</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Models\Role::ICONS as $key => $label)
                            <label :class="icon === @js($key) ? 'border-[#5B4FE8] bg-indigo-50 text-[#5B4FE8]' : 'border-gray-200 text-gray-400 hover:border-gray-300'"
                                   class="w-11 h-11 rounded-xl border flex items-center justify-center cursor-pointer transition-colors"
                                   title="{{ $label }}">
                                <input type="radio" name="icon" value="{{ $key }}" x-model="icon" class="sr-only">
                                @include('admin.partials.role-icon', ['icon' => $key, 'class' => 'w-5 h-5'])
                            </label>
                        @endforeach
                    </div>
                    @error('icon')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-2">Цвет</label>
                        <div class="flex gap-2">
                            @foreach($swatches as $key => $dot)
                                <label :class="color === @js($key) ? 'border-gray-900' : 'border-transparent hover:border-gray-300'"
                                       class="w-9 h-9 rounded-xl border-2 flex items-center justify-center cursor-pointer"
                                       title="{{ \App\Models\Role::COLORS[$key] }}">
                                    <input type="radio" name="color" value="{{ $key }}" x-model="color" class="sr-only">
                                    <span class="w-5 h-5 rounded-lg {{ $dot }}"></span>
                                </label>
                            @endforeach
                        </div>
                        @error('color')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Уровень *</label>
                        <input type="number" name="level" min="1" max="9" required
                               value="{{ old('level', $role->level ?? 1) }}"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <p class="text-xs text-gray-400 mt-1">1 — исполнитель, 5 — высшее руководство. Влияет на порядок в списке.</p>
                        @error('level')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest">Пользователи с этой ролью</label>
                    <input type="text" x-model="q" placeholder="Поиск…"
                           class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 w-48 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                <p class="text-xs text-gray-400 mb-3">Роль можно выдать дополнительно — пользователь сохранит все свои остальные роли.</p>

                <div class="border border-gray-100 rounded-lg divide-y divide-gray-100 max-h-96 overflow-y-auto">
                    @foreach($users as $u)
                        @php $isPrimary = isset($role) && $u->role === $role->code; @endphp
                        <label x-show="q === '' || @js(mb_strtolower($u->name)).includes(q.toLowerCase())"
                               class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="users[]" value="{{ $u->id }}" class="rounded"
                                   {{ in_array($u->id, $selectedUsers, true) ? 'checked' : '' }}
                                   {{ $isPrimary ? 'checked disabled' : '' }}>
                            <img src="{{ $u->avatar_url }}" alt="" class="w-7 h-7 rounded-full">
                            <span class="text-sm text-gray-700">{{ $u->name }}</span>
                            <span class="text-xs text-gray-400">{{ $u->department?->name }}</span>
                            @if($isPrimary)
                                <span class="ml-auto text-[10px] font-semibold text-gray-400 border border-gray-200 rounded px-1.5 py-0.5">ОСНОВНАЯ РОЛЬ</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('users')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-2">Основная роль задаётся в карточке пользователя и здесь не снимается.</p>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                    {{ isset($role) ? 'Сохранить' : 'Создать роль' }}
                </button>
                <a href="{{ route('admin.roles.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>

                @if(isset($role) && !$role->is_system)
                    <button type="button" form="delete-role" class="ml-auto text-sm text-red-500 hover:underline"
                            onclick="if (confirm('Удалить роль «{{ $role->name }}»?')) document.getElementById('delete-role').submit()">
                        Удалить роль
                    </button>
                @endif
            </div>
        </form>

        @if(isset($role) && !$role->is_system)
            <form id="delete-role" action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endif
    </div>
</x-app-layout>
