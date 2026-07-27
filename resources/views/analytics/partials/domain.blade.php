@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' ₽';
    $credit = $data['credit'];
    $abs = $data['absences'];
    $asg = $data['assignments'];
    $asgTotal = $asg['on_time'] + $asg['late'];
@endphp

<div class="space-y-6">

    {{-- Кредитный комитет --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Кредитный комитет — суммы по контрагентам</h2>
            <span class="text-sm text-gray-500">Итого: <span class="font-semibold text-gray-900">{{ $money($credit['total']) }}</span> · {{ $credit['count'] }} заявок</span>
        </div>
        @if(empty($credit['rows']))
            <p class="text-sm text-gray-400 py-10 text-center">Нет заявок кредитного комитета за период</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-5 py-2.5 font-semibold">Контрагент</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Сумма</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Заявок</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($credit['rows'] as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 text-gray-900">{{ $r['contractor'] }}</td>
                            <td class="px-5 py-2.5 text-right font-medium text-gray-900">{{ $money($r['total']) }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-500">{{ $r['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Командировки / отпуска --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Командировки и отпуска</h2>
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div>
                    <p class="text-xs text-gray-400">Затраты на командировки</p>
                    <p class="text-lg font-bold text-gray-900">{{ $money($abs['trip_cost']) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Командировок</p>
                    <p class="text-lg font-bold text-gray-900">{{ $abs['trips'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Отпусков</p>
                    <p class="text-lg font-bold text-gray-900">{{ $abs['vacations'] }}</p>
                </div>
            </div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Отсутствуют сейчас ({{ count($abs['current']) }})</p>
            @forelse($abs['current'] as $a)
                <div class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0 text-sm">
                    <span class="text-gray-800">{{ $a['name'] }}</span>
                    <span class="text-xs text-gray-500">{{ $a['type'] }} · до {{ $a['until'] }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-4 text-center">Сейчас никто не отсутствует</p>
            @endforelse
        </div>

        {{-- Поручения: дисциплина --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Поручения — исполнительская дисциплина</h2>
            <div class="flex items-center gap-4 mb-4">
                <div class="text-4xl font-bold text-gray-900">{{ $asg['discipline_pct'] === null ? '—' : $asg['discipline_pct'] . '%' }}</div>
                <div class="text-sm text-gray-500">
                    <div><span class="text-emerald-600 font-medium">{{ $asg['on_time'] }}</span> в срок · <span class="text-amber-600 font-medium">{{ $asg['late'] }}</span> с опозданием</div>
                    <div><span class="text-red-600 font-medium">{{ $asg['open_overdue'] }}</span> открытых просроченных</div>
                </div>
            </div>
            @if($asgTotal > 0)
                <div class="h-2 rounded-full overflow-hidden flex mb-4">
                    <div class="h-full bg-emerald-500" style="width: {{ round($asg['on_time'] / $asgTotal * 100) }}%"></div>
                    <div class="h-full bg-amber-500" style="width: {{ round($asg['late'] / $asgTotal * 100) }}%"></div>
                </div>
            @endif
            @if(!empty($asg['executors']))
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">По исполнителям</p>
                <div class="space-y-1.5">
                    @foreach($asg['executors'] as $e)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-800 truncate">{{ $e['name'] }}</span>
                            <span class="text-xs text-gray-500 shrink-0 ml-2">
                                {{ $e['done'] }} выполнено · <span class="text-emerald-600">{{ $e['on_time'] }}</span> в срок
                                @if($e['late'] > 0)· <span class="text-amber-600">{{ $e['late'] }}</span> с опозд.@endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 py-2 text-center">Нет выполненных поручений в выборке</p>
            @endif
        </div>
    </div>
</div>
