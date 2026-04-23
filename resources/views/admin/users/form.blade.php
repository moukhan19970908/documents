<x-app-layout>
    <x-slot name="title">{{ isset($user) ? 'Редактировать пользователя' : 'Новый пользователь' }} — ArchManuscript</x-slot>

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
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Роль *</label>
                    <select name="role" required class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="linear"   {{ old('role', $user->role ?? '') === 'linear'   ? 'selected' : '' }}>Линейный сотрудник</option>
                        <option value="director" {{ old('role', $user->role ?? '') === 'director' ? 'selected' : '' }}>Руководитель</option>
                        <option value="archiver" {{ old('role', $user->role ?? '') === 'archiver' ? 'selected' : '' }}>Архивариус</option>
                        <option value="admin"    {{ old('role', $user->role ?? '') === 'admin'    ? 'selected' : '' }}>Администратор</option>
                    </select>
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
