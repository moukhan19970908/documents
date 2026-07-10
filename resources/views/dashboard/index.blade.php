<x-app-layout>
    <x-slot name="title">Дашборд — Vamin.ru</x-slot>

    {{-- Greeting --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">
            Здравствуйте, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') ?: auth()->user()->name }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">{{ \Illuminate\Support\Str::ucfirst(now()->translatedFormat('l, j F')) }}</p>
    </div>

    {{-- Stat cards --}}
    @php
        $avg = $stats['avg_approval_days'];
        if ($avg === null) {
            $avgLabel = '—';
        } else {
            $n     = (int) floor($avg);
            $mod10 = $n % 10; $mod100 = $n % 100;
            $word  = (floor($avg) != $avg || ($mod10 >= 2 && $mod10 <= 4 && !($mod100 >= 12 && $mod100 <= 14)))
                        ? 'дня'
                        : (($mod10 == 1 && $mod100 != 11) ? 'день' : 'дней');
            $avgNum   = floor($avg) == $avg ? number_format($avg, 0) : number_format($avg, 1, ',', '');
            $avgLabel = $avgNum . ' ' . $word;
        }
        $cards = [
            ['value' => $stats['in_work_count'], 'label' => 'Документов в работе',      'bg' => 'bg-indigo-50',  'fg' => 'text-indigo-500', 'icon' => 'document'],
            ['value' => $stats['pending_count'], 'label' => 'Ждут вашего действия',      'bg' => 'bg-violet-50',  'fg' => 'text-violet-500', 'icon' => 'tasks', 'link' => route('tasks', ['filter' => 'pending'])],
            ['value' => $stats['overdue_count'], 'label' => 'Просрочено',               'bg' => 'bg-red-50',     'fg' => 'text-red-500',    'icon' => 'clock', 'danger' => $stats['overdue_count'] > 0],
            ['value' => $avgLabel,               'label' => 'Среднее время согласования', 'bg' => 'bg-sky-50',    'fg' => 'text-sky-500',    'icon' => 'chart'],
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($cards as $card)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start justify-between">
                    <div class="w-10 h-10 rounded-lg {{ $card['bg'] }} {{ $card['fg'] }} flex items-center justify-center">
                        @switch($card['icon'])
                            @case('document')
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                @break
                            @case('tasks')
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                @break
                            @case('clock')
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @break
                            @case('chart')
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                @break
                        @endswitch
                    </div>
                    @isset($card['link'])
                        <a href="{{ $card['link'] }}" class="text-xs font-medium text-[#5B4FE8] hover:underline">Перейти</a>
                    @endisset
                </div>
                <p class="text-3xl font-bold mt-4 {{ ($card['danger'] ?? false) ? 'text-red-600' : 'text-gray-900' }}">{{ $card['value'] }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Action required + status breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

        {{-- Требуют действия --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Требуют действия</h2>
                <a href="{{ route('tasks') }}" class="text-sm text-[#5B4FE8] hover:underline">Все задачи →</a>
            </div>

            @if($pendingApprovals->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-gray-500">Нет задач, требующих действий.</div>
            @else
                <div class="divide-y divide-gray-50">
                    @foreach($pendingApprovals as $item)
                        @php
                            $doc       = $item['document'];
                            $stageName = $item['stage']->workflowStage?->name ?? $doc->status_label;
                            $accent = match(true) {
                                str_contains(mb_strtolower($stageName), 'соглас')   => ['bg' => 'bg-blue-50',   'text' => 'text-blue-700',   'dot' => 'bg-blue-500'],
                                str_contains(mb_strtolower($stageName), 'утвержд')  => ['bg' => 'bg-green-50',  'text' => 'text-green-700',  'dot' => 'bg-green-500'],
                                str_contains(mb_strtolower($stageName), 'ознаком')  => ['bg' => 'bg-amber-50',  'text' => 'text-amber-700',  'dot' => 'bg-amber-500'],
                                str_contains(mb_strtolower($stageName), 'приём') ||
                                str_contains(mb_strtolower($stageName), 'прием')    => ['bg' => 'bg-violet-50', 'text' => 'text-violet-700', 'dot' => 'bg-violet-500'],
                                default                                             => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'dot' => 'bg-indigo-500'],
                            };
                        @endphp
                        <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50 transition-colors">
                            <div class="w-9 h-9 rounded-lg {{ $accent['bg'] }} flex items-center justify-center shrink-0">
                                <span class="w-2 h-2 rounded-full {{ $accent['dot'] }}"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('documents.show', $doc) }}" class="font-medium text-gray-900 hover:text-[#5B4FE8] block truncate">{{ $doc->title }}</a>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $doc->type?->name ?? 'Документ' }} · D-{{ $doc->id }}</p>
                            </div>
                            <span class="hidden sm:inline-flex px-2.5 py-1 text-xs font-medium rounded-full {{ $accent['bg'] }} {{ $accent['text'] }} whitespace-nowrap">{{ $stageName }}</span>
                            <a href="{{ route('documents.show', $doc) }}" class="shrink-0 px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">Открыть</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Документы по статусам --}}
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Документы по статусам</h2>
            </div>
            @php $maxStatus = max(1, collect($statusBreakdown)->max('count')); @endphp
            <div class="px-5 py-4 space-y-4">
                @foreach($statusBreakdown as $st)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1.5">
                            <span class="text-gray-600">{{ $st['label'] }}</span>
                            <span class="font-semibold text-gray-900">{{ $st['count'] }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ round($st['count'] / $maxStatus * 100) }}%; background: {{ $st['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Лента событий --}}
    <div class="bg-white rounded-xl border border-gray-200 mt-6">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Лента событий</h2>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($activity as $log)
                @php
                    $action = mb_strtolower($log->action);
                    $dot = match(true) {
                        str_contains($action, 'отказал') || str_contains($action, 'отклонил') => 'bg-red-500',
                        str_contains($action, 'утвердил') || str_contains($action, 'согласовал') => str_contains($action, 'согласовал') ? 'bg-blue-500' : 'bg-green-500',
                        str_contains($action, 'доработк')                                     => 'bg-violet-500',
                        str_contains($action, 'ознаком')                                      => 'bg-amber-500',
                        default                                                               => 'bg-gray-400',
                    };
                    $when = $log->created_at->isToday()
                        ? $log->created_at->format('H:i')
                        : ($log->created_at->isYesterday()
                            ? 'вчера, ' . $log->created_at->format('H:i')
                            : $log->created_at->format('d.m, H:i'));
                @endphp
                <div class="flex items-center gap-3 px-5 py-3">
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $dot }}"></span>
                    <p class="flex-1 min-w-0 text-sm text-gray-700 truncate">
                        <span class="font-medium text-gray-900">{{ $log->user?->name ?? 'Система' }}</span>
                        {{ $log->action }}
                    </p>
                    <span class="text-xs text-gray-400 shrink-0">{{ $when }}</span>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-gray-400">Активности нет</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
