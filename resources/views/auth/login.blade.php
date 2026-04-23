<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — ArchManuscript</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F9FAFB] flex flex-col items-center justify-center px-4">

    <div class="w-full max-w-sm">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <h1 class="text-xl font-bold text-gray-900">ArchManuscript</h1>
            <p class="text-sm text-gray-500 mt-1">Редакционная система управления документами.</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">

            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="/login" class="space-y-5">
                @csrf

                {{-- Email / Username --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-widest mb-1.5">
                        Email или имя пользователя
                    </label>
                    <input
                        type="text"
                        name="login"
                        value="{{ old('login') }}"
                        placeholder="workspace@archmanuscript.com"
                        autocomplete="username"
                        class="w-full px-4 py-3 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] focus:bg-white transition-colors"
                        required
                    >
                </div>

                {{-- Password --}}
                <div x-data="{ show: false }">
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-widest mb-1.5">
                        Пароль
                    </label>
                    <div class="relative">
                        <input
                            :type="show ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            class="w-full px-4 py-3 pr-11 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] focus:bg-white transition-colors"
                            required
                        >
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Forgot password --}}
                <div class="text-right">
                    <a href="#" class="text-sm text-[#5B4FE8] hover:underline">Забыли пароль?</a>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="w-full bg-[#5B4FE8] text-white py-3 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
                    Войти
                </button>
            </form>

            {{-- Divider --}}
            <div class="flex items-center gap-3 my-5">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-xs text-gray-400 font-medium uppercase tracking-widest">ИЛИ</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            {{-- Bitrix24 --}}
            <a href="/auth/bitrix24"
               class="flex items-center justify-center gap-3 w-full border border-gray-200 rounded-lg py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#5B4FE8]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                Войти через Bitrix24
            </a>

            {{-- Register link --}}
            <p class="text-center text-sm text-gray-500 mt-5">
                Нет аккаунта?
                <a href="#" class="text-[#5B4FE8] font-medium hover:underline">Регистрация</a>
            </p>
        </div>
    </div>

</body>
</html>
