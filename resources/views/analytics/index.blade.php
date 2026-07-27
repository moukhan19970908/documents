<x-app-layout>
    <x-slot name="title">Аналитика — Vamin</x-slot>

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900">Аналитика</h1>
        <p class="text-sm text-gray-500 mt-1">Готовые дашборды по процессам: операционная картина, узкие места, пропускная способность.</p>
    </div>

    {{-- Группы метрик --}}
    <div class="flex flex-wrap items-center gap-1 mb-5 border-b border-gray-200">
        @foreach($groups as $key => $label)
            <a href="{{ route('analytics.index', array_merge(request()->except(['group','page']), ['group' => $key])) }}"
               class="px-3.5 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                      {{ $group === $key ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Фильтры разреза --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
        <input type="hidden" name="group" value="{{ $group }}">
        @if($group === 'operational')
            <input type="hidden" name="dimension" value="{{ $data['dimension'] ?? 'direction' }}">
        @endif
        <div class="w-52">
            <label class="text-xs text-gray-500 font-medium block mb-1">Направление</label>
            <select name="direction" onchange="this.form.submit()" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Все направления</option>
                @foreach($directions as $d)
                    <option value="{{ $d->id }}" {{ $filters['direction'] === $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-52">
            <label class="text-xs text-gray-500 font-medium block mb-1">Тип документа</label>
            <select name="type" onchange="this.form.submit()" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="">Все типы</option>
                @foreach($types as $t)
                    <option value="{{ $t->id }}" {{ $filters['type'] === $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 font-medium block mb-1">Период (создано)</label>
            <div class="flex items-center gap-2">
                <input type="date" name="from" value="{{ $filters['from'] }}" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <span class="text-gray-400 text-sm">—</span>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            </div>
        </div>
        <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Применить</button>
        @if($filters['direction'] || $filters['type'] || $filters['from'] || $filters['to'])
            <a href="{{ route('analytics.index', ['group' => $group]) }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
        @endif
    </form>

    @switch($group)
        @case('operational')
            @include('analytics.partials.operational')
            @break
        @case('sla')
            @include('analytics.partials.sla')
            @break
        @case('throughput')
            @include('analytics.partials.throughput')
            @break
        @case('load')
            @include('analytics.partials.load')
            @break
        @case('domain')
            @include('analytics.partials.domain')
            @break
    @endswitch
</x-app-layout>
