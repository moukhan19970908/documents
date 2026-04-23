<x-app-layout>
    <x-slot name="title">Документы — ArchManuscript</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Документы</h1>
            <p class="text-sm text-gray-500 mt-1">Все документы рабочего пространства</p>
        </div>
        <a href="{{ route('documents.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новый документ
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="text-xs text-gray-500 font-medium block mb-1">Поиск</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Название документа..."
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>
        <div class="w-40">
            <label class="text-xs text-gray-500 font-medium block mb-1">Тип</label>
            <select name="type" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Все типы</option>
                @foreach($documentTypes as $type)
                    <option value="{{ $type->slug }}" {{ request('type') === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-40">
            <label class="text-xs text-gray-500 font-medium block mb-1">Статус</label>
            <select name="status" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Все статусы</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Черновик</option>
                <option value="in_review" {{ request('status') === 'in_review' ? 'selected' : '' }}>На одобрении</option>
                <option value="requires_changes" {{ request('status') === 'requires_changes' ? 'selected' : '' }}>Требует изменений</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Одобрено</option>
                <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Архив</option>
            </select>
        </div>
        <div class="w-36">
            <label class="text-xs text-gray-500 font-medium block mb-1">Дата от</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>
        <div class="w-36">
            <label class="text-xs text-gray-500 font-medium block mb-1">Дата до</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>
        <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">Найти</button>
        @if(request()->hasAny(['search', 'type', 'status', 'date_from', 'date_to']))
            <a href="{{ route('documents.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold">Документ</th>
                        <th class="text-left px-5 py-3 font-semibold">Тип</th>
                        <th class="text-left px-5 py-3 font-semibold">Инициатор</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                        <th class="text-left px-5 py-3 font-semibold">Обновлён</th>
                        <th class="text-left px-5 py-3 font-semibold">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($documents as $doc)
                        @php
                            $statusBadge = [
                                'draft'            => 'bg-gray-100 text-gray-600',
                                'in_review'        => 'bg-blue-100 text-blue-700',
                                'requires_changes' => 'bg-red-100 text-red-700',
                                'approved'         => 'bg-green-100 text-green-700',
                                'signed'           => 'bg-indigo-100 text-indigo-700',
                                'archived'         => 'bg-gray-100 text-gray-500',
                            ][$doc->status] ?? 'bg-gray-100 text-gray-600';
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('documents.show', $doc) }}" class="font-medium text-gray-900 hover:text-[#5B4FE8]">
                                    {{ $doc->title }}
                                </a>
                                <p class="text-xs text-gray-400 mt-0.5">ID: D-{{ $doc->id }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $doc->type?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <img src="{{ $doc->initiator->avatar_url }}" class="w-6 h-6 rounded-full" alt="">
                                    <span class="text-gray-700">{{ Str::limit($doc->initiator->name, 20) }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded {{ $statusBadge }}">
                                    {{ $doc->status_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $doc->updated_at->format('d.m.Y') }}</td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('documents.show', $doc) }}" class="text-[#5B4FE8] text-xs font-medium hover:underline">Открыть</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-gray-500">Документы не найдены</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
