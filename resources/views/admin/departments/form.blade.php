<x-app-layout>
    <x-slot name="title">{{ isset($department) ? 'Редактировать отдел' : 'Новый отдел' }} — Vamin</x-slot>

    <div class="max-w-xl">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ isset($department) ? 'Редактировать отдел' : 'Новый отдел' }}</h1>

        <form action="{{ isset($department) ? route('admin.departments.update', $department) : route('admin.departments.store') }}" method="POST" class="space-y-5">
            @csrf
            @if(isset($department)) @method('PUT') @endif

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название *</label>
                    <input type="text" name="name" value="{{ old('name', $department->name ?? '') }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Родительский отдел</label>
                    <select name="parent_id" class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— Нет (корневой отдел) —</option>
                        @foreach($departments as $d)
                            @if(!isset($department) || $d->id !== $department->id)
                                <option value="{{ $d->id }}" {{ old('parent_id', $department->parent_id ?? '') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Руководитель</label>
                    <select name="head_user_id" class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">— Не назначен —</option>
                        @foreach($users as $m)
                            <option value="{{ $m->id }}" {{ old('head_user_id', $department->head_user_id ?? '') == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                    {{ isset($department) ? 'Сохранить' : 'Создать отдел' }}
                </button>
                <a href="{{ route('admin.departments.index') }}" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Отмена</a>
            </div>
        </form>
    </div>
</x-app-layout>
