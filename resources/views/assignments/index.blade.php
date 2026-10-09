<x-app-layout>
    <x-slot name="title">Поручения — Vamin</x-slot>

    @php
        $statusColors = [
            'assigned' => 'bg-gray-100 text-gray-600', 'in_progress' => 'bg-blue-50 text-blue-600',
            'submitted' => 'bg-amber-50 text-amber-600', 'done' => 'bg-emerald-50 text-emerald-600',
            'returned' => 'bg-red-50 text-red-600',
        ];
    @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-900">Поручения</h1>
        <div class="flex items-center gap-2">
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.assignments.settings') }}"
               class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Правила
            </a>
        @endif
        @if($canInitiate)
            <a href="{{ route('assignments.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Поставить поручение
            </a>
        @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    {{-- Вкладки --}}
    <div class="flex items-center gap-1 border-b border-gray-200 mb-5 text-sm">
        @php
            $tabs = ['incoming' => 'Мне на исполнение', 'outgoing' => 'Я поставил'];
            if ($canAll) $tabs['all'] = 'Все поручения';
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('assignments.index', ['tab' => $key]) }}"
               class="px-4 py-2.5 -mb-px border-b-2 font-medium {{ $tab === $key ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                {{ $label }}
                @if($key === 'incoming' && $incomingPending > 0)
                    <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full bg-[#5B4FE8] text-white">{{ $incomingPending }}</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Фильтры --}}
    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по номеру или теме…"
               class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] w-64">
        <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <option value="">Все статусы</option>
            @foreach($statuses as $code => $label)
                <option value="{{ $code }}" @selected(request('status') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Фильтр</button>
    </form>

    {{-- Список --}}
    <div class="space-y-2">
        @forelse($assignments as $a)
            {{-- Вся строка кликабельна (ссылка растянута на строку), кнопка удаления лежит поверх неё. --}}
            <div class="relative flex items-center gap-4 bg-white border border-gray-200 rounded-xl px-5 py-4 hover:shadow-sm hover:border-[#5B4FE8]/40 transition">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-xs text-gray-400">{{ $a->number ?? 'ПОР-…' }}</span>
                        @if($a->parent_id)
                            <span class="text-[10px] uppercase tracking-wide text-gray-400">подпоручение</span>
                        @endif
                    </div>
                    <h3 class="font-semibold text-gray-900 truncate">
                        <a href="{{ route('assignments.show', $a) }}" class="after:absolute after:inset-0 after:rounded-xl focus:outline-none focus-visible:underline">{{ $a->title }}</a>
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Постановщик: {{ $a->initiator->name }} · Исполнитель: {{ $a->executor->name }}
                    </p>
                </div>

                @if($a->due_at)
                    <div class="text-xs shrink-0 {{ $a->isOverdue() ? 'text-red-500 font-semibold' : 'text-gray-400' }}">
                        до {{ $a->due_at->format('d.m.Y') }}
                        @if($a->isOverdue()) · просрочено @endif
                    </div>
                @endif

                <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $statusColors[$a->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $a->statusLabel() }}</span>

                @if($a->canBeDeletedBy(auth()->user()))
                    @php $confirm = "Удалить поручение {$a->number}?" . ($a->children_count > 0 ? ' Вместе с ним удалятся все его подпоручения.' : ''); @endphp
                    <form method="POST" action="{{ route('assignments.destroy', $a) }}" class="relative z-10 shrink-0"
                          onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }})">
                        @csrf @method('DELETE')
                        <button type="submit" title="Удалить поручение" aria-label="Удалить поручение {{ $a->number }}"
                                class="p-2 border border-gray-200 rounded-lg text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-16">
                Поручений нет.
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>
</x-app-layout>
