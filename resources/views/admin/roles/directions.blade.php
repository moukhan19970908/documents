<x-app-layout>
    <x-slot name="title">Направления — Vamin</x-slot>

    @php
        $plural = fn (int $n) => $n % 10 === 1 && $n % 100 !== 11
            ? 'отдел'
            : (in_array($n % 10, [2, 3, 4]) && !in_array($n % 100, [12, 13, 14]) ? 'отдела' : 'отделов');
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Роли и доступы</h1>
    </div>

    @include('admin.roles.partials.tabs')

    @if(session('success'))
        <div class="mb-4 max-w-5xl rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error') || $errors->any())
        <div class="mb-4 max-w-5xl rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
            {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    <div x-data="{ createModal: false }" class="max-w-5xl">

        <button type="button" @click="createModal = true"
                class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Создать направление
        </button>

        <div class="space-y-3">
            @forelse($directions as $direction)
                @php
                    $childIds   = $direction->children->pluck('id')->all();
                    $candidates = $allDepartments->reject(fn ($d) => $d->id === $direction->id || in_array($d->id, $childIds));
                @endphp
                <div x-data="{ adding: false }" class="bg-white rounded-xl border border-gray-200 px-5 py-4">
                    <div class="flex items-center gap-5">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5B4FE8] flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>

                        <div class="w-48 shrink-0">
                            <h2 class="font-semibold text-gray-900 truncate">{{ $direction->name }}</h2>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $direction->children->count() }} {{ $plural($direction->children->count()) }}</p>
                        </div>

                        <div class="w-56 shrink-0 flex items-center gap-2.5">
                            @if($direction->head)
                                <img src="{{ $direction->head->avatar_url }}" alt="" class="w-7 h-7 rounded-full shrink-0">
                                <span class="text-sm text-gray-700 truncate">{{ $direction->head->name }}</span>
                            @else
                                <span class="w-7 h-7 rounded-full bg-gray-100 text-gray-300 flex items-center justify-center text-sm shrink-0">—</span>
                                <span class="text-sm text-gray-400">— не назначен —</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto shrink-0">
                            <button type="button" @click="adding = !adding"
                                    class="flex items-center gap-1.5 text-sm text-[#5B4FE8] hover:bg-indigo-50 px-3 py-1.5 rounded-lg font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                Добавить отдел
                            </button>
                            <form method="POST" action="{{ route('admin.roles.directions.destroy', $direction) }}"
                                  onsubmit="return confirm('Удалить направление «{{ $direction->name }}»?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Удалить направление"
                                        class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Отделы направления --}}
                    <div class="mt-3 pl-[3.75rem] flex flex-wrap gap-2">
                        @forelse($direction->children as $child)
                            <span class="inline-flex items-center gap-1.5 bg-gray-50 border border-gray-200 rounded-full pl-3 pr-1.5 py-1 text-sm text-gray-700">
                                {{ $child->name }}
                                <form method="POST" action="{{ route('admin.roles.directions.departments.remove', [$direction, $child]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Открепить отдел"
                                            class="w-5 h-5 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </form>
                            </span>
                        @empty
                            <span class="text-sm text-gray-400">Отделы ещё не добавлены</span>
                        @endforelse
                    </div>

                    {{-- Инлайн-форма добавления отдела --}}
                    <div x-show="adding" x-cloak class="mt-3 pl-[3.75rem]">
                        @if($candidates->isEmpty())
                            <p class="text-sm text-gray-400">Нет доступных отделов для добавления.</p>
                        @else
                            <form method="POST" action="{{ route('admin.roles.directions.departments.add', $direction) }}"
                                  class="flex items-center gap-2">
                                @csrf
                                <select name="department_id" required
                                        class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] min-w-64">
                                    <option value="" disabled selected>— выберите отдел —</option>
                                    @foreach($candidates as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Добавить</button>
                                <button type="button" @click="adding = false" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">
                    Направлений пока нет — создайте первое.
                </div>
            @endforelse
        </div>

        <p class="text-xs text-gray-400 mt-4">
            Направление объединяет отделы: добавленный отдел становится дочерним в оргструктуре,
            поэтому руководитель направления видит документы всех его отделов (доступ уровня «направление»).
        </p>

        {{-- Модалка создания направления --}}
        <div x-show="createModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/40" @click="createModal = false"></div>

            <form method="POST" action="{{ route('admin.roles.directions.store') }}"
                  class="relative bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-lg p-6">
                @csrf
                <h2 class="text-lg font-bold text-gray-900 mb-5">Новое направление</h2>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название</label>
                        <input type="text" name="name" required placeholder="Например: Продажи"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Отделы (необязательно)</label>
                        <select name="departments[]" multiple size="6"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($allDepartments as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1.5">Можно выбрать несколько (Ctrl/Cmd + клик). Отделы можно добавить и позже.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-6">
                    <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Создать</button>
                    <button type="button" @click="createModal = false" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
