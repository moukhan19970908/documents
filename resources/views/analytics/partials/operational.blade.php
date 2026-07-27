@php
    $dimension = $data['dimension'];
    $rows = $data['rows'];
    $totals = $data['totals'];
    $maxTotal = max(1, collect($rows)->max('total') ?? 1);
    $segments = [
        'on_approval' => ['label' => 'На согласовании', 'color' => '#3B82F6'],
        'on_ack'      => ['label' => 'На ознакомлении', 'color' => '#8B5CF6'],
        'in_work'     => ['label' => 'В работе',        'color' => '#F59E0B'],
    ];
@endphp

{{-- Разрез --}}
<div class="flex items-center gap-2 mb-4">
    <span class="text-xs text-gray-500 font-medium">Разрез:</span>
    @foreach(['direction' => 'Направление', 'department' => 'Отдел', 'type' => 'Тип'] as $key => $label)
        <a href="{{ route('analytics.index', array_merge(request()->except('page'), ['group' => 'operational', 'dimension' => $key])) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ $dimension === $key ? 'bg-[#5B4FE8] text-white border-[#5B4FE8]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Легенда --}}
<div class="flex flex-wrap items-center gap-4 mb-3 text-xs text-gray-500">
    @foreach($segments as $s)
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['label'] }}</span>
    @endforeach
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-500"></span>Просрочено</span>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                <th class="text-left px-5 py-3 font-semibold w-48">{{ ['direction'=>'Направление','department'=>'Отдел','type'=>'Тип'][$dimension] }}</th>
                <th class="text-left px-5 py-3 font-semibold">Распределение</th>
                <th class="text-center px-3 py-3 font-semibold">Соглас.</th>
                <th class="text-center px-3 py-3 font-semibold">Ознак.</th>
                <th class="text-center px-3 py-3 font-semibold">В работе</th>
                <th class="text-center px-3 py-3 font-semibold">Просроч.</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($rows as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $row['label'] }}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center h-3 rounded-full overflow-hidden bg-gray-100" style="width: {{ max(4, round($row['total'] / $maxTotal * 100)) }}%">
                            @foreach($segments as $key => $s)
                                @if($row[$key] > 0)
                                    <div class="h-full" title="{{ $s['label'] }}: {{ $row[$key] }}"
                                         style="width: {{ $row['total'] > 0 ? round($row[$key] / $row['total'] * 100) : 0 }}%; background: {{ $s['color'] }}"></div>
                                @endif
                            @endforeach
                        </div>
                    </td>
                    <td class="text-center px-3 py-3 text-gray-700">{{ $row['on_approval'] ?: '—' }}</td>
                    <td class="text-center px-3 py-3 text-gray-700">{{ $row['on_ack'] ?: '—' }}</td>
                    <td class="text-center px-3 py-3 text-gray-700">{{ $row['in_work'] ?: '—' }}</td>
                    <td class="text-center px-3 py-3">
                        @if($row['overdue'] > 0)
                            <span class="inline-flex px-2 py-0.5 rounded-full bg-red-50 text-red-600 text-xs font-semibold">{{ $row['overdue'] }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">Нет данных за выбранный период</td></tr>
            @endforelse
        </tbody>
        @if(!empty($rows))
            <tfoot>
                <tr class="border-t border-gray-200 bg-gray-50 font-semibold text-gray-900">
                    <td class="px-5 py-3">Итого</td>
                    <td></td>
                    <td class="text-center px-3 py-3">{{ $totals['on_approval'] }}</td>
                    <td class="text-center px-3 py-3">{{ $totals['on_ack'] }}</td>
                    <td class="text-center px-3 py-3">{{ $totals['in_work'] }}</td>
                    <td class="text-center px-3 py-3 text-red-600">{{ $totals['overdue'] }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
