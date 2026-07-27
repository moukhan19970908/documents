<x-app-layout>
    <x-slot name="title">Архив (ECM) — Vamin</x-slot>

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900">Архив (ECM)</h1>
        <p class="text-sm text-gray-500 mt-1">Неизменяемые копии завершённых процессов. Один документ — множество срезов по метаданным.</p>
    </div>

    {{-- Точки входа --}}
    @php
        $entryPoints = [
            'all'               => 'Все',
            'directions'        => 'По направлениям',
            'types'             => 'По типам',
            'counterparties'    => 'По контрагентам',
            'cases_orders'      => 'Дела: Приказы',
            'cases_assignments' => 'Дела: Поручения',
        ];
    @endphp
    <div class="flex flex-wrap items-center gap-1 mb-5 border-b border-gray-200">
        @foreach($entryPoints as $key => $label)
            <a href="{{ route('archive.index', ['view' => $key]) }}"
               class="px-3.5 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                      {{ $view === $key ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="flex gap-5">

        {{-- Left: slice-aware sidebar --}}
        <aside class="w-60 shrink-0 space-y-4">
            @if($view === 'directions')
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-700 uppercase tracking-widest">Направления</div>
                    <div class="py-2">
                        @forelse($directions as $dir)
                            <a href="{{ route('archive.index', ['view' => 'directions', 'direction' => $dir->id]) }}"
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request('direction') == $dir->id ? 'bg-blue-50 text-[#5B4FE8] font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="flex-1 truncate">{{ $dir->name }}</span>
                                <span class="text-xs text-gray-400">{{ $dir->archive_count }}</span>
                            </a>
                            {{-- Отделы выбранного направления --}}
                            @if(request('direction') == $dir->id && $departments->isNotEmpty())
                                <div class="ml-3 border-l border-gray-100">
                                    @foreach($departments as $dept)
                                        <a href="{{ route('archive.index', ['view' => 'directions', 'direction' => $dir->id, 'department' => $dept->id]) }}"
                                           class="flex items-center gap-2 pl-4 pr-4 py-1.5 text-sm {{ request('department') == $dept->id ? 'text-[#5B4FE8] font-medium' : 'text-gray-500 hover:text-gray-900' }}">
                                            <span class="flex-1 truncate">{{ $dept->name }}</span>
                                            <span class="text-xs text-gray-400">{{ $dept->archive_count }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @empty
                            <p class="px-4 py-3 text-sm text-gray-400">Пусто</p>
                        @endforelse
                    </div>
                </div>
            @elseif($view === 'types')
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-700 uppercase tracking-widest">Типы документов</div>
                    <div class="py-2">
                        @foreach($types as $t)
                            <a href="{{ route('archive.index', ['view' => 'types', 'type' => $t->id]) }}"
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request('type') == $t->id ? 'bg-blue-50 text-[#5B4FE8] font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="flex-1 truncate">{{ $t->name }}</span>
                                <span class="text-xs text-gray-400">{{ $t->archive_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @elseif($view === 'counterparties')
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-700 uppercase tracking-widest">Контрагенты</div>
                    <div class="py-2 max-h-[28rem] overflow-y-auto">
                        @forelse($counterparties as $cp)
                            <a href="{{ route('archive.index', ['view' => 'counterparties', 'counterparty' => $cp->counterparty]) }}"
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request('counterparty') === $cp->counterparty ? 'bg-blue-50 text-[#5B4FE8] font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="flex-1 truncate">{{ $cp->counterparty }}</span>
                                <span class="text-xs text-gray-400">{{ $cp->c }}</span>
                            </a>
                        @empty
                            <p class="px-4 py-3 text-sm text-gray-400">Контрагенты не указаны</p>
                        @endforelse
                    </div>
                </div>
            @endif

            {{-- Storage indicator --}}
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
                    <span>Хранилище</span>
                    <span>{{ $storagePercent }}%</span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-[#5B4FE8] rounded-full transition-all" style="width: {{ $storagePercent }}%"></div>
                </div>
            </div>
        </aside>

        {{-- Right: Documents --}}
        <div class="flex-1 min-w-0">
            {{-- Search & Filters --}}
            <form method="GET" class="flex flex-wrap gap-2 mb-4">
                <input type="hidden" name="view" value="{{ $view }}">
                @foreach(['direction', 'department', 'type', 'counterparty'] as $keep)
                    @if(request($keep) !== null && request($keep) !== '')<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
                @endforeach
                <div class="flex-1 min-w-56 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Название, номер, контрагент…"
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                </div>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <button type="submit" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Найти</button>
                @if(collect(['direction','department','type','counterparty','search','date_from','date_to'])->contains(fn($k) => request($k)))
                    <a href="{{ route('archive.index', ['view' => $view]) }}" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-500 hover:bg-gray-50">Сбросить</a>
                @endif
            </form>

            {{-- Table --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                                <th class="text-left px-5 py-3 font-semibold">Дело</th>
                                <th class="text-left px-5 py-3 font-semibold">Автор</th>
                                <th class="text-left px-5 py-3 font-semibold">В архиве с</th>
                                <th class="text-left px-5 py-3 font-semibold">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($documents as $doc)
                                @php
                                    $ext = strtolower(pathinfo($doc->file_name ?? '', PATHINFO_EXTENSION));
                                    $iconColor = match($ext) {
                                        'pdf'  => 'text-red-500 bg-red-50',
                                        'docx', 'doc' => 'text-blue-500 bg-blue-50',
                                        'xlsx', 'xls' => 'text-green-500 bg-green-50',
                                        default => 'text-gray-500 bg-gray-100',
                                    };
                                    $kindLabel = ['document' => 'Документ', 'order' => 'Приказ', 'assignment' => 'Поручение'][$doc->metadata['kind'] ?? ''] ?? null;
                                @endphp
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg {{ $iconColor }} flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('archive.show', $doc) }}" class="font-medium text-gray-900 hover:text-[#5B4FE8]">{{ $doc->title }}</a>
                                                    @if($kindLabel)
                                                        <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-indigo-50 text-[#5B4FE8]">{{ $kindLabel }}</span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-400 mt-0.5">
                                                    {{ $doc->number ?? 'A-' . $doc->id }}
                                                    @if($doc->counterparty) • {{ $doc->counterparty }} @endif
                                                    @if($doc->file_size) • {{ $doc->formatted_size }} @endif
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @if($doc->initiator)
                                            <div class="flex items-center gap-2">
                                                <img src="{{ $doc->initiator->avatar_url }}" class="w-6 h-6 rounded-full" alt="">
                                                <span class="text-gray-700 text-xs">{{ \Illuminate\Support\Str::limit($doc->initiator->name, 18) }}</span>
                                            </div>
                                        @else
                                            <span class="text-gray-400 text-xs">{{ $doc->metadata['initiator'] ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $doc->archived_at?->format('d.m.Y') }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3 text-xs font-medium">
                                            <a href="{{ route('archive.show', $doc) }}" class="text-[#5B4FE8] hover:underline">Дело</a>
                                            @if($doc->file_path)
                                                <a href="{{ route('archive.file', $doc) }}" class="text-gray-600 hover:underline">Файл</a>
                                            @endif
                                            @if($doc->approval_sheet_path)
                                                <a href="{{ route('archive.sheet', $doc) }}" class="text-gray-600 hover:underline">Лист согл.</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-12 text-center text-gray-500">Ничего не найдено</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($documents->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                        <p class="text-xs text-gray-500">Показано {{ $documents->firstItem() }}-{{ $documents->lastItem() }} из {{ $documents->total() }}</p>
                        {{ $documents->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
