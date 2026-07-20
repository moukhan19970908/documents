@php
    $statusColors = [
        'assigned' => 'bg-gray-100 text-gray-600', 'in_progress' => 'bg-blue-50 text-blue-600',
        'submitted' => 'bg-amber-50 text-amber-600', 'done' => 'bg-emerald-50 text-emerald-600',
        'returned' => 'bg-red-50 text-red-600',
    ];
    $openMandatory = $assignment->children->where('is_mandatory', true)->where('status', '!=', 'done')->count();
    $canReport     = $isExecutor && in_array($assignment->status, ['in_progress', 'returned']) && $openMandatory === 0;
    $ownFiles      = $assignment->files->whereNull('source_assignment_id');
    $aggFiles      = $assignment->files->whereNotNull('source_assignment_id');
@endphp

<x-app-layout>
    <x-slot name="title">{{ $assignment->number }} — Поручение</x-slot>

    <div class="max-w-4xl" x-data="{ panel: '' }">
        <div class="mb-4">
            <a href="{{ route('assignments.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← Поручения</a>
            @if($parentVisible)
                <span class="text-xs text-gray-300 mx-1">/</span>
                <a href="{{ route('assignments.show', $assignment->parent) }}" class="text-xs text-gray-400 hover:text-gray-600">{{ $assignment->parent->number }}</a>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">{{ session('error') }}</div>
        @endif

        {{-- Шапка --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-4">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="font-mono text-xs text-gray-400">{{ $assignment->number }}</span>
                        @if($assignment->parent_id)<span class="text-[10px] uppercase tracking-wide text-gray-400">подпоручение · ур. {{ $assignment->depth }}</span>@endif
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">{{ $assignment->title }}</h1>
                </div>
                <div class="flex flex-col items-end gap-1 shrink-0">
                    <span class="text-xs px-2.5 py-1 rounded-full {{ $statusColors[$assignment->status] ?? '' }}">{{ $assignment->statusLabel() }}</span>
                    @if($assignment->isOverdue())<span class="text-xs text-red-500 font-semibold">Просрочено</span>@endif
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4 text-sm">
                <div><span class="text-xs text-gray-400 block">Постановщик</span>{{ $assignment->initiator->name }}</div>
                <div><span class="text-xs text-gray-400 block">Исполнитель</span>{{ $assignment->executor->name }}</div>
                <div><span class="text-xs text-gray-400 block">Крайний срок</span>{{ $assignment->due_at?->format('d.m.Y') ?? '—' }}</div>
                @if($assignment->coExecutors->isNotEmpty())
                    <div class="col-span-2 md:col-span-3"><span class="text-xs text-gray-400 block">Соисполнители</span>{{ $assignment->coExecutors->pluck('name')->join(', ') }}</div>
                @endif
                @if($assignment->controller)
                    <div><span class="text-xs text-gray-400 block">Контролёр</span>{{ $assignment->controller->name }}</div>
                @endif
            </div>

            @if($assignment->body_html)
                <div class="prose prose-sm max-w-none mt-4 pt-4 border-t border-gray-100">{!! $assignment->body_html !!}</div>
            @endif
        </div>

        {{-- Действия --}}
        @php $showActions = $isParticipant || ($isInitiator && $assignment->status === 'submitted') || $canSubAssign; @endphp
        @if($showActions)
            <div class="flex flex-wrap gap-2 mb-4">
                @if($isParticipant && in_array($assignment->status, ['assigned']))
                    <form method="POST" action="{{ route('assignments.start', $assignment) }}">@csrf
                        <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Взять в работу</button>
                    </form>
                @endif

                @if($isExecutor && in_array($assignment->status, ['in_progress', 'returned']))
                    <button type="button" @click="panel = panel === 'report' ? '' : 'report'"
                            @disabled(! $canReport)
                            class="px-4 py-2 rounded-lg text-sm font-medium {{ $canReport ? 'bg-[#5B4FE8] text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">
                        Отчитаться (Исполнено)
                    </button>
                @endif

                @if($isParticipant && in_array($assignment->status, ['in_progress', 'returned']))
                    <button type="button" @click="panel = panel === 'contribute' ? '' : 'contribute'"
                            class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Приложить материалы</button>
                @endif

                @if($isExecutor && $assignment->status !== 'done' && $settings->deadline_extension !== 'disabled' && ! $assignment->pending_due_at)
                    <button type="button" @click="panel = panel === 'extend' ? '' : 'extend'"
                            class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Перенести срок</button>
                @endif

                @if($isInitiator && $assignment->status === 'submitted')
                    <form method="POST" action="{{ route('assignments.accept', $assignment) }}">@csrf
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Принять</button>
                    </form>
                    <button type="button" @click="panel = panel === 'return' ? '' : 'return'"
                            class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50">Вернуть на доработку</button>
                @endif

                @if($canSubAssign)
                    <button type="button" @click="panel = panel === 'sub' ? '' : 'sub'"
                            class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">+ Подпоручение</button>
                @endif
            </div>

            @if($isExecutor && ! $canReport && in_array($assignment->status, ['in_progress', 'returned']) && $openMandatory > 0)
                <p class="text-xs text-amber-600 mb-4">Сначала примите обязательные подпоручения ({{ $openMandatory }}) — только потом можно отчитаться.</p>
            @endif

            {{-- Панель: отчёт --}}
            <div x-show="panel === 'report'" x-cloak class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-3">Отчёт об исполнении</h3>
                <form method="POST" action="{{ route('assignments.submit', $assignment) }}" enctype="multipart/form-data">@csrf
                    <textarea name="result_comment" rows="3" placeholder="Что сделано…"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('result_comment') }}</textarea>
                    <input type="file" name="files[]" multiple class="mt-3 block text-sm text-gray-600">
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Отправить на приёмку</button>
                    </div>
                </form>
            </div>

            {{-- Панель: приложить материалы (соисполнитель/исполнитель) --}}
            <div x-show="panel === 'contribute'" x-cloak class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-3">Приложить материалы</h3>
                <form method="POST" action="{{ route('assignments.contribute', $assignment) }}" enctype="multipart/form-data">@csrf
                    <input type="file" name="files[]" multiple required class="block text-sm text-gray-600">
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Приложить</button>
                    </div>
                </form>
            </div>

            {{-- Панель: перенос срока --}}
            <div x-show="panel === 'extend'" x-cloak class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-3">Перенос срока</h3>
                <form method="POST" action="{{ route('assignments.extend', $assignment) }}">@csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <input type="date" name="due_at" required value="{{ $assignment->due_at?->format('Y-m-d') }}"
                               class="text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <textarea name="comment" rows="2" required placeholder="Причина переноса (обязательно)"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 mt-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Перенести срок</button>
                    </div>
                </form>
            </div>

            {{-- Панель: возврат --}}
            <div x-show="panel === 'return'" x-cloak class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-3">Возврат на доработку</h3>
                <form method="POST" action="{{ route('assignments.return', $assignment) }}">@csrf
                    <textarea name="return_comment" rows="3" required placeholder="Что доработать (обязательно)"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Вернуть</button>
                    </div>
                </form>
            </div>

            {{-- Панель: подпоручение --}}
            @if($canSubAssign)
                <div x-show="panel === 'sub'" x-cloak class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                    <h3 class="font-semibold text-gray-900 mb-3">Новое подпоручение</h3>
                    <form method="POST" action="{{ route('assignments.sub', $assignment) }}" class="space-y-3">@csrf
                        <input type="text" name="title" required placeholder="Тема подпоручения"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <select name="executor_id" required
                                    class="text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— исполнитель —</option>
                                @foreach($executors as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="due_at"
                                   class="text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>
                        <textarea name="body_html" rows="2" placeholder="Текст (необязательно)"
                                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>

                        @include('assignments._participants', ['settings' => $settings, 'executors' => $executors, 'people' => $people])

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_mandatory" value="1" checked class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            Обязательное (корень нельзя закрыть, пока не принято)
                        </label>
                        <div class="flex justify-end">
                            <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Поставить подпоручение</button>
                        </div>
                    </form>
                </div>
            @endif
        @endif

        {{-- Заявка на перенос срока (режим «с одобрением») --}}
        @if($assignment->pending_due_at)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                <p class="text-sm text-amber-800">
                    <span class="font-semibold">Запрос переноса срока</span> на {{ $assignment->pending_due_at->format('d.m.Y') }}.
                    @if($assignment->pending_due_comment)<span class="block text-amber-700 mt-0.5">{{ $assignment->pending_due_comment }}</span>@endif
                </p>
                @if($isInitiator)
                    <div class="flex items-center gap-2 mt-3">
                        <form method="POST" action="{{ route('assignments.extend.approve', $assignment) }}">@csrf
                            <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Одобрить</button>
                        </form>
                        <form method="POST" action="{{ route('assignments.extend.reject', $assignment) }}">@csrf
                            <button class="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50">Отклонить</button>
                        </form>
                    </div>
                @else
                    <p class="text-xs text-amber-600 mt-1">Ожидает решения постановщика.</p>
                @endif
            </div>
        @endif

        {{-- Отчёт исполнителя / возврат --}}
        @if($assignment->status === 'returned' && $assignment->return_comment)
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 text-sm">
                <span class="font-semibold text-red-700">Возвращено на доработку:</span> <span class="text-red-600">{{ $assignment->return_comment }}</span>
            </div>
        @endif

        @if($assignment->result_comment || $ownFiles->isNotEmpty())
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-2">Результат</h3>
                @if($assignment->result_comment)<p class="text-sm text-gray-700 whitespace-pre-line">{{ $assignment->result_comment }}</p>@endif
                @if($ownFiles->isNotEmpty())
                    <div class="mt-3 space-y-1">
                        @foreach($ownFiles as $f)
                            <a href="{{ route('assignments.file', $f) }}" class="flex items-center gap-2 text-sm text-[#5B4FE8] hover:underline">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                {{ $f->original_name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Подтянутые снизу файлы (агрегация) --}}
        @if($aggFiles->isNotEmpty())
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-2">Результаты подпоручений</h3>
                <div class="space-y-1">
                    @foreach($aggFiles as $f)
                        <a href="{{ route('assignments.file', $f) }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-[#5B4FE8]">
                            <span class="font-mono text-xs text-gray-400">{{ $f->source?->number }}</span>
                            {{ $f->original_name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Дочерние узлы (послойная видимость) --}}
        @if($children->isNotEmpty())
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-3">Подпоручения</h3>
                <div class="space-y-2">
                    @foreach($children as $c)
                        <div class="border border-gray-100 rounded-lg px-4 py-3" x-data="{ ret: false }">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('assignments.show', $c) }}" class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs text-gray-400">{{ $c->number }}</span>
                                        @unless($c->is_mandatory)<span class="text-[10px] text-gray-400">необязательное</span>@endunless
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $c->title }}</p>
                                    <p class="text-xs text-gray-500">Исполнитель: {{ $c->executor->name }}@if($c->due_at) · до {{ $c->due_at->format('d.m.Y') }}@endif</p>
                                </a>
                                <span class="text-xs px-2.5 py-1 rounded-full shrink-0 {{ $statusColors[$c->status] ?? '' }}">{{ $c->statusLabel() }}</span>
                            </div>

                            {{-- Приёмка дочернего узла его постановщиком (= исполнитель текущего) --}}
                            @if($c->status === 'submitted' && $c->initiator_id === auth()->id())
                                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100">
                                    <form method="POST" action="{{ route('assignments.accept', $c) }}">@csrf
                                        <button class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-medium hover:bg-emerald-700">Принять</button>
                                    </form>
                                    <button type="button" @click="ret = !ret" class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs hover:bg-red-50">Вернуть</button>
                                </div>
                                <form x-show="ret" x-cloak method="POST" action="{{ route('assignments.return', $c) }}" class="mt-2">@csrf
                                    <textarea name="return_comment" rows="2" required placeholder="Что доработать"
                                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                                    <button class="mt-2 px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-medium hover:bg-red-700">Вернуть на доработку</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Таймлайн --}}
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h3 class="font-semibold text-gray-900 mb-3">История</h3>
            <div class="space-y-3">
                @forelse($assignment->events as $e)
                    <div class="flex gap-3 text-sm">
                        <div class="w-1.5 h-1.5 rounded-full bg-[#5B4FE8] mt-1.5 shrink-0"></div>
                        <div class="min-w-0">
                            <span class="text-gray-800">{{ $e->label() }}</span>
                            <span class="text-xs text-gray-400">· {{ $e->user?->name }} · {{ $e->created_at->format('d.m.Y H:i') }}</span>
                            @if($e->comment)<p class="text-xs text-gray-500 mt-0.5">{{ $e->comment }}</p>@endif
                            @if($e->type === 'deadline_changed' && $e->meta)<p class="text-xs text-gray-400">{{ $e->meta['from'] ?? '' }} → {{ $e->meta['to'] ?? '' }}</p>@endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Событий пока нет.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
