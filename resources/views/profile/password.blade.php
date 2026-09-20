<x-app-layout>
    <x-slot name="title">Смена пароля — Vamin</x-slot>

    <div class="max-w-xl">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Мой профиль</h1>

        @include('profile.partials.tabs')

        <form action="{{ route('profile.password.update') }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                @if($user->password)
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Текущий пароль *</label>
                        <input type="password" name="current_password" required autocomplete="current-password"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        @error('current_password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                @else
                    <p class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        Вы входите через Битрикс24, и пароля у учётной записи ещё нет. Задайте его, чтобы иметь
                        возможность входить по почте.
                    </p>
                @endif

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Новый пароль *</label>
                    <input type="password" name="password" required autocomplete="new-password"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <p class="text-xs text-gray-400 mt-1">Минимум 8 символов.</p>
                    @error('password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Повторите новый пароль *</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
            </div>

            <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                Изменить пароль
            </button>
        </form>
    </div>
</x-app-layout>
