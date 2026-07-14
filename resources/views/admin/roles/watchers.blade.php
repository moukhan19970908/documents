<x-app-layout>
    <x-slot name="title">Наблюдатели — Vamin</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Роли и доступы</h1>
    </div>

    @include('admin.roles.partials.tabs')

    <div x-data="{ modal: false, menu: null }" class="max-w-4xl">

        <div class="flex items-start gap-3 bg-red-50 border border-red-100 rounded-xl p-4 mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-gray-600 leading-relaxed">
                <span class="font-semibold text-red-600">Наблюдатель ВИДИТ документы, но НЕ может выполнять действия.</span>
                Не помещайте помощника в роль директора — тогда он сможет согласовывать от его имени.
                Для передачи права действия используйте <span class="font-semibold text-gray-900">ДЕЛЕГИРОВАНИЕ</span>.
            </p>
        </div>

        <button type="button" @click="modal = true"
                class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Правило наблюдения
        </button>

        <div class="space-y-3">
            @forelse($rules as $i => $rule)
                <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center gap-3">
                    <img src="{{ $rule['watcher']->avatar_url }}" alt="" class="w-9 h-9 rounded-full shrink-0">
                    <span class="text-sm font-semibold text-gray-900 shrink-0">{{ $rule['watcher']->name }}</span>

                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#5B4FE8] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span class="text-sm text-gray-500 truncate">{{ $rule['scope'] }}</span>

                    <div class="flex items-center gap-2 ml-auto shrink-0">
                        <img src="{{ $rule['target']->avatar_url }}" alt="" class="w-8 h-8 rounded-full">
                        <span class="text-sm font-medium text-gray-700">{{ $rule['target']->name }}</span>

                        <div class="relative">
                            <button type="button" @click="menu = (menu === {{ $i }} ? null : {{ $i }})"
                                    class="w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-50 hover:text-gray-600 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 12a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm7.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM21 12a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
                            </button>
                            <div x-show="menu === {{ $i }}" x-cloak @click.outside="menu = null"
                                 class="absolute right-0 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-20">
                                <button type="button" class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Изменить</button>
                                <button type="button" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-50">Удалить</button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">
                    Правила наблюдения не заданы
                </div>
            @endforelse
        </div>

        <p class="text-xs text-gray-400 mt-4">
            Верстка: правила не сохраняются, строки собраны из реальных пользователей как заглушка.
        </p>

        {{-- Модалка нового правила --}}
        <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/40" @click="modal = false"></div>

            <div class="relative bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-5">Правило наблюдения</h2>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Наблюдатель</label>
                        <select class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Наблюдает за</label>
                        <select class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Область</label>
                        <select class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option>Все документы, где участвует</option>
                            <option>Только документы, где он инициатор</option>
                            <option>Только документы, где он согласующий</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-6">
                    <button type="button" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Создать правило</button>
                    <button type="button" @click="modal = false" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
