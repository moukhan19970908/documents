<x-app-layout>
    <x-slot name="title">Командировка T-{{ $trip->id }} — Vamin</x-slot>

    <div class="max-w-2xl">

        {{-- Header --}}
        <div class="mb-5 flex items-center gap-3">
            <a href="{{ route('trips.index') }}" class="text-gray-400 hover:text-gray-600 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg font-bold text-gray-900">Командировка T-{{ $trip->id }}</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $trip->status_color }}">
                        {{ $trip->status_label }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">{{ $trip->user->name }} · {{ $trip->user->department?->name }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if(in_array($trip->status, ['draft', 'revision']) && $trip->user_id === auth()->id())
                    <a href="{{ route('trips.edit', $trip) }}"
                       class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                        Редактировать
                    </a>
                    @if($trip->status === 'draft')
                        <form action="{{ route('trips.update', $trip) }}" method="POST">
                            @csrf @method('PUT')
                            <input type="hidden" name="city_type" value="{{ $trip->location_type }}">
                            <input type="hidden" name="city_name" value="{{ $trip->city }}">
                            <input type="hidden" name="purpose" value="{{ $trip->purpose }}">
                            <input type="hidden" name="date_start" value="{{ $trip->date_start->format('Y-m-d') }}">
                            <input type="hidden" name="date_end" value="{{ $trip->date_end->format('Y-m-d') }}">
                            <input type="hidden" name="daily_rate" value="{{ $trip->daily_rate }}">
                            <input type="hidden" name="accommodation_total" value="{{ $trip->accommodation_total }}">
                            <input type="hidden" name="comment" value="{{ $trip->comment }}">
                            <input type="hidden" name="submit" value="1">
                            <button type="submit" class="px-3 py-1.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                                Отправить
                            </button>
                        </form>
                    @endif
                @endif
                @can('delete', $trip)
                    <form action="{{ route('trips.destroy', $trip) }}" method="POST"
                          onsubmit="return confirm('Удалить заявку? Это действие необратимо.')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-colors">
                            Удалить
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">{{ session('success') }}</div>
        @endif

        {{-- Single card --}}
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">

            {{-- Details --}}
            <div class="px-5 py-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-3">Детали</p>
                <dl class="grid grid-cols-[auto_1fr] gap-x-8 gap-y-2 text-sm">
                    <dt class="text-gray-400">Город</dt>
                    <dd class="font-medium text-gray-900">{{ $trip->city }}</dd>

                    <dt class="text-gray-400">Даты</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $trip->date_start->format('d.m.Y') }} — {{ $trip->date_end->format('d.m.Y') }}
                        <span class="text-gray-400 font-normal">({{ $trip->days_count }} дн.)</span>
                    </dd>

                    <dt class="text-gray-400">Цель</dt>
                    <dd class="text-gray-900">{{ $trip->purpose }}</dd>

                    @if($trip->comment)
                        <dt class="text-gray-400">Комментарий</dt>
                        <dd class="text-gray-700">{{ $trip->comment }}</dd>
                    @endif

                    <dt class="text-gray-400">Подписант</dt>
                    <dd class="font-medium text-gray-900">{{ $trip->signatory?->name ?? '—' }}</dd>

                    <dt class="text-gray-400">Создано</dt>
                    <dd class="text-gray-500">{{ $trip->created_at->format('d.m.Y') }} · обновлено {{ $trip->updated_at->diffForHumans() }}</dd>
                </dl>
            </div>

            {{-- Expenses --}}
            <div class="px-5 py-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-3">Расходы</p>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Суточные ({{ number_format($trip->daily_rate, 0, '.', ' ') }} ₽ × {{ $trip->days_count }} дн.)</span>
                        <span>{{ number_format($trip->daily_rate * $trip->days_count, 0, '.', ' ') }} ₽</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Проживание</span>
                        <span>{{ number_format($trip->accommodation_total, 0, '.', ' ') }} ₽</span>
                    </div>
                    @if($trip->transport_total > 0)
                        <div class="flex justify-between text-gray-600">
                            <span>Переезд</span>
                            <span>{{ number_format($trip->transport_total, 0, '.', ' ') }} ₽</span>
                        </div>
                    @endif
                    <div class="border-t border-gray-100 pt-2 flex justify-between font-semibold">
                        <span class="text-gray-900">Итого</span>
                        <span class="text-[#5B4FE8]">{{ number_format($trip->total_amount, 0, '.', ' ') }} ₽</span>
                    </div>
                </div>
            </div>

            {{-- Approval route --}}
            @if($trip->route)
                <div class="px-5 py-4">
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Маршрут согласования</p>
                    <p class="text-xs text-gray-500 mb-3">{{ $trip->route->name }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($trip->route->steps as $step)
                            @php
                                $isDone    = $step->step_order < $trip->current_step;
                                $isCurrent = $step->step_order === $trip->current_step && $trip->status === 'pending';
                                $name      = $step->approverUser?->name ?? 'Уровень ' . $step->approver_role_level;
                            @endphp
                            <div class="flex items-center gap-1.5 text-xs">
                                <div @class(['w-5 h-5 rounded-full flex items-center justify-center font-bold shrink-0',
                                    'bg-green-500 text-white' => $isDone,
                                    'bg-[#5B4FE8] text-white ring-2 ring-[#5B4FE8]/30' => $isCurrent,
                                    'bg-gray-100 text-gray-400' => !$isDone && !$isCurrent,
                                ])>
                                    @if($isDone)
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        {{ $step->step_order }}
                                    @endif
                                </div>
                                <span @class(['font-medium text-gray-800' => $isCurrent, 'text-gray-400' => !$isDone && !$isCurrent, 'text-gray-600' => $isDone])>
                                    {{ $name }}
                                </span>
                                @if(!$loop->last)
                                    <svg class="w-3 h-3 text-gray-300 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Approval actions --}}
            @if($trip->status === 'pending')
                @php
                    $canApproveTrip = (!auth()->user()->hasRole('linear') || auth()->user()->isManager())
                        && $trip->route
                        && $trip->route->steps->where('step_order', $trip->current_step)->first()?->approverUser?->id === auth()->id();
                @endphp
                @if($canApproveTrip)
                    <div class="px-5 py-4" x-data="{ comment: '' }">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-3">Ваше решение</p>
                        <textarea x-model="comment" rows="2"
                            placeholder="Комментарий (необязательно)..."
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 mb-3 resize-none focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]/30 focus:border-[#5B4FE8]"></textarea>
                        <div class="flex gap-2">
                            <form action="{{ route('trips.approve', $trip) }}" method="POST" class="flex-1">
                                @csrf
                                <input type="hidden" name="comment" x-bind:value="comment">
                                <button type="submit" class="w-full py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                                    Согласовать
                                </button>
                            </form>
                            <form action="{{ route('trips.revision', $trip) }}" method="POST" class="flex-1">
                                @csrf
                                <input type="hidden" name="comment" x-bind:value="comment">
                                <button type="submit" class="w-full py-2 border border-orange-200 text-orange-600 rounded-lg text-sm font-medium hover:bg-orange-50 transition-colors">
                                    На доработку
                                </button>
                            </form>
                            <form action="{{ route('trips.reject', $trip) }}" method="POST" class="flex-1">
                                @csrf
                                <input type="hidden" name="comment" x-bind:value="comment">
                                <button type="submit" class="w-full py-2 border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                                    Отклонить
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Approval history --}}
            @if($trip->approvalLogs->isNotEmpty())
                <div class="px-5 py-4">
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-3">История</p>
                    <div class="space-y-2.5">
                        @foreach($trip->approvalLogs as $log)
                            <div class="flex items-start gap-2.5 text-sm">
                                <div class="w-6 h-6 rounded-full bg-[#5B4FE8]/10 flex items-center justify-center text-[#5B4FE8] text-[11px] font-bold shrink-0 mt-0.5">
                                    {{ mb_substr($log->approver->name, 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-medium text-gray-900">{{ $log->approver->name }}</span>
                                        <span @class(['text-xs px-1.5 py-0.5 rounded font-medium',
                                            'bg-green-100 text-green-700' => $log->action === 'approved',
                                            'bg-red-100 text-red-700'    => $log->action === 'rejected',
                                            'bg-orange-100 text-orange-700' => $log->action === 'sent_revision',
                                            'bg-gray-100 text-gray-500'  => !in_array($log->action, ['approved','rejected','sent_revision']),
                                        ])>{{ $log->action_label }}</span>
                                        <span class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</span>
                                    </div>
                                    @if($log->comment)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $log->comment }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        {{-- Задания по командировке (ТЗ 18.3): результаты возвращаются инициатору --}}
        @if($trip->tripTasks->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 mt-4">
                <div class="px-5 py-4 border-b border-gray-100">
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest">Задания по командировке</p>
                    <p class="text-xs text-gray-400 mt-0.5">Порождаются после согласования, результаты возвращаются инициатору</p>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($trip->tripTasks as $task)
                        <div class="px-5 py-3.5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $task->title }}</p>
                                    <p class="text-xs text-gray-400">{{ $task->whoLabel() }} · {{ $task->assignee?->name ?? 'исполнитель не назначен' }}</p>
                                </div>
                                <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                            </div>
                            @if($task->result_comment)<p class="text-sm text-gray-600 mt-1.5">{{ $task->result_comment }}</p>@endif
                            @if($task->files->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($task->files as $f)
                                        <a href="{{ route('trips.tasks.file', $f) }}" class="inline-flex items-center gap-1.5 text-xs text-[#5B4FE8] hover:underline">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            {{ $f->original_name }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
