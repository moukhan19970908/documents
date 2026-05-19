<x-app-layout>
    <x-slot name="title">Командировка T-{{ $trip->id }} — Vamin</x-slot>

    <div class="max-w-3xl">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('trips.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-gray-900">Командировка T-{{ $trip->id }}</h1>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $trip->status_color }}">
                        {{ $trip->status_label }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-0.5">{{ $trip->user->name }} · {{ $trip->user->department?->name }}</p>
            </div>
            @if(in_array($trip->status, ['draft', 'revision']) && $trip->user_id === auth()->id())
                <div class="flex gap-2">
                    <a href="{{ route('trips.edit', $trip) }}"
                       class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                        Редактировать
                    </a>
                    @if($trip->status === 'draft')
                        <form action="{{ route('trips.update', $trip) }}" method="POST">
                            @csrf @method('PUT')
                            <input type="hidden" name="city" value="{{ $trip->city }}">
                            <input type="hidden" name="purpose" value="{{ $trip->purpose }}">
                            <input type="hidden" name="date_start" value="{{ $trip->date_start->format('Y-m-d') }}">
                            <input type="hidden" name="date_end" value="{{ $trip->date_end->format('Y-m-d') }}">
                            <input type="hidden" name="daily_rate" value="{{ $trip->daily_rate }}">
                            <input type="hidden" name="accommodation_total" value="{{ $trip->accommodation_total }}">
                            <input type="hidden" name="transport_total" value="{{ $trip->transport_total }}">
                            <input type="hidden" name="comment" value="{{ $trip->comment }}">
                            <input type="hidden" name="submit" value="1">
                            <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                                Отправить
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-5">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-3 gap-5">
            {{-- Main info --}}
            <div class="col-span-2 space-y-5">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">Детали командировки</h2>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-400 text-xs">Город</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ $trip->city }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-400 text-xs">Даты</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">
                                {{ $trip->date_start->format('d.m.Y') }} — {{ $trip->date_end->format('d.m.Y') }}
                                <span class="text-gray-400 text-xs">({{ $trip->days_count }} дн.)</span>
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-gray-400 text-xs">Цель поездки</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ $trip->purpose }}</dd>
                        </div>
                        @if($trip->comment)
                            <div class="col-span-2">
                                <dt class="text-gray-400 text-xs">Комментарий</dt>
                                <dd class="text-gray-700 mt-0.5">{{ $trip->comment }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">Расходы</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Суточные ({{ number_format($trip->daily_rate, 0, '.', ' ') }} ₽ × {{ $trip->days_count }} дн.)</span>
                            <span>{{ number_format($trip->daily_rate * $trip->days_count, 0, '.', ' ') }} ₽</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Проживание</span>
                            <span>{{ number_format($trip->accommodation_total, 0, '.', ' ') }} ₽</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Переезд</span>
                            <span>{{ number_format($trip->transport_total, 0, '.', ' ') }} ₽</span>
                        </div>
                        <div class="border-t border-gray-100 pt-2 flex justify-between font-semibold text-gray-900">
                            <span>Итого</span>
                            <span class="text-[#5B4FE8]">{{ number_format($trip->total_amount, 0, '.', ' ') }} ₽</span>
                        </div>
                    </div>
                </div>

                {{-- Approval history --}}
                @if($trip->approvalLogs->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">История согласования</h2>
                        <div class="space-y-3">
                            @foreach($trip->approvalLogs as $log)
                                <div class="flex items-start gap-3 text-sm">
                                    <div class="w-7 h-7 rounded-full bg-[#5B4FE8]/15 flex items-center justify-center text-[#5B4FE8] text-xs font-semibold shrink-0 mt-0.5">
                                        {{ mb_substr($log->approver->name, 0, 1) }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-medium text-gray-900">{{ $log->approver->name }}</span>
                                            <span @class(['text-xs px-2 py-0.5 rounded-full font-medium',
                                                'bg-green-100 text-green-700' => $log->action === 'approved',
                                                'bg-red-100 text-red-700' => $log->action === 'rejected',
                                                'bg-orange-100 text-orange-700' => $log->action === 'sent_revision',
                                                'bg-gray-100 text-gray-600' => !in_array($log->action, ['approved','rejected','sent_revision']),
                                            ])>{{ $log->action_label }}</span>
                                            <span class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</span>
                                        </div>
                                        @if($log->comment)
                                            <p class="text-gray-500 mt-0.5">{{ $log->comment }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-4">
                {{-- Route progress --}}
                @if($trip->route)
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Маршрут согласования</h3>
                        <p class="text-sm font-medium text-gray-800 mb-3">{{ $trip->route->name }}</p>
                        <div class="space-y-2">
                            @foreach($trip->route->steps as $step)
                                @php
                                    $isDone = $step->step_order < $trip->current_step;
                                    $isCurrent = $step->step_order === $trip->current_step && $trip->status === 'pending';
                                @endphp
                                <div class="flex items-center gap-2.5 text-xs">
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
                                    <span @class(['font-medium' => $isCurrent, 'text-gray-400' => !$isDone && !$isCurrent, 'text-gray-700' => $isDone || $isCurrent])>
                                        @if($step->approverUser)
                                            {{ $step->approverUser->name }}
                                        @else
                                            Уровень {{ $step->approver_role_level }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Создано</span>
                        <span class="text-gray-700">{{ $trip->created_at->format('d.m.Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Обновлено</span>
                        <span class="text-gray-700">{{ $trip->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
