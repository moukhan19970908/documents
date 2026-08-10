<x-app-layout>
    <x-slot name="title">Отпуск V-{{ $vacation->id }} — Vamin</x-slot>

    <div class="max-w-2xl">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('requests.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="flex-1 flex items-center gap-3">
                <h1 class="text-xl font-bold text-gray-900">Отпуск V-{{ $vacation->id }}</h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $vacation->status_color }}">
                    {{ $vacation->status_label }}
                </span>
            </div>
            @if(in_array($vacation->status, ['draft', 'revision']) && $vacation->user_id === auth()->id())
                <div class="flex gap-2">
                    @if($vacation->status === 'draft')
                        <form action="{{ route('vacations.update', $vacation) }}" method="POST">
                            @csrf @method('PUT')
                            <input type="hidden" name="vacation_type" value="{{ $vacation->vacation_type }}">
                            <input type="hidden" name="date_start" value="{{ $vacation->date_start->format('Y-m-d') }}">
                            <input type="hidden" name="date_end" value="{{ $vacation->date_end->format('Y-m-d') }}">
                            <input type="hidden" name="comment" value="{{ $vacation->comment }}">
                            <input type="hidden" name="submit" value="1">
                            <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                                Отправить
                            </button>
                        </form>
                    @endif
                </div>
            @endif
            @can('delete', $vacation)
                <form action="{{ route('vacations.destroy', $vacation) }}" method="POST"
                      onsubmit="return confirm('Удалить заявку? Это действие необратимо.')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-colors">
                        Удалить
                    </button>
                </form>
            @endcan
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-5">{{ session('success') }}</div>
        @endif

        <div class="space-y-5">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">Детали заявки</h2>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-gray-400 text-xs">Заявитель</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $vacation->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-xs">Отдел</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $vacation->user->department?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-xs">Вид отпуска</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $vacation->vacation_type_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-xs">Количество дней</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $vacation->days_count }} дн.</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-gray-400 text-xs">Период</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">
                            {{ $vacation->date_start->format('d.m.Y') }} — {{ $vacation->date_end->format('d.m.Y') }}
                        </dd>
                    </div>
                    @if($vacation->comment)
                        <div class="col-span-2">
                            <dt class="text-gray-400 text-xs">Комментарий</dt>
                            <dd class="text-gray-700 mt-0.5">{{ $vacation->comment }}</dd>
                        </div>
                    @endif
                    <div class="col-span-2">
                        <dt class="text-gray-400 text-xs">Подписант</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $vacation->signatory?->name ?? '—' }}</dd>
                    </div>
                    @if($vacation->substitution)
                        <div class="col-span-2">
                            <dt class="text-gray-400 text-xs">Замещение</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">
                                {{ $vacation->substitution->deputy?->name ?? '—' }}
                                <span class="text-gray-400 font-normal">· {{ $vacation->substitution->date_from->format('d.m') }}–{{ $vacation->substitution->date_to->format('d.m') }}</span>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if($vacation->route)
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Маршрут согласования</h2>
                    <p class="text-xs text-gray-500 mb-4">{{ $vacation->route->name }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($vacation->route->steps as $step)
                            @php
                                $isDone    = $step->step_order < $vacation->current_step || $vacation->status === 'approved';
                                $isCurrent = $step->step_order === $vacation->current_step && $vacation->status === 'pending';
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

            @if($vacation->status === 'approved')
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Дальнейшее движение</h2>
                    @php $regItem = $vacation->registryItem; @endphp
                    @if($regItem && $regItem->registry)
                        @php $reg = $regItem->registry; @endphp
                        <div class="flex items-center gap-2 text-sm flex-wrap">
                            <span class="text-gray-600">В реестре</span>
                            <a href="{{ route('vacations.registries.show', $reg) }}" class="font-medium text-[#5B4FE8] hover:underline">«{{ $reg->title }}»</a>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $reg->status_color }}">{{ $reg->status_label }}</span>
                            @if($regItem->status === 'dropped')
                                <span class="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">выведена на доработку</span>
                            @endif
                        </div>
                    @else
                        @php $goesToRegistry = ($vacation->user->role_level ?? 1) < 2 && !in_array($vacation->user->role, ['admin', 'director', 'ceo', 'chief_of_staff']); @endphp
                        <p class="text-sm text-gray-500">
                            @if($goesToRegistry)
                                Согласована. Ожидает включения в реестр отпусков для передачи в бухгалтерию.
                            @else
                                Согласована по индивидуальному маршруту.
                            @endif
                        </p>
                    @endif
                </div>
            @endif

            @if($vacation->approvalLogs->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">История согласования</h2>
                    <div class="space-y-3">
                        @foreach($vacation->approvalLogs as $log)
                            <div class="flex items-start gap-3 text-sm">
                                <div class="w-7 h-7 rounded-full bg-[#5B4FE8]/15 flex items-center justify-center text-[#5B4FE8] text-xs font-bold shrink-0 mt-0.5">
                                    {{ mb_substr($log->approver->name, 0, 1) }}
                                </div>
                                <div>
                                    <span class="font-medium text-gray-900">{{ $log->approver->name }}</span>
                                    <span class="ml-2 text-xs text-gray-400">{{ $log->action_label }}</span>
                                    <span class="ml-2 text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</span>
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
    </div>
</x-app-layout>
