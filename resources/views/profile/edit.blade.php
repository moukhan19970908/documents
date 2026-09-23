<x-app-layout>
    <x-slot name="title">Мой профиль — Vamin</x-slot>

    <div class="max-w-xl">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Мой профиль</h1>

        @include('profile.partials.tabs')

        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div x-data="{ preview: null, filename: null }" class="flex items-center gap-4 pb-4 border-b border-gray-100">
                    <img :src="preview || '{{ $user->avatar_url }}'" src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                         class="w-16 h-16 rounded-full object-cover ring-2 ring-gray-200 shrink-0">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                        <p class="text-xs text-gray-500 mb-2">
                            {{ $user->role_label }}@if($user->department) · {{ $user->department->name }}@endif
                        </p>
                        <label class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Загрузить фото
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden"
                                   @change="filename = $event.target.files[0]?.name ?? null;
                                            preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                        </label>
                        <p class="text-xs text-gray-400 mt-1 truncate" x-text="filename || 'JPG, PNG, GIF или WebP, до 5 МБ'">JPG, PNG, GIF или WebP, до 5 МБ</p>
                        @error('avatar')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                @if($user->bitrix24_id)
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        ФИО и почта подтягиваются из Битрикс24 и будут перезаписаны при следующей синхронизации.
                        Телефон синхронизация не трогает.
                    </p>
                @endif

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">ФИО *</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Телефон</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+7 900 000-00-00"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @error('phone')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Почта *</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <p class="text-xs text-gray-400 mt-1">Почта используется для входа в систему и уведомлений.</p>
                    @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                Сохранить изменения
            </button>
        </form>
    </div>
</x-app-layout>
