<x-app-layout>
    <x-slot name="title">База знаний — Vamin</x-slot>

    @php
        $typeMeta = [
            'article'     => ['badge' => 'bg-emerald-50 text-emerald-600', 'dot' => 'bg-emerald-400', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            'video'       => ['badge' => 'bg-blue-50 text-blue-600',       'dot' => 'bg-blue-400',    'icon' => 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            'instruction' => ['badge' => 'bg-amber-50 text-amber-600',     'dot' => 'bg-amber-400',   'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
            'regulation'  => ['badge' => 'bg-violet-50 text-violet-600',   'dot' => 'bg-violet-400',  'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
        ];
    @endphp

    <div x-data="{ admin: false, q: '' }">
        {{-- Шапка --}}
        <div class="flex items-center justify-between mb-6 gap-4">
            <h1 class="text-2xl font-bold text-gray-900">База знаний</h1>

            <div class="flex items-center gap-3">
                @if($isAdmin)
                    <a x-show="admin" x-cloak href="{{ route('admin.knowledge.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Создать материал
                    </a>

                    <div class="flex bg-gray-100 rounded-lg p-0.5 text-sm">
                        <button @click="admin = false" :class="!admin ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-md font-medium transition">Обычный</button>
                        <button @click="admin = true"  :class="admin ? 'bg-white shadow text-gray-900' : 'text-gray-500'"  class="px-3 py-1.5 rounded-md font-medium transition">Администратор</button>
                    </div>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <div class="flex gap-6 items-start">
            {{-- Дерево знаний --}}
            <aside class="w-60 shrink-0 hidden md:block">
                <div class="relative mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="q" placeholder="Поиск по материалам…"
                           class="w-full text-sm bg-gray-50 border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>

                <nav class="space-y-0.5 text-sm">
                    @php $isGeneral = request()->boolean('general'); @endphp
                    <a href="{{ route('knowledge.index', ['general' => 1]) }}"
                       class="flex items-center gap-2 px-3 py-2 rounded-lg font-medium {{ $isGeneral ? 'bg-[#5B4FE8] text-white' : 'text-gray-700 hover:bg-gray-50' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="{{ $isGeneral ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        Общее для всех
                    </a>

                    @foreach($directions as $dir)
                        @php $dirActive = (int) request('direction') === $dir->id; @endphp
                        <div x-data="{ open: {{ $dirActive || request('department') && $dir->catalogDepartments()->contains('id', (int) request('department')) ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center gap-1.5 px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-50">
                                <svg :class="open ? 'rotate-90' : ''" class="w-3.5 h-3.5 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                <span class="font-medium truncate">{{ $dir->name }}</span>
                            </button>
                            <div x-show="open" x-cloak class="ml-3 pl-2 border-l border-gray-100 space-y-0.5">
                                @forelse($dir->catalogDepartments() as $dept)
                                    @php $deptActive = (int) request('department') === $dept->id; @endphp
                                    <div x-data="{ o: {{ $deptActive ? 'true' : 'false' }} }">
                                        <button type="button" @click="o = !o"
                                                class="w-full flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-50">
                                            <svg :class="o ? 'rotate-90' : ''" class="w-3 h-3 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                            <span class="truncate font-medium text-gray-700">{{ $dept->name }}</span>
                                        </button>
                                        <div x-show="o" x-cloak class="ml-4 space-y-0.5">
                                            @foreach($levels as $code => $label)
                                                @php $lvlActive = $deptActive && request('level') === $code; @endphp
                                                <a href="{{ route('knowledge.index', ['department' => $dept->id, 'level' => $code]) }}"
                                                   class="block px-3 py-1.5 rounded-lg text-[13px] {{ $lvlActive ? 'bg-[#5B4FE8] text-white' : 'text-gray-500 hover:bg-gray-50' }}">
                                                    {{ $label }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <p class="px-3 py-1.5 text-xs text-gray-400">Нет отделов</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </nav>
            </aside>

            {{-- Карточки --}}
            <div class="flex-1 min-w-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @forelse($materials as $m)
                        @php $meta = $typeMeta[$m->type] ?? $typeMeta['article']; @endphp
                        <div class="relative group bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md hover:border-[#5B4FE8]/40 transition"
                             x-show="q === '' || @js(mb_strtolower($m->title)).includes(q.toLowerCase())">
                            <a href="{{ route('knowledge.show', $m) }}" class="absolute inset-0 z-0" aria-label="{{ $m->title }}"></a>

                            <div class="relative z-10 pointer-events-none">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="w-10 h-10 rounded-xl {{ $meta['badge'] }} flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/></svg>
                                    </div>
                                    @unless($m->is_published)
                                        <span class="text-[10px] uppercase tracking-wide font-semibold text-amber-500">Черновик</span>
                                    @else
                                        <span class="w-2.5 h-2.5 rounded-full {{ $meta['dot'] }} mt-1"></span>
                                    @endunless
                                </div>

                                <h3 class="font-semibold text-gray-900 leading-snug">{{ $m->title }}</h3>
                                @if($m->description)
                                    <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $m->description }}</p>
                                @elseif($m->body)
                                    <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ \Illuminate\Support\Str::limit(strip_tags($m->body), 90) }}</p>
                                @endif

                                <div class="flex flex-wrap gap-1.5 mt-3">
                                    @if($m->is_general)
                                        <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">Общее</span>
                                    @endif
                                    @if($m->direction)
                                        <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ $m->direction->name }}</span>
                                    @endif
                                    @if($m->level)
                                        <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-[#5B4FE8]">{{ $m->levelLabel() }}</span>
                                    @endif
                                </div>

                                @if($m->studyLabel())
                                    <div class="flex items-center gap-1.5 mt-3 text-xs text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $m->studyLabel() }}
                                    </div>
                                @endif
                            </div>

                            {{-- Действия администратора --}}
                            @if($isAdmin)
                                <div x-show="admin" x-cloak class="relative z-10 pointer-events-auto flex items-center gap-3 mt-4 pt-3 border-t border-gray-100 text-xs">
                                    <a href="{{ route('admin.knowledge.edit', $m) }}" class="text-gray-500 hover:text-[#5B4FE8]">Изменить</a>
                                    <a href="{{ route('admin.knowledge.access', $m) }}" class="text-gray-500 hover:text-[#5B4FE8]">Доступ</a>
                                    <form method="POST" action="{{ route('admin.knowledge.destroy', $m) }}" class="ml-auto"
                                          onsubmit="return confirm('Удалить материал «{{ $m->title }}»?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700">Удалить</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="col-span-full text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-16">
                            Материалов пока нет.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
