<x-app-layout>
    <x-slot name="title">{{ isset($user) ? 'Редактировать пользователя' : 'Новый пользователь' }} — Vamin</x-slot>

    <div class="max-w-xl">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ isset($user) ? 'Редактировать пользователя' : 'Новый пользователь' }}</h1>

        <form action="{{ isset($user) ? route('admin.users.update', $user) : route('admin.users.store') }}" method="POST" class="space-y-5">
            @csrf
            @if(isset($user)) @method('PUT') @endif

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Имя *</label>
                    <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                @if(!isset($user))
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Пароль *</label>
                    <input type="password" name="password" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                @endif
                @php
                    $primaryRole = old('role', $user->role ?? 'linear');
                    $extraRoles = collect(old('roles', isset($user) ? $user->roles->pluck('id')->all() : []))
                        ->map(fn ($id) => (int) $id)->all();
                @endphp
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Основная роль *</label>
                    <select name="role" required class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        @foreach($roles as $role)
                            <option value="{{ $role->code }}" {{ $primaryRole === $role->code ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Дополнительные роли</label>
                    <div class="border border-gray-100 rounded-lg divide-y divide-gray-100 max-h-56 overflow-y-auto">
                        @foreach($roles as $role)
                            <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="rounded"
                                       {{ in_array($role->id, $extraRoles, true) ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ $role->name }}</span>
                                <span class="text-xs text-gray-400 font-mono">{{ $role->code }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Права ролей суммируются: пользователь получает доступ по любой из своих ролей.</p>
                    @error('roles')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Отдел</label>
                    <select name="department_id" class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— Не выбрано —</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id', $user->department_id ?? '') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded" {{ old('is_active', $user->is_active ?? 1) ? 'checked' : '' }}>
                    <label for="is_active" class="text-sm text-gray-700">Активный пользователь</label>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                    {{ isset($user) ? 'Сохранить изменения' : 'Создать пользователя' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
            </div>
        </form>
    </div>
</x-app-layout>
