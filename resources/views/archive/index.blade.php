<x-app-layout>
    <x-slot name="title">Архив и Репозиторий — ArchManuscript</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Архив и Репозиторий</h1>
        <p class="text-sm text-gray-500 mt-1">Доступ, фильтрация и управление всеми организационными документами по активным процессам и историческим архивам.</p>
    </div>

    <div class="flex gap-5">

        {{-- Left: Folder tree --}}
        <aside class="w-56 shrink-0">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-700 uppercase tracking-widest">Директория</span>
                    <button class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10h18M3 16h18"/></svg>
                    </button>
                </div>
                <div class="py-2">
                    @foreach($folderTree as $typeFolder)
                        <a href="{{ request()->fullUrlWithQuery(['type' => $typeFolder->id]) }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm transition-colors
                                  {{ request('type') == $typeFolder->id ? 'bg-blue-50 text-[#5B4FE8] font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 {{ request('type') == $typeFolder->id ? 'text-[#5B4FE8]' : 'text-gray-400' }}" fill="{{ request('type') == $typeFolder->id ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h4.586a1 1 0 01.707.293L12 7h7a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                            <span class="flex-1 truncate">{{ $typeFolder->name }}</span>
                            <span class="text-xs text-gray-400">{{ $typeFolder->documents_count }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Storage indicator --}}
                <div class="px-4 py-3 border-t border-gray-100">
                    <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
                        <span>Хранилище</span>
                        <span>{{ $storagePercent }}%</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-[#5B4FE8] rounded-full transition-all" style="width: {{ $storagePercent }}%"></div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Right: Documents --}}
        <div class="flex-1 min-w-0">
            {{-- Tabs --}}
            <div class="flex items-center gap-1 mb-4">
                @foreach(['all' => 'Все документы', 'approved' => 'Одобрено', 'rejected' => 'Отклонено'] as $tabKey => $tabLabel)
                    <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey]) }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg transition-colors
                              {{ $tab === $tabKey ? 'bg-white border border-gray-200 text-gray-900' : 'text-gray-600 hover:bg-white hover:border hover:border-gray-200' }}">
                        {{ $tabLabel }}
                    </a>
                @endforeach
            </div>

            {{-- Search & Filters --}}
            <form method="GET" class="flex flex-wrap gap-2 mb-4">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="flex-1 min-w-56 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по метаданным, тегам или контенту..."
                           class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                </div>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <select name="author" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <option value="">Автор</option>
                    @foreach(\App\Models\User::where('is_active', true)->get() as $u)
                        <option value="{{ $u->id }}" {{ request('author') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
                <select name="department" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <option value="">Отдел</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                </button>
            </form>

            {{-- Table --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                                <th class="text-left px-5 py-3 font-semibold">Название документа</th>
                                <th class="text-left px-5 py-3 font-semibold">Автор</th>
                                <th class="text-left px-5 py-3 font-semibold">Дата изменения</th>
                                <th class="text-left px-5 py-3 font-semibold">Статус</th>
                                <th class="text-left px-5 py-3 font-semibold">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($documents as $doc)
                                @php
                                    $statusBadge = [
                                        'approved' => 'bg-green-100 text-green-700',
                                        'signed'   => 'bg-indigo-100 text-indigo-700',
                                        'archived' => 'bg-gray-100 text-gray-600',
                                    ][$doc->status] ?? 'bg-gray-100 text-gray-600';
                                    $ext = strtolower(pathinfo($doc->currentFile?->file_name ?? '', PATHINFO_EXTENSION));
                                    $iconColor = match($ext) {
                                        'pdf'  => 'text-red-500 bg-red-50',
                                        'docx', 'doc' => 'text-blue-500 bg-blue-50',
                                        'xlsx', 'xls' => 'text-green-500 bg-green-50',
                                        default => 'text-gray-500 bg-gray-100',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg {{ $iconColor }} flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </div>
                                            <div>
                                                <a href="{{ route('documents.show', $doc) }}" class="font-medium text-gray-900 hover:text-[#5B4FE8]">
                                                    {{ $doc->currentFile?->file_name ?? $doc->title }}
                                                </a>
                                                <p class="text-xs text-gray-400 mt-0.5">
                                                    {{ $doc->currentFile?->formatted_size ?? '' }} • ID: D-{{ $doc->id }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $doc->initiator->avatar_url }}" class="w-6 h-6 rounded-full" alt="">
                                            <span class="text-gray-700 text-xs">{{ Str::limit($doc->initiator->name, 15) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $doc->updated_at->format('M d, Y') }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded {{ $statusBadge }}">
                                            {{ strtoupper($doc->status_label) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('documents.show', $doc) }}" class="text-[#5B4FE8] text-xs font-medium hover:underline">Открыть</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-12 text-center text-gray-500">Документы не найдены</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($documents->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                        <p class="text-xs text-gray-500">Показано {{ $documents->firstItem() }}-{{ $documents->lastItem() }} из {{ $documents->total() }} документов</p>
                        {{ $documents->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
