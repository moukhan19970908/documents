<x-app-layout>
    <x-slot name="title">Новый внешний участник — Vamin</x-slot>

    <div class="max-w-xl">
        <div class="mb-6">
            <a href="{{ route('admin.external-participants.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Назад к списку</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-2">Новый внешний участник</h1>
            <p class="text-sm text-gray-500 mt-1">Пароль будет сгенерирован и отправлен на почту участника.</p>
        </div>

        <form action="{{ route('admin.external-participants.store') }}" method="POST" class="space-y-5">
            @csrf
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">ФИО *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                           placeholder="Иванов Иван Иванович">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                           placeholder="participant@example.com">
                    @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-gray-400 mt-1">На этот адрес придёт логин и пароль для входа.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Создать и отправить доступ
                </button>
                <a href="{{ route('admin.external-participants.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
            </div>
        </form>
    </div>
</x-app-layout>
