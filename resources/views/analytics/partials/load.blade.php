@php
    $maxPending = max(1, collect($data['rows'])->max('pending') ?? 1);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-widest">Открытых задач</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $data['total_pending'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-widest">Просрочено</p>
        <p class="text-3xl font-bold text-red-600 mt-1">{{ $data['total_overdue'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-widest">Исполнителей с задачами</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $data['people'] }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-5">
    <h2 class="font-semibold text-gray-900 mb-4">Нагрузка по людям</h2>
    @forelse($data['rows'] as $row)
        <div class="mb-3 last:mb-0">
            <div class="flex items-center justify-between text-sm mb-1">
                <span class="text-gray-800 truncate">{{ $row['name'] }}</span>
                <span class="shrink-0 ml-2 text-gray-500">
                    {{ $row['pending'] }} задач
                    @if($row['overdue'] > 0)<span class="text-red-600 font-medium">· {{ $row['overdue'] }} просроч.</span>@endif
                </span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 overflow-hidden flex">
                @php $overduePct = $row['pending'] > 0 ? round($row['overdue'] / $row['pending'] * 100) : 0; @endphp
                <div class="h-full bg-red-500" style="width: {{ round($row['pending'] / $maxPending * 100) * $overduePct / 100 }}%"></div>
                <div class="h-full bg-[#5B4FE8]" style="width: {{ round($row['pending'] / $maxPending * 100) * (100 - $overduePct) / 100 }}%"></div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400 py-8 text-center">Нет открытых задач</p>
    @endforelse
</div>
