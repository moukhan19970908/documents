<x-app-layout>
    <x-slot name="title">Маршруты согласования — Vamin</x-slot>

    <div class="flex gap-5 items-start">

        {{-- Folder sidebar --}}
        <aside class="w-60 shrink-0" x-data="{ openFolders: {} }">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Папки</span>
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.workflow-folders.index') }}" title="Управление папками"
                           class="text-gray-400 hover:text-[#5B4FE8] transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </a>
                    @endif
                </div>

                <nav class="py-1">
                    {{-- All workflows --}}
                    <a href="{{ route('workflows.index') }}"
                       class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm transition-colors
                              {{ !$folderId ? 'bg-[#5B4FE8]/5 text-[#5B4FE8] font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        <span class="flex items-center gap-2.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            Все процессы
                        </span>
                        <span class="text-xs {{ !$folderId ? 'text-[#5B4FE8]/70' : 'text-gray-400' }}">{{ $totalCount }}</span>
                    </a>

                    {{-- Root folders --}}
                    @foreach($folderTree as $folder)
                        <div>
                            <div class="flex items-center">
                                @if($folder->children->isNotEmpty())
                                    <button @click="openFolders[{{ $folder->id }}] = !openFolders[{{ $folder->id }}]"
                                            class="pl-3 pr-1 py-2.5 text-gray-400 hover:text-gray-600 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 transition-transform duration-150"
                                             :class="openFolders[{{ $folder->id }}] ? 'rotate-90' : ''"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                @else
                                    <span class="pl-7 shrink-0"></span>
                                @endif
                                <a href="{{ route('workflows.index', ['folder_id' => $folder->id]) }}"
                                   @if($folder->children->isNotEmpty())
                                       @click="openFolders[{{ $folder->id }}] = true"
                                   @endif
                                   class="flex-1 flex items-center justify-between gap-2 pr-4 py-2.5 text-sm transition-colors
                                          {{ $folderId == $folder->id ? 'text-[#5B4FE8] font-medium' : 'text-gray-700 hover:text-gray-900' }}">
                                    <span class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 {{ $folderId == $folder->id ? 'text-[#5B4FE8]' : 'text-gray-400' }}"
                                             fill="{{ $folderId == $folder->id ? 'currentColor' : 'none' }}"
                                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                        </svg>
                                        {{ $folder->name }}
                                    </span>
                                    <span class="text-xs {{ $folderId == $folder->id ? 'text-[#5B4FE8]/70' : 'text-gray-400' }}">{{ $folder->children->isNotEmpty() ? $folder->children->sum('workflows_count') : $folder->workflows_count }}</span>
                                </a>
                            </div>

                            {{-- Sub-folders --}}
                            @if($folder->children->isNotEmpty())
                                <div x-show="openFolders[{{ $folder->id }}]"
                                     x-init="openFolders[{{ $folder->id }}] = {{ $folder->children->contains('id', (int) $folderId) ? 'true' : 'false' }}"
                                     x-transition:enter="transition-all duration-150"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     style="display:none">
                                    @foreach($folder->children as $child)
                                        <a href="{{ route('workflows.index', ['folder_id' => $child->id]) }}"
                                           class="flex items-center justify-between gap-2 pl-9 pr-4 py-2 text-sm transition-colors
                                                  {{ $folderId == $child->id ? 'text-[#5B4FE8] font-medium bg-[#5B4FE8]/5' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800' }}">
                                            <span class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 {{ $folderId == $child->id ? 'text-[#5B4FE8]' : 'text-gray-300' }}"
                                                     fill="{{ $folderId == $child->id ? 'currentColor' : 'none' }}"
                                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                                </svg>
                                                {{ $child->name }}
                                            </span>
                                            <span class="text-xs {{ $folderId == $child->id ? 'text-[#5B4FE8]/70' : 'text-gray-400' }}">{{ $child->workflows_count }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if($folderTree->isEmpty())
                        <p class="px-4 py-3 text-xs text-gray-400">Папки не созданы</p>
                    @endif
                </nav>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 min-w-0">
            {{-- Header --}}
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">
                        {{ $currentFolder ? $currentFolder->name : 'Все процессы' }}
                    </h1>
                    @if($currentFolder?->parent)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $currentFolder->parent->name }}</p>
                    @endif
                </div>
                <a href="{{ route('workflows.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Новый маршрут
                </a>
            </div>

            {{-- Workflows list --}}
            <div class="space-y-3">
                @forelse($workflows as $wf)
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <h2 class="font-semibold text-gray-900 truncate">{{ $wf->name }}</h2>
                                @if($wf->description)
                                    <p class="text-sm text-gray-500 mt-0.5 line-clamp-1">{{ $wf->description }}</p>
                                @endif
                                {{-- Folder badges --}}
                                @if($wf->folders->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @foreach($wf->folders as $wfFolder)
                                            <a href="{{ route('workflows.index', ['folder_id' => $wfFolder->id]) }}"
                                               class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 hover:bg-[#5B4FE8]/10 hover:text-[#5B4FE8] rounded-md px-2 py-0.5 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                                                {{ $wfFolder->name }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs px-2 py-1 rounded-full font-medium {{ $wf->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $wf->is_active ? 'Активен' : 'Черновик' }}
                                </span>
                                <a href="{{ route('workflows.builder', $wf) }}" class="text-xs font-medium text-[#5B4FE8] border border-[#5B4FE8] px-3 py-1.5 rounded-lg hover:bg-indigo-50">Редактировать</a>
                                <form action="{{ route('workflows.destroy', $wf) }}" method="POST" onsubmit="return confirm('Удалить маршрут?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50">Удалить</button>
                                </form>
                            </div>
                        </div>

                        {{-- Stages --}}
                        <div class="flex items-center gap-2 flex-wrap">
                            @foreach($wf->stages->sortBy('sort_order') as $i => $stage)
                                @if($i > 0)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                @endif
                                <div class="flex flex-col items-center">
                                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-1.5 text-xs font-medium text-indigo-700">
                                        {{ $stage->name }}
                                    </div>
                                    <span class="text-xs text-gray-400 mt-1">{{ $stage->approvers_count }} {{ trans_choice('согласующий|согласующих|согласующих', $stage->approvers_count) }}</span>
                                </div>
                            @endforeach
                            @if($wf->stages->isEmpty())
                                <p class="text-sm text-gray-400">Нет этапов — <a href="{{ route('workflows.builder', $wf) }}" class="text-[#5B4FE8] hover:underline">добавить</a></p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
                        @if($currentFolder)
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                            <p class="text-gray-500 text-sm">В этой папке нет маршрутов</p>
                            <a href="{{ route('workflows.create') }}" class="mt-3 inline-block text-sm text-[#5B4FE8] hover:underline">Создать маршрут</a>
                        @else
                            <p class="text-gray-500">Маршруты ещё не созданы</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</x-app-layout>
