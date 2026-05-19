<x-app-layout>
    <x-slot name="title">Новая папка — Vamin</x-slot>

    <div class="max-w-lg">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('admin.workflow-folders.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Новая папка</h1>
        </div>

        <form action="{{ route('admin.workflow-folders.store') }}" method="POST">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название папки *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7]"
                           placeholder="Например: Продажи">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Родительская папка</label>
                    <select name="parent_id"
                            class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#6C5CE7] bg-white">
                        <option value="">— Корневая папка (верхний уровень) —</option>
                        @foreach($rootFolders as $root)
                            <option value="{{ $root->id }}" {{ old('parent_id', request('parent_id')) == $root->id ? 'selected' : '' }}>
                                {{ $root->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('parent_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-gray-400 mt-1">Оставьте пустым для папки верхнего уровня (например: «Продажи»). Выберите родительскую папку для подпапки (например: «Согласование договоров»).</p>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#6C5CE7] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Создать папку
                </button>
                <a href="{{ route('admin.workflow-folders.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                    Отмена
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
