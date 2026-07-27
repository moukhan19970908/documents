@php
    // Часы → «Xд Yч» / «Zч».
    $fmt = function ($h) {
        $h = (float) $h;
        if ($h >= 24) {
            $d = floor($h / 24);
            $r = round($h - $d * 24);
            return $r > 0 ? "{$d}д {$r}ч" : "{$d}д";
        }
        return round($h, 1) . 'ч';
    };
    $maxStage = max(1, collect($data['stages'])->max('avg_hours') ?? 1);
@endphp

{{-- KPI --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-widest">Соблюдение сроков</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $data['on_time_pct'] === null ? '—' : $data['on_time_pct'] . '%' }}</p>
        <p class="text-xs text-gray-400 mt-1">звеньев закрыто в срок</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-widest">Завершённых звеньев</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $data['total_completed'] }}</p>
        <p class="text-xs text-gray-400 mt-1">в выборке</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 uppercase tracking-widest">Самое узкое место</p>
        @if(!empty($data['hotspots']))
            <p class="text-lg font-bold text-gray-900 mt-1 leading-tight">{{ $fmt($data['hotspots'][0]['avg_hours']) }}</p>
            <p class="text-xs text-gray-400 mt-1 truncate">{{ $data['hotspots'][0]['stage'] }} · {{ $data['hotspots'][0]['approver'] }}</p>
        @else
            <p class="text-3xl font-bold text-gray-300 mt-1">—</p>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Среднее время на каждом звене --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-4">Среднее время на звене</h2>
        @forelse($data['stages'] as $s)
            <div class="mb-3 last:mb-0">
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-700 truncate">{{ $s['name'] }}</span>
                    <span class="text-gray-500 shrink-0 ml-2">{{ $fmt($s['avg_hours']) }}
                        @if($s['on_time_pct'] !== null)
                            <span class="text-xs {{ $s['on_time_pct'] >= 80 ? 'text-emerald-600' : ($s['on_time_pct'] >= 50 ? 'text-amber-600' : 'text-red-600') }}">· {{ $s['on_time_pct'] }}% в срок</span>
                        @endif
                    </span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full bg-[#5B4FE8]" style="width: {{ max(3, round($s['avg_hours'] / $maxStage * 100)) }}%"></div>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">{{ $s['count'] }} звеньев</p>
            </div>
        @empty
            <p class="text-sm text-gray-400 py-8 text-center">Нет завершённых звеньев в выборке</p>
        @endforelse
    </div>

    {{-- Кто держит дольше всего --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-4">Согласующие: время до решения</h2>
        @forelse($data['approvers'] as $a)
            <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0 text-sm">
                <span class="text-gray-800 truncate">{{ $a['name'] }}</span>
                <span class="shrink-0 ml-2">
                    <span class="font-medium text-gray-900">{{ $fmt($a['avg_hours']) }}</span>
                    <span class="text-xs text-gray-400">· {{ $a['count'] }} реш.</span>
                </span>
            </div>
        @empty
            <p class="text-sm text-gray-400 py-8 text-center">Нет решений в выборке</p>
        @endforelse
    </div>
</div>

{{-- Точки роста: звено × согласующий --}}
@if(!empty($data['hotspots']))
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mt-6">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Узкие места: звено × согласующий</h2>
            <p class="text-xs text-gray-400 mt-0.5">Где именно медленно — не «вообще», а на конкретном звене у конкретного человека.</p>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-2.5 font-semibold">Звено</th>
                    <th class="text-left px-5 py-2.5 font-semibold">Согласующий</th>
                    <th class="text-right px-5 py-2.5 font-semibold">Среднее время</th>
                    <th class="text-right px-5 py-2.5 font-semibold">Решений</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($data['hotspots'] as $h)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2.5 text-gray-700">{{ $h['stage'] }}</td>
                        <td class="px-5 py-2.5 text-gray-900 font-medium">{{ $h['approver'] }}</td>
                        <td class="px-5 py-2.5 text-right text-gray-900">{{ $fmt($h['avg_hours']) }}</td>
                        <td class="px-5 py-2.5 text-right text-gray-500">{{ $h['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
