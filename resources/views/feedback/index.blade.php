<x-app-layout>
    <x-slot name="title">Обратная связь — Vamin</x-slot>

    @php
        $statusClasses = ['blue' => 'bg-blue-50 text-blue-600', 'amber' => 'bg-amber-50 text-amber-600', 'violet' => 'bg-violet-50 text-violet-600', 'gray' => 'bg-gray-100 text-gray-600'];
        $catClasses = ['red' => 'bg-red-50 text-red-600', 'emerald' => 'bg-emerald-50 text-emerald-600', 'blue' => 'bg-blue-50 text-blue-600', 'gray' => 'bg-gray-100 text-gray-600'];
    @endphp

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Обратная связь</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $canViewAll ? 'Все обращения пользователей.' : 'Ваши обращения: ошибки, пожелания, вопросы.' }}</p>
        </div>
        <a href="{{ route('feedback.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новое обращение
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    {{-- Фильтры — только для обработчиков --}}
    @if($canViewAll)
        <form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
            <select name="status" onchange="this.form.submit()" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Статус: Все</option>
                @foreach(\App\Models\Feedback::STATUSES as $k => $v)
                    <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                @endforeach
            </select>
            <select name="category" onchange="this.form.submit()" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Категория: Все</option>
                @foreach(\App\Models\Feedback::CATEGORIES as $k => $v)
                    <option value="{{ $k }}" @selected(request('category')===$k)>{{ $v }}</option>
                @endforeach
            </select>
            <select name="author" onchange="this.form.submit()" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Автор: Все</option>
                @foreach($authors as $a)
                    <option value="{{ $a->id }}" @selected(request('author')==$a->id)>{{ $a->name }}</option>
                @endforeach
            </select>
            <select name="direction" onchange="this.form.submit()" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Направление: Все</option>
                @foreach($directions as $d)
                    <option value="{{ $d->id }}" @selected(request('direction')==$d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
            @if(request()->hasAny(['status','category','author','direction']))
                <a href="{{ route('feedback.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
            @endif
        </form>
    @endif

    <div class="space-y-3">
        @forelse($items as $item)
            <a href="{{ route('feedback.show', $item) }}" class="block bg-white rounded-xl border border-gray-200 px-5 py-4 hover:border-[#5B4FE8]/40 transition-colors">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $catClasses[$item->categoryColor()] }}">{{ $item->categoryLabel() }}</span>
                    <span class="font-semibold text-gray-900">{{ $item->subject }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $statusClasses[$item->statusColor()] }}">{{ $item->statusLabel() }}</span>
                </div>
                <p class="text-sm text-gray-500 mt-1 line-clamp-1">{{ \Illuminate\Support\Str::limit($item->body, 140) }}</p>
                <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                    @if($canViewAll)<span>{{ $item->user->name }}</span> ·@endif
                    <span>{{ $item->created_at->translatedFormat('j M Y, H:i') }}</span>
                    @if($item->messages_count > 0)
                        · <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.8L3 20l1.3-3.9A7.9 7.9 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            {{ $item->messages_count }}
                        </span>
                    @endif
                </div>
            </a>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">
                {{ $canViewAll ? 'Обращений нет.' : 'У вас пока нет обращений. Создайте первое.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-5">{{ $items->links() }}</div>
</x-app-layout>
