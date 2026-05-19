<x-app-layout>
    <x-slot name="title">Папки процессов — Vamin</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Папки процессов</h1>
            <p class="text-sm text-gray-500 mt-1">Управление структурой папок для маршрутов согласования</p>
        </div>
        <a href="{{ route('admin.workflow-folders.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новая папка
        </a>
    </div>

    @if($folders->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
            <p class="text-gray-500 text-sm">Папки ещё не созданы</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($folders as $folder)
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    {{-- Root folder row --}}
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 last:border-0">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#5B4FE8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                            <div>
                                <span class="font-semibold text-gray-900 text-sm">{{ $folder->name }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ $folder->workflows_count }} {{ trans_choice('маршрут|маршрута|маршрутов', $folder->workflows_count) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.workflow-folders.create', ['parent_id' => $folder->id]) }}" class="text-xs text-[#5B4FE8] font-medium hover:underline">+ Подпапка</a>
                            <a href="{{ route('admin.workflow-folders.edit', $folder) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700 border border-gray-200 px-2.5 py-1 rounded-lg hover:bg-gray-50">Изменить</a>
                            <form action="{{ route('admin.workflow-folders.destroy', $folder) }}" method="POST" onsubmit="return confirm('Удалить папку «{{ $folder->name }}» и все вложенные?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-500 border border-red-200 px-2.5 py-1 rounded-lg hover:bg-red-50">Удалить</button>
                            </form>
                        </div>
                    </div>

                    {{-- Sub-folders --}}
                    @foreach($folder->children as $child)
                        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50 last:border-0 bg-gray-50/50">
                            <div class="flex items-center gap-3 pl-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                                <span class="text-sm text-gray-700">{{ $child->name }}</span>
                                <span class="text-xs text-gray-400">{{ $child->workflows_count }} {{ trans_choice('маршрут|маршрута|маршрутов', $child->workflows_count) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.workflow-folders.edit', $child) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700 border border-gray-200 px-2.5 py-1 rounded-lg hover:bg-gray-50">Изменить</a>
                                <form action="{{ route('admin.workflow-folders.destroy', $child) }}" method="POST" onsubmit="return confirm('Удалить папку «{{ $child->name }}»?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-500 border border-red-200 px-2.5 py-1 rounded-lg hover:bg-red-50">Удалить</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6">
        <a href="{{ route('workflows.index') }}" class="text-sm text-[#5B4FE8] hover:underline">← Вернуться к процессам</a>
    </div>
</x-app-layout>
