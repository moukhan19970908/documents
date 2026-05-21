<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Соглашение об использовании — Vamin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F9FAFB] font-sans min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Vamin</h1>
            <p class="text-sm text-gray-500 mt-1">Система электронного документооборота</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-900">Соглашение об использовании системы</h2>
                <p class="text-sm text-gray-500 mt-1">Для продолжения необходимо ознакомиться с соглашением и принять его</p>
            </div>

            <div class="px-8 py-6">
                {{-- Agreement text --}}
                @if($content)
                    <div class="mb-6 h-80 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap font-sans">{{ $content }}</div>
                @else
                    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl text-sm text-yellow-800">
                        Файл соглашения не найден. Пожалуйста, обратитесь к администратору.
                    </div>
                @endif

                <form method="POST" action="{{ route('agreement.accept') }}">
                    @csrf

                    <label class="flex items-start gap-3 cursor-pointer mb-6">
                        <input type="checkbox" name="accepted" value="1"
                               class="mt-0.5 w-4 h-4 rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"
                               {{ old('accepted') ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700 leading-relaxed">
                            Я ознакомился(-лась) с соглашением об использовании системы электронного документооборота Vamin и принимаю его условия.
                        </span>
                    </label>

                    @error('accepted')
                        <p class="text-sm text-red-600 mb-4">{{ $message }}</p>
                    @enderror

                    <div class="flex gap-3">
                        <button type="submit"
                                class="flex-1 bg-[#5B4FE8] text-white text-sm font-semibold rounded-xl py-2.5 hover:bg-[#4840d4] transition-colors">
                            Принять и продолжить
                        </button>
                        <a href="{{ route('agreement.decline') }}"
                           class="flex-1 text-center bg-gray-100 text-gray-700 text-sm font-semibold rounded-xl py-2.5 hover:bg-gray-200 transition-colors">
                            Отказаться и выйти
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
