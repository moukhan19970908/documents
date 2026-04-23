<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ArchManuscript' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F9FAFB] font-sans min-h-screen flex">

    {{-- Mobile overlay --}}
    <div x-data="{ sidebarOpen: false }" class="flex w-full min-h-screen">

        {{-- Sidebar backdrop (mobile) --}}
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="sidebarOpen = false"
            class="fixed inset-0 bg-black/50 z-20 md:hidden"
            style="display:none"
        ></div>

        {{-- Sidebar --}}
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="fixed top-0 left-0 h-full w-60 bg-white border-r border-gray-200 flex flex-col z-30 transition-transform duration-200 ease-in-out"
        >
            {{-- Workspace header --}}
            <div class="p-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#5B4FE8] text-white flex items-center justify-center font-bold text-sm">
                        {{ strtoupper(substr(auth()->user()->name ?? 'S', 0, 1)) }}
                    </div>
                    <div class="leading-tight">
                        <div class="text-xs font-semibold text-gray-800 uppercase tracking-wide">{{ auth()->user()->department?->name ?? 'Рабочая область' }}</div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-wide">Корпоративный процесс</div>
                    </div>
                </div>
            </div>

            {{-- New request button --}}
            <div class="p-3">
                <a href="{{ route('documents.create') }}"
                   class="flex items-center justify-center gap-2 w-full bg-[#5B4FE8] text-white rounded-lg py-2.5 text-sm font-medium hover:bg-indigo-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Новый запрос
                </a>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-3 py-2 space-y-0.5 overflow-y-auto">
                @php
                    $navItems = [
                        ['route' => 'dashboard', 'label' => 'Дашборд', 'icon' => 'dashboard'],
                        ['route' => 'tasks', 'label' => 'Мои задачи', 'icon' => 'tasks'],
                        ['route' => 'documents.index', 'label' => 'Документы', 'icon' => 'document'],
                        ['route' => 'workflows.index', 'label' => 'Процессы', 'icon' => 'workflow'],
                        ['route' => 'archive.index', 'label' => 'Архив', 'icon' => 'archive'],
                    ];
                @endphp

                @foreach($navItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')
                                 ? 'bg-[#5B4FE8] text-white'
                                 : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        @include('partials.nav-icon', ['icon' => $item['icon'], 'active' => request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')])
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Bottom links --}}
            <div class="px-3 py-3 border-t border-gray-100 space-y-0.5">
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Настройки
                </a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Поддержка
                </a>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col md:ml-60 min-h-screen">

            {{-- Top bar --}}
            <header class="bg-white border-b border-gray-200 sticky top-0 z-10 h-16 flex items-center px-4 md:px-6 gap-4">
                {{-- Hamburger (mobile) --}}
                <button @click="sidebarOpen = !sidebarOpen" class="md:hidden text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                {{-- Logo --}}
                <a href="{{ route('dashboard') }}" class="text-base font-bold text-gray-900 mr-2 hidden md:block">ArchManuscript</a>

                {{-- Search --}}
                <div class="flex-1 max-w-md">
                    <form action="{{ route('documents.index') }}" method="GET">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Поиск документов..."
                                class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] focus:bg-white"
                            >
                        </div>
                    </form>
                </div>

                <div class="flex items-center gap-3 ml-auto">
                    {{-- Bell --}}
                    <a href="{{ route('notifications.index') }}" class="relative text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @if(auth()->user()->unreadNotificationsCount() > 0)
                            <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center">
                                {{ auth()->user()->unreadNotificationsCount() }}
                            </span>
                        @endif
                    </a>

                    {{-- History --}}
                    <button class="text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>

                    {{-- Upload CTA --}}
                    <a href="{{ route('documents.create') }}"
                       class="hidden md:flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Загрузить документ
                    </a>

                    {{-- Avatar --}}
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="w-8 h-8 rounded-full overflow-hidden ring-2 ring-gray-200">
                            <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-full h-full object-cover">
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition
                             class="absolute right-0 top-10 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-50" style="display:none">
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-gray-500">{{ auth()->user()->role_label }}</p>
                            </div>
                            <form method="POST" action="/logout">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Выйти</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="mx-4 md:mx-6 mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mx-4 md:mx-6 mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    {{ session('error') }}
                </div>
            @endif

            {{-- Page content --}}
            <main class="flex-1 p-4 md:p-6">
                {{ $slot }}
            </main>

            {{-- Mobile bottom navigation --}}
            <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex items-center h-16 z-20">
                <a href="{{ route('dashboard') }}" class="flex-1 flex flex-col items-center gap-1 text-xs {{ request()->routeIs('dashboard') ? 'text-[#5B4FE8]' : 'text-gray-500' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-2a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/></svg>
                    Дашборд
                </a>
                <a href="{{ route('tasks') }}" class="flex-1 flex flex-col items-center gap-1 text-xs {{ request()->routeIs('tasks') ? 'text-[#5B4FE8]' : 'text-gray-500' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Задачи
                </a>
                <a href="{{ route('documents.index') }}" class="flex-1 flex flex-col items-center gap-1 text-xs {{ request()->routeIs('documents.*') ? 'text-[#5B4FE8]' : 'text-gray-500' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Документы
                </a>
                <a href="#" class="flex-1 flex flex-col items-center gap-1 text-xs text-gray-500">
                    <img src="{{ auth()->user()->avatar_url }}" class="w-5 h-5 rounded-full" alt="">
                    Профиль
                </a>
            </nav>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
