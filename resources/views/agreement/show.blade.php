<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Соглашение об использовании — Vamin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Word-document look */
        .word-paper {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #111;
        }
        .word-paper .doc-title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 16px;
            line-height: 1.3;
        }
        .word-paper .doc-para {
            text-align: justify;
            text-indent: 1.25cm;
            margin: 0 0 6px 0;
        }
        .word-paper .doc-list {
            margin: 4px 0 4px 1.25cm;
            padding-left: 0.75cm;
            list-style-type: disc;
        }
        .word-paper .doc-list li {
            text-align: justify;
            margin-bottom: 4px;
        }
        .word-paper .doc-sublist {
            margin: 2px 0 2px 0.75cm;
            padding-left: 0.5cm;
            list-style-type: circle;
        }
        .word-paper .doc-sublist li {
            text-align: left;
            margin-bottom: 2px;
        }
    </style>
</head>
<body class="bg-[#E8E8E8] font-sans min-h-screen flex flex-col items-center justify-start py-10 px-4">

    <div class="w-full max-w-3xl">

        {{-- Header --}}
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold text-gray-900">Vamin</h1>
            <p class="text-sm text-gray-500 mt-1">Система электронного документооборота</p>
        </div>

        {{-- Paper sheet --}}
        <div class="bg-white shadow-lg rounded-sm mb-6 px-14 py-12 word-paper">
            @if($content)
                {!! $content !!}
            @else
                <p class="text-center text-yellow-700 bg-yellow-50 border border-yellow-200 rounded p-4 text-sm" style="font-family:sans-serif">
                    Файл соглашения не найден. Пожалуйста, обратитесь к администратору.
                </p>
            @endif
        </div>

        {{-- Accept / Decline card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-8 py-6 mb-10">
            <form method="POST" action="{{ route('agreement.accept') }}">
                @csrf

                <label class="flex items-start gap-3 cursor-pointer mb-5">
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
                    <!--
                    <a href="{{ route('agreement.decline') }}"
                       class="flex-1 text-center bg-gray-100 text-gray-700 text-sm font-semibold rounded-xl py-2.5 hover:bg-gray-200 transition-colors">
                        Отказаться и выйти
                    </a>
-->
                </div>
            </form>
        </div>

    </div>

</body>
</html>
