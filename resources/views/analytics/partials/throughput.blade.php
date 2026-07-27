@php
    $max = max(1, max($data['created'] ?: [0]), max($data['completed'] ?: [0]));
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-widest">Создано за период</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $data['total_created'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-widest">Завершено за период</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $data['total_completed'] }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-5">
    <div class="flex items-center justify-between mb-5">
        <h2 class="font-semibold text-gray-900">Динамика по месяцам</h2>
        <div class="flex items-center gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#5B4FE8]"></span>Создано</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-emerald-500"></span>Завершено</span>
        </div>
    </div>

    @if($data['total_created'] === 0 && $data['total_completed'] === 0)
        <p class="text-sm text-gray-400 py-10 text-center">Нет данных за выбранный период</p>
    @else
        <div class="flex items-end gap-2 h-48 overflow-x-auto pb-2">
            @foreach($data['months'] as $i => $month)
                <div class="flex-1 min-w-[2.5rem] flex flex-col items-center gap-1">
                    <div class="flex items-end justify-center gap-1 w-full h-40">
                        <div class="w-1/2 max-w-[14px] bg-[#5B4FE8] rounded-t" style="height: {{ round($data['created'][$i] / $max * 100) }}%" title="Создано: {{ $data['created'][$i] }}"></div>
                        <div class="w-1/2 max-w-[14px] bg-emerald-500 rounded-t" style="height: {{ round($data['completed'][$i] / $max * 100) }}%" title="Завершено: {{ $data['completed'][$i] }}"></div>
                    </div>
                    <span class="text-[10px] text-gray-400 whitespace-nowrap">{{ $month }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
