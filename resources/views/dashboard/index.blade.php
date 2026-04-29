<x-app-layout>
    <x-slot name="title">Дашборд — Vamin.ru</x-slot>

    {{-- Greeting --}}
    <div class="flex flex-col md:flex-row md:items-start gap-6">
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900">
                @php
                    $hour = now()->hour;
                    $greeting = $hour < 12 ? 'Доброе утро' : ($hour < 18 ? 'Добрый день' : 'Добрый вечер');
                @endphp
                {{ $greeting }}, {{ auth()->user()->name }}.
            </h1>
            <p class="text-sm text-gray-500 mt-1">Статус ваших рабочих процессов.</p>

            {{-- Stats row --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                {{-- Pending --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Ожидают согласования</p>
                    <div class="flex items-end gap-2 mt-3">
                        <span class="text-4xl font-bold text-gray-900">{{ $stats['pending_count'] }}</span>
                    </div>
                </div>

                {{-- Processed --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Обработано за неделю</p>
                    <div class="flex items-end gap-2 mt-3">
                        <span class="text-4xl font-bold text-gray-900">{{ $stats['processed_week'] }}</span>
                    </div>
                </div>

                {{-- Overdue --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Просроченных задач</p>
                    <div class="flex items-end gap-2 mt-3">
                        <span class="text-4xl font-bold {{ $stats['overdue_count'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $stats['overdue_count'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Action required table --}}
            <div class="bg-white rounded-xl border border-gray-200 mt-6">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Требуют действия</h2>
                    <a href="{{ route('tasks') }}" class="text-sm text-[#5B4FE8] hover:underline">Смотреть все задачи</a>
                </div>

                @if($pendingApprovals->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-gray-500">Нет задач, требующих действий.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                                    <th class="text-left px-5 py-3 font-semibold">Документ</th>
                                    <th class="text-left px-5 py-3 font-semibold">Тип</th>
                                    <th class="text-left px-5 py-3 font-semibold">Инициатор</th>
                                    <th class="text-left px-5 py-3 font-semibold">Ответственный</th>
                                    <th class="text-left px-5 py-3 font-semibold">Статус</th>
                                    <th class="text-left px-5 py-3 font-semibold">Обновлён</th>
                                    <th class="text-left px-5 py-3 font-semibold">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($pendingApprovals as $item)
                                    @php
                                        $doc = $item['document'];
                                        $statusBadge = [
                                            'draft'            => 'bg-gray-100 text-gray-600',
                                            'in_review'        => 'bg-blue-100 text-blue-700',
                                            'requires_changes' => 'bg-orange-100 text-orange-700',
                                            'approved'         => 'bg-green-100 text-green-700',
                                            'signed'           => 'bg-green-100 text-green-700',
                                            'rejected'         => 'bg-red-100 text-red-700',
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
                                            @php
                                                $approval    = $doc->activeApproval ?? $doc->latestApproval;
                                                $activeStage = $approval?->stages->first();
                                                $approvers   = $activeStage?->workflowStage?->approvers ?? collect();
                                            @endphp
                                            @if($approvers->isNotEmpty())
                                                <div class="flex items-center gap-1.5">
                                                    @foreach($approvers->take(2) as $ap)
                                                        @if($ap->user)
                                                            <div class="flex items-center gap-1.5">
                                                                <img src="{{ $ap->user->avatar_url }}" class="w-6 h-6 rounded-full flex-shrink-0" alt="">
                                                                <span class="text-gray-700 text-sm">{{ Str::limit($ap->user->name, 20) }}</span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                    @if($approvers->count() > 2)
                                                        <span class="text-xs text-gray-400">+{{ $approvers->count() - 2 }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded {{ $statusBadge }}">
                                                {{ $doc->status_label }}
                                            </span>
                                            @php
                                                $stages = $doc->activeApproval?->stages ?? collect();
                                                if ($doc->status === 'draft') {
                                                    $segments = collect([['color' => '#D1D5DB', 'label' => 'Черновик']]);
                                                } elseif ($doc->status === 'rejected') {
                                                    $segments = collect([['color' => '#EF4444', 'label' => 'Отклонено']]);
                                                } else {
                                                    $segments = collect([['color' => '#22C55E', 'label' => 'Инициатор: ' . $doc->initiator->name]]);
                                                    foreach ($stages as $stage) {
                                                        $stageApprovers = $stage->workflowStage?->approvers ?? collect();
                                                        $decidedUserIds = $stage->decisions->pluck('user_id')->toArray();
                                                        if ($stageApprovers->isEmpty()) {
                                                            $color = match($stage->status) {
                                                                'approved'    => '#22C55E',
                                                                'rejected'    => '#EF4444',
                                                                'in_progress' => '#D1D5DB',
                                                                default       => '#D1D5DB',
                                                            };
                                                            $segments->push(['color' => $color, 'label' => $stage->workflowStage?->name ?? 'Стадия']);
                                                        } else {
                                                            foreach ($stageApprovers as $ap) {
                                                                if ($stage->status === 'approved') {
                                                                    $color = '#22C55E';
                                                                    $label = 'Подписал: ' . ($ap->user?->name ?? '—');
                                                                } elseif ($stage->status === 'rejected') {
                                                                    $color = '#EF4444';
                                                                    $label = 'Отклонил: ' . ($ap->user?->name ?? '—');
                                                                } elseif ($stage->status === 'in_progress') {
                                                                    $signed = in_array($ap->approver_id, $decidedUserIds);
                                                                    $color  = $signed ? '#3B82F6' : '#D1D5DB';
                                                                    $label  = ($signed ? 'Подписал: ' : 'На подписании у: ') . ($ap->user?->name ?? '—');
                                                                } else {
                                                                    $color = '#D1D5DB';
                                                                    $label = 'Ожидает: ' . ($ap->user?->name ?? '—');
                                                                }
                                                                $segments->push(['color' => $color, 'label' => $label]);
                                                            }
                                                        }
                                                    }
                                                }
                                            @endphp
                                            @if($segments->count() > 0)
                                                <div class="flex gap-0.5 mt-1.5" style="width:120px">
                                                    @foreach($segments as $seg)
                                                        <div title="{{ $seg['label'] }}" style="flex:1; height:4px; background:{{ $seg['color'] }}; border-radius:2px; cursor:default"></div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $doc->updated_at->format('d.m.Y') }}</td>
                                        <td class="px-5 py-3.5">
                                            <a href="{{ route('documents.show', $doc) }}" class="text-[#5B4FE8] text-xs font-medium hover:underline">Открыть</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Activity sidebar --}}
        <div class="w-full md:w-72 shrink-0">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Последняя активность</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($activity as $log)
                        <div class="px-5 py-3.5 flex gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden shrink-0 mt-0.5">
                                @if($log->user)
                                    <img src="{{ $log->user->avatar_url }}" alt="{{ $log->user->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full bg-gray-200 flex items-center justify-center text-xs text-gray-500">S</div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-700 leading-snug">
                                    <span class="font-medium">{{ $log->user?->name ?? 'Система' }}</span>
                                    — {{ $log->action }}
                                </p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ $log->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center text-xs text-gray-400">Активности нет</div>
                    @endforelse
                </div>
                @if($activity->count() >= 10)
                    <div class="px-5 py-3 border-t border-gray-100">
                        <button class="text-xs text-[#5B4FE8] font-medium hover:underline w-full text-center uppercase tracking-wide">
                            Загрузить больше активности
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>