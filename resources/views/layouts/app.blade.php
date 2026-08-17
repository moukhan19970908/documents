<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.public_key') }}">
    <title>{{ $title ?? 'Vamin' }}</title>
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
                    <div class="w-16 h-16 rounded-lg bg-[#5B4FE8] text-white flex items-center justify-center font-bold text-sm">
                        <img src="{{asset('logo.png')}}">
                    </div>
                    <div class="leading-tight">
                        <div class="text-xs font-semibold text-gray-800 uppercase tracking-wide">Документооборот</div>
                    </div>
                </div>
            </div>

            {{-- New request button --}}
            <div class="p-3">
                <a href="{{ auth()->user()->isExternal() ? route('documents.index') : route('documents.create') }}"
                   class="flex items-center justify-center gap-2 w-full bg-[#5B4FE8] text-white rounded-lg py-2.5 text-sm font-medium hover:bg-indigo-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Новый сценарий
                </a>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-3 py-2 space-y-0.5 overflow-y-auto">
                @php
                    // Top group — видимость управляется матрицей прав (menu.*).
                    $navItems = array_filter([
                        ['key' => 'menu.dashboard', 'route' => 'dashboard', 'label' => 'Дашборд', 'icon' => 'dashboard'],
                        ['key' => 'menu.tasks', 'route' => 'tasks', 'label' => 'Мои задачи', 'icon' => 'tasks', 'url' => route('tasks', ['filter' => 'pending']), 'badge' => $menuActiveTasks ?? 0],
                        ['key' => 'menu.chats', 'route' => 'chats.index', 'label' => 'Чаты', 'icon' => 'chat', 'badge' => $menuUnreadChats ?? 0],
                    ], fn ($item) => auth()->user()->canSeeMenu($item['key']));
                @endphp

                @foreach($navItems as $item)
                    <a href="{{ $item['url'] ?? route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')
                                 ? 'bg-[#5B4FE8] text-white'
                                 : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        @include('partials.nav-icon', ['icon' => $item['icon'], 'active' => request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')])
                        {{ $item['label'] }}
                        @if(($item['badge'] ?? 0) > 0)
                            <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full
                                         {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')
                                            ? 'bg-white text-[#5B4FE8]'
                                            : 'bg-[#5B4FE8] text-white' }}">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach

                @if(auth()->user()->canSeeMenu('menu.processes'))
                {{-- Процессы (collapsible group) --}}
                @php
                    $processItems = array_filter([
                        ['key' => 'menu.processes.documents', 'label' => 'Документооборот', 'route' => 'documents.index', 'url' => route('documents.index'), 'badge' => $menuPendingApprovals ?? 0],
                        ['key' => 'menu.processes.orders', 'label' => 'Приказы', 'route' => 'orders', 'url' => route('orders.index'), 'badge' => $menuOrderAcks ?? 0],
                        ['key' => 'menu.processes.assignments', 'label' => 'Поручения', 'route' => 'assignments', 'url' => route('assignments.index'), 'badge' => $menuAssignments ?? 0],
                        ['key' => 'menu.processes.procedures', 'label' => 'Процедуры', 'route' => 'procedures', 'url' => route('procedures.index'), 'badge' => $menuProcedures ?? 0],
                        ['key' => 'menu.processes.requests', 'label' => 'Заявки', 'route' => 'requests', 'url' => route('requests.index'), 'badge' => $menuRequests ?? 0],
                        ['key' => 'menu.processes.credit_committee', 'label' => 'Кредитный комитет', 'route' => 'credit-committee', 'url' => route('credit-committee.index'), 'badge' => $menuCreditCommittee ?? 0],
                        ['key' => 'menu.processes.audits', 'label' => 'Проверки', 'route' => 'inspections', 'url' => route('inspections.index'), 'badge' => $menuInspections ?? 0],
                    ], fn ($proc) => auth()->user()->canSeeMenu($proc['key']));
                @endphp
                <div x-data="{ open: true }">
                    <button @click="open = !open"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        @include('partials.nav-icon', ['icon' => 'process', 'active' => false])
                        Процессы
                        <svg :class="open ? 'rotate-90' : ''" class="ml-auto w-3.5 h-3.5 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div x-show="open" class="mt-0.5 space-y-0.5">
                        @foreach($processItems as $proc)
                            @php $procActive = isset($proc['route']) && (request()->routeIs($proc['route']) || request()->routeIs($proc['route'].'.*')); @endphp
                            <a href="{{ $proc['url'] }}"
                               class="flex items-center pl-11 pr-3 py-2 rounded-lg text-sm font-medium transition-colors
                                      {{ $procActive ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                {{ $proc['label'] }}
                                @if(($proc['badge'] ?? 0) > 0)
                                    <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full
                                                 {{ $procActive ? 'bg-white text-[#5B4FE8]' : 'bg-[#5B4FE8] text-white' }}">{{ $proc['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(auth()->user()->canSeeMenu('menu.admin'))
                {{-- Администрирование (collapsible group) --}}
                @php
                    $adminItems = array_filter([
                        ['key' => 'menu.admin.scenarios', 'label' => 'Конструктор процессов', 'route' => 'admin.scenarios', 'url' => route('admin.scenarios.index')],
                        ['key' => 'menu.admin.scenarios', 'label' => 'Виды заявок', 'route' => 'admin.request-types', 'url' => route('admin.request-types.index')],
                        ['key' => 'menu.admin.document_types', 'label' => 'Классификаторы и типы', 'route' => 'admin.document-types', 'url' => route('admin.document-types.index')],
                        ['key' => 'menu.admin.numbering', 'label' => 'Нумерация', 'route' => 'admin.numbering', 'url' => route('admin.numbering.index')],
                        ['key' => 'menu.admin.procedures', 'label' => 'Шаблоны процедур', 'route' => 'admin.procedures', 'url' => route('admin.procedures.index')],
                        ['key' => 'menu.admin.roles', 'label' => 'Роли и доступы', 'route' => 'admin.roles', 'url' => route('admin.roles.index')],
                        ['key' => 'menu.admin.blank_templates', 'label' => 'Шаблоны бланков', 'route' => 'admin.blank-templates', 'url' => route('admin.blank-templates.index')],
                        ['key' => 'menu.admin.org_structure', 'label' => 'Оргструктура', 'url' => '#'],
                    ], fn ($adm) => auth()->user()->canSeeMenu($adm['key']));
                @endphp
                @php $adminOpen = request()->routeIs('admin.document-types.*') || request()->routeIs('admin.scenarios.*') || request()->routeIs('admin.roles.*'); @endphp
                <div x-data="{ open: @json($adminOpen) }">
                    <button @click="open = !open"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        @include('partials.nav-icon', ['icon' => 'admin', 'active' => false])
                        Администрирование
                        <svg :class="open ? 'rotate-90' : ''" class="ml-auto w-3.5 h-3.5 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    <div x-show="open" class="mt-0.5 space-y-0.5">
                        @foreach($adminItems as $adm)
                            @php $admActive = isset($adm['route']) && request()->routeIs($adm['route'].'.*'); @endphp
                            <a href="{{ $adm['url'] }}"
                               class="flex items-center pl-11 pr-3 py-2 rounded-lg text-sm font-medium transition-colors
                                      {{ $admActive ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                {{ $adm['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Main items below «Процессы» --}}
                @php
                    $lowerNavItems = array_filter([
                        ['key' => 'menu.archive', 'route' => 'archive.index', 'label' => 'Архив', 'icon' => 'archive'],
                        ['key' => 'menu.employees', 'route' => 'employees.index', 'label' => 'Сотрудники', 'icon' => 'employees'],
                    ], fn ($item) => auth()->user()->canSeeMenu($item['key']));
                @endphp
                @foreach($lowerNavItems as $item)
                    <a href="{{ $item['url'] ?? route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')
                                 ? 'bg-[#5B4FE8] text-white'
                                 : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        @include('partials.nav-icon', ['icon' => $item['icon'], 'active' => request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')])
                        {{ $item['label'] }}
                        @if(($item['badge'] ?? 0) > 0)
                            <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full
                                         {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')
                                            ? 'bg-white text-[#5B4FE8]'
                                            : 'bg-[#5B4FE8] text-white' }}">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach

                {{-- Аналитика --}}
                @if(auth()->user()->canSeeMenu('menu.analytics'))
                @php $analyticsActive = request()->routeIs('analytics.*'); @endphp
                <a href="{{ route('analytics.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $analyticsActive ? 'bg-[#5B4FE8]/10 text-[#5B4FE8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    @include('partials.nav-icon', ['icon' => 'analytics', 'active' => $analyticsActive])
                    Аналитика
                </a>
                @endif

                

                {{-- Trips --}}
                <!-- @if(auth()->user()->canSeeMenu('menu.trips'))
                <div class="pt-3 pb-1">
                    <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Командировки</p>
                </div>
                @if(auth()->user()->canSeeMenu('menu.trips.my'))
                <a href="{{ route('trips.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('trips.index') || (request()->routeIs('trips.*') && !request()->routeIs('trips.approvals') && !request()->routeIs('trips.registries.*')) ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Мои заявки
                </a>
                @endif
                @if(auth()->user()->canSeeMenu('menu.trips.approvals') || auth()->user()->isManager() || auth()->user()->isApprover('trip'))
                    <a href="{{ route('trips.approvals') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('trips.approvals') ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        На согласование
                        @if(($menuTripApprovals ?? 0) > 0)
                            <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full {{ request()->routeIs('trips.approvals') ? 'bg-white text-[#5B4FE8]' : 'bg-[#5B4FE8] text-white' }}">{{ $menuTripApprovals }}</span>
                        @endif
                    </a>
                @endif
                @if(auth()->user()->canSeeMenu('menu.trips.registries') || auth()->user()->isManager())
                    <a href="{{ route('trips.registries.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('trips.registries.*') ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Реестры
                        @if(($menuTripRegistries ?? 0) > 0)
                            <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full {{ request()->routeIs('trips.registries.*') ? 'bg-white text-[#5B4FE8]' : 'bg-[#5B4FE8] text-white' }}">{{ $menuTripRegistries }}</span>
                        @endif
                    </a>
                @endif
                @endif

                {{-- Vacations --}}
                @if(auth()->user()->canSeeMenu('menu.vacations'))
                <div class="pt-3 pb-1">
                    <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Отпуска</p>
                </div>
                @if(auth()->user()->canSeeMenu('menu.vacations.my'))
                <a href="{{ route('vacations.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('vacations.index') || (request()->routeIs('vacations.*') && !request()->routeIs('vacations.approvals') && !request()->routeIs('vacations.registries.*')) ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Мои заявки
                </a>
                @endif
                @if(auth()->user()->canSeeMenu('menu.vacations.approvals') || auth()->user()->isManager() || auth()->user()->isApprover('vacation'))
                    <a href="{{ route('vacations.approvals') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('vacations.approvals') ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        На согласование
                        @if(($menuVacationApprovals ?? 0) > 0)
                            <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full {{ request()->routeIs('vacations.approvals') ? 'bg-white text-[#5B4FE8]' : 'bg-[#5B4FE8] text-white' }}">{{ $menuVacationApprovals }}</span>
                        @endif
                    </a>
                @endif
                @if(auth()->user()->canSeeMenu('menu.vacations.registries') || auth()->user()->isManager() || auth()->user()->isApprover('vacation_registry'))
                    <a href="{{ route('vacations.registries.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('vacations.registries.*') ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Реестры
                        @if(($menuVacationRegistries ?? 0) > 0)
                            <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full {{ request()->routeIs('vacations.registries.*') ? 'bg-white text-[#5B4FE8]' : 'bg-[#5B4FE8] text-white' }}">{{ $menuVacationRegistries }}</span>
                        @endif
                    </a>
                @endif
                @endif -->
            </nav>

            {{-- Bottom links --}}
            <div class="px-3 py-3 border-t border-gray-100 space-y-0.5">
                @php $knowledgeActive = request()->routeIs('knowledge.*'); @endphp
                <a href="{{ route('knowledge.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ $knowledgeActive ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    @include('partials.nav-icon', ['icon' => 'knowledge', 'active' => $knowledgeActive])
                    База знаний
                </a>
            </div>

            {{-- Обратная связь --}}
            @if(auth()->user()->canSeeMenu('menu.feedback'))
            <div class="px-3 py-3">
            @php $feedbackActive = request()->routeIs('feedback.*'); @endphp
            <a href="{{ route('feedback.index') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $feedbackActive ? 'bg-[#5B4FE8]/10 text-[#5B4FE8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                @include('partials.nav-icon', ['icon' => 'feedback', 'active' => $feedbackActive])
                Обратная связь
            </a>
            </div>
            @endif
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
                <a href="{{ route('dashboard') }}" class="text-base font-bold text-gray-900 mr-2 hidden md:block">Vamin</a>

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
                    @unless(auth()->user()->isExternal())
                    <a href="{{ route('documents.create') }}"
                       class="hidden md:flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Загрузить документ
                    </a>
                    @endunless

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
                <a href="{{ route('tasks', ['filter' => 'pending']) }}" class="flex-1 flex flex-col items-center gap-1 text-xs {{ request()->routeIs('tasks') ? 'text-[#5B4FE8]' : 'text-gray-500' }}">
                    <span class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        @if(($menuActiveTasks ?? 0) > 0)
                            <span class="absolute -top-1.5 -right-1.5 inline-flex items-center justify-center min-w-[1rem] h-4 px-1 text-[10px] font-bold rounded-full bg-[#5B4FE8] text-white">{{ $menuActiveTasks }}</span>
                        @endif
                    </span>
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

    @auth
    {{-- Real-time browser notifications (new document to approve, chat message, trip/vacation approval) --}}
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const uid = {{ auth()->id() }};

        // ── Sound (Web Audio, no asset needed) ───────────────────────────────
        let audioCtx = null;
        const initAudio = () => {
            if (!audioCtx && (window.AudioContext || window.webkitAudioContext)) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
        };
        // Browsers require a user gesture before audio can play, so the
        // AudioContext is created only on the first click — never earlier
        // (that avoids the "AudioContext was not allowed to start" warning).
        document.addEventListener('click', initAudio, { once: true });
        const playBeep = () => {
            if (!audioCtx || audioCtx.state !== 'running') return;
            try {
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.connect(g); g.connect(audioCtx.destination);
                o.type = 'sine'; o.frequency.value = 880;
                g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.25, audioCtx.currentTime + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.35);
                o.start(); o.stop(audioCtx.currentTime + 0.35);
            } catch (e) {}
        };

        // ── Toast (bottom-right) ─────────────────────────────────────────────
        const container = document.createElement('div');
        container.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;max-width:360px;';
        document.body.appendChild(container);

        const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const accentByType = {
            new_message: '#5B4FE8', new_document: '#5B4FE8',
            trip_approval: '#0ea5e9', vacation_approval: '#0ea5e9',
            document_approved: '#22c55e', document_rejected: '#ef4444',
        };

        const showToast = (e) => {
            const accent = accentByType[e.type] || '#5B4FE8';
            const el = document.createElement('div');
            el.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-left:4px solid ${accent};border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.15);padding:12px 14px;cursor:pointer;transition:transform .25s ease,opacity .25s ease;transform:translateX(120%);opacity:0;`;
            el.innerHTML = `<div style="font-weight:600;font-size:13px;color:#111827;margin-bottom:3px;">${escapeHtml(e.title || 'Уведомление')}</div><div style="font-size:12px;color:#4b5563;line-height:1.35;">${escapeHtml(e.body)}</div>`;
            el.onclick = () => { if (e.url) { window.focus(); window.location.href = e.url; } el.remove(); };
            container.appendChild(el);
            requestAnimationFrame(() => { el.style.transform = 'translateX(0)'; el.style.opacity = '1'; });
            setTimeout(() => { el.style.transform = 'translateX(120%)'; el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 7000);
        };

        // For an OPEN & FOCUSED tab: in-page toast + sound.
        // Background / closed tabs are handled by the Web Push service worker
        // (see registerPush below), so we don't fire a native Notification here.
        const handle = (e) => {
            playBeep();
            showToast(e);
        };

        const subscribeEcho = () => {
            if (!window.Echo) { setTimeout(subscribeEcho, 500); return; }
            window.Echo.private('user.' + uid).listen('.notify', handle);
        };
        subscribeEcho();

        // ── Web Push (delivers OS notifications even when the site is closed) ─
        const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        const urlB64ToUint8Array = (base64) => {
            const padding = '='.repeat((4 - (base64.length % 4)) % 4);
            const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(b64);
            const out = new Uint8Array(raw.length);
            for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
            return out;
        };

        const registerPush = async () => {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !vapidPublicKey) {
                console.warn('[push] Web Push не поддерживается в этом браузере');
                return;
            }
            try {
                const reg = await navigator.serviceWorker.register('{{ asset('sw.js') }}');
                if (Notification.permission === 'default') {
                    await Notification.requestPermission();
                }
                if (Notification.permission !== 'granted') {
                    console.warn('[push] нет разрешения на уведомления:', Notification.permission);
                    return;
                }
                await navigator.serviceWorker.ready;
                let sub = await reg.pushManager.getSubscription();
                if (!sub) {
                    sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlB64ToUint8Array(vapidPublicKey),
                    });
                }
                const raw = sub.toJSON();
                await fetch('{{ route('push.subscribe') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ endpoint: raw.endpoint, keys: raw.keys, content_encoding: 'aes128gcm' }),
                });
                console.log('[push] подписка на Web Push оформлена');
            } catch (err) {
                console.error('[push] ошибка регистрации Web Push:', err);
            }
        };
        registerPush();
    });
    </script>
    @endauth
</body>
</html>
