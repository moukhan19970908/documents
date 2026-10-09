@php
    $statusBadges = [
        'assigned' => 'bg-gray-100 text-gray-600', 'in_progress' => 'bg-blue-50 text-blue-600',
        'submitted' => 'bg-amber-50 text-amber-700', 'done' => 'bg-emerald-50 text-emerald-600',
        'returned' => 'bg-red-50 text-red-600',
    ];
    $openMandatory = $assignment->children->where('is_mandatory', true)->where('status', '!=', 'done')->count();
    $canReport     = $isExecutor && in_array($assignment->status, ['in_progress', 'returned']) && $openMandatory === 0;
    $canExtend     = $isExecutor && $assignment->status !== 'done' && $settings->deadline_extension !== 'disabled' && ! $assignment->pending_due_at;
    $canDelete     = $assignment->canBeDeletedBy(auth()->user());
    $hasActions    = ($isParticipant && in_array($assignment->status, ['assigned', 'in_progress', 'returned']))
        || ($isInitiator && $assignment->status === 'submitted') || $canExtend || $canSubAssign || $canDelete;
    $ownFiles      = $assignment->files->whereNull('source_assignment_id');
    $aggFiles      = $assignment->files->whereNotNull('source_assignment_id');
    $perspective   = $perspectives->isEmpty() ? null : ($perspectives->has(auth()->id()) ? auth()->id() : $perspectives->keys()->first());

    $btnGreen   = 'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-100';
    $btnPurple  = 'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-[#5B4FE8]/10 text-[#5B4FE8] hover:bg-[#5B4FE8]/15';
    $btnNeutral = 'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50';
    $field      = 'w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]';
@endphp

<x-app-layout>
    <x-slot name="title">{{ $assignment->number }} — Поручение</x-slot>

    <div class="max-w-7xl bg-white border border-gray-200 rounded-xl overflow-hidden"
         x-data="{ panel: @js(request('panel', '')), perspective: @js($perspective) }">

        {{-- ── Шапка ── --}}
        <div class="px-6 pt-5 pb-5">
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-2">
                <a href="{{ route('assignments.index') }}" class="hover:text-gray-600">Поручения</a>
                @if($parentVisible)
                    <span>›</span>
                    <a href="{{ route('assignments.show', $assignment->parent) }}" class="font-mono hover:text-gray-600">{{ $assignment->parent->number }}</a>
                @endif
                <span>›</span>
                <span class="font-mono text-gray-500">{{ $assignment->number }}</span>
            </nav>

            @if(session('success'))
                <div class="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">{{ session('error') }}</div>
            @endif

            <div class="flex items-start justify-between gap-4">
                <h1 class="text-xl font-bold text-gray-900">{{ $assignment->title }}</h1>
                <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                    @if($assignment->parent_id)
                        <span class="text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">Подпоручение · ур. {{ $assignment->depth }}</span>
                    @endif
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $statusBadges[$assignment->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $assignment->statusLabel() }}</span>
                    @if($assignment->isOverdue())
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-red-50 text-red-600">Просрочено</span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-x-5 gap-y-2 flex-wrap mt-3 text-sm">
                <span class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">Постановщик</span>
                    @include('partials.user-avatar', ['user' => $assignment->initiator, 'size' => 'w-6 h-6 text-[10px]'])
                    <span class="text-gray-800">{{ $assignment->initiator->name }}</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">Исполнитель</span>
                    @include('partials.user-avatar', ['user' => $assignment->executor, 'size' => 'w-6 h-6 text-[10px]'])
                    <span class="text-gray-800">{{ $assignment->executor->name }}@if($assignment->executor->department), {{ $assignment->executor->department->name }}@endif</span>
                </span>
                @if($assignment->controller)
                    <span class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">Контролёр</span>
                        <span class="text-gray-800">{{ $assignment->controller->name }}</span>
                    </span>
                @endif
                <span class="flex items-center gap-1.5 font-medium {{ $assignment->isOverdue() ? 'text-red-600' : ($assignment->isDone() ? 'text-gray-500' : 'text-amber-600') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $assignment->due_at ? 'Срок: ' . $assignment->due_at->translatedFormat('j F') : 'Без срока' }}
                </span>
            </div>
            @if($assignment->coExecutors->isNotEmpty())
                <p class="text-sm mt-2"><span class="text-xs text-gray-400 mr-2">Соисполнители</span>{{ $assignment->coExecutors->pluck('name')->join(', ') }}</p>
            @endif

            {{-- Действия --}}
            @if($hasActions)
            <div class="flex flex-wrap gap-2 mt-4">
                @if($isParticipant && $assignment->status === 'assigned')
                    <form method="POST" action="{{ route('assignments.start', $assignment) }}">@csrf
                        <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-[#5B4FE8] text-white hover:bg-indigo-700">Взять в работу</button>
                    </form>
                @endif
                @if($isInitiator && $assignment->status === 'submitted')
                    <form method="POST" action="{{ route('assignments.accept', $assignment) }}">@csrf
                        <button class="{{ $btnGreen }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Принять результат
                        </button>
                    </form>
                    <button type="button" @click="panel = panel === 'return' ? '' : 'return'" class="{{ $btnPurple }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Вернуть
                    </button>
                @endif
                @if($isParticipant && in_array($assignment->status, ['in_progress', 'returned']))
                    <button type="button" @click="panel = panel === 'contribute' ? '' : 'contribute'" class="{{ $btnNeutral }}">Приложить материалы</button>
                @endif
                @if($canExtend)
                    <button type="button" @click="panel = panel === 'extend' ? '' : 'extend'" class="{{ $btnNeutral }}">Перенести срок</button>
                @endif
                @if($canSubAssign)
                    <button type="button" @click="panel = panel === 'sub' ? '' : 'sub'" class="{{ $btnPurple }}">+ Подпоручение</button>
                @endif
                @if($canDelete)
                    @php $confirm = "Удалить поручение {$assignment->number}?" . ($assignment->children->isNotEmpty() ? ' Вместе с ним удалятся все его подпоручения.' : ''); @endphp
                    <form method="POST" action="{{ route('assignments.destroy', $assignment) }}" class="ml-auto"
                          onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }})">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 text-red-500 hover:bg-red-50 hover:border-red-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Удалить
                        </button>
                    </form>
                @endif
            </div>
            @endif

            {{-- Панель: приложить материалы (соисполнитель/исполнитель) --}}
            <div x-show="panel === 'contribute'" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Приложить материалы</h3>
                <form method="POST" action="{{ route('assignments.contribute', $assignment) }}" enctype="multipart/form-data">@csrf
                    <input type="file" name="files[]" multiple required class="block text-sm text-gray-600">
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Приложить</button>
                    </div>
                </form>
            </div>

            {{-- Панель: перенос срока --}}
            <div x-show="panel === 'extend'" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Перенос срока</h3>
                <form method="POST" action="{{ route('assignments.extend', $assignment) }}">@csrf
                    <input type="date" name="due_at" required value="{{ $assignment->due_at?->format('Y-m-d') }}" class="{{ $field }} md:w-64">
                    <textarea name="comment" rows="2" required placeholder="Причина переноса (обязательно)" class="{{ $field }} mt-3"></textarea>
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Перенести срок</button>
                    </div>
                </form>
            </div>

            {{-- Панель: возврат --}}
            <div x-show="panel === 'return'" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Возврат на доработку</h3>
                <form method="POST" action="{{ route('assignments.return', $assignment) }}">@csrf
                    <textarea name="return_comment" rows="3" required placeholder="Что доработать (обязательно)" class="{{ $field }}"></textarea>
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Вернуть</button>
                    </div>
                </form>
            </div>

            {{-- Панель: подпоручение --}}
            @if($canSubAssign)
                <div id="panel-sub" x-show="panel === 'sub'" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-gray-900 mb-3">Новое подпоручение</h3>
                    <form method="POST" action="{{ route('assignments.sub', $assignment) }}" class="space-y-3">@csrf
                        <input type="text" name="title" required placeholder="Тема подпоручения" class="{{ $field }}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <select name="executor_id" required class="{{ $field }}">
                                <option value="">— исполнитель —</option>
                                @foreach($executors as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="due_at" class="{{ $field }}">
                        </div>
                        <textarea name="body_html" rows="2" placeholder="Текст (необязательно)" class="{{ $field }}"></textarea>

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

            {{-- Заявка на перенос срока (режим «с одобрением») --}}
            @if($assignment->pending_due_at)
                <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p class="text-sm text-amber-800">
                        <span class="font-semibold">Запрос переноса срока</span> на {{ $assignment->pending_due_at->format('d.m.Y') }}.
                        @if($assignment->pending_due_comment)<span class="block text-amber-700 mt-0.5">{{ $assignment->pending_due_comment }}</span>@endif
                    </p>
                    @if($isInitiator)
                        <div class="flex items-center gap-2 mt-3">
                            <form method="POST" action="{{ route('assignments.extend.approve', $assignment) }}">@csrf
                                <button class="{{ $btnGreen }}">Одобрить</button>
                            </form>
                            <form method="POST" action="{{ route('assignments.extend.reject', $assignment) }}">@csrf
                                <button class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-red-200 text-red-600 hover:bg-red-50">Отклонить</button>
                            </form>
                        </div>
                    @else
                        <p class="text-xs text-amber-600 mt-1">Ожидает решения постановщика.</p>
                    @endif
                </div>
            @endif

            {{-- Что видит каждый участник (послойная видимость) --}}
            @if($perspectives->isNotEmpty())
                <div class="mt-5 pt-5 border-t border-gray-100">
                    <div class="inline-flex flex-wrap gap-1 bg-gray-100 rounded-xl p-1">
                        @foreach($perspectives as $userId => $label)
                            <button type="button" @click="perspective = {{ $userId }}"
                                    :class="perspective === {{ $userId }} ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                                    class="px-3.5 py-1.5 text-sm font-medium rounded-lg transition">{{ $label }}</button>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Видимость дерева послойная — переключите роль, чтобы увидеть, что видит каждый участник</p>
                </div>
            @endif
        </div>

        {{-- ── Дерево слева, материалы/отчёт/история справа (на узком экране — друг под другом) ── --}}
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem] border-t border-gray-200">
            <section class="bg-gray-50 p-6 min-w-0">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Дерево поручений</h2>
                @include('assignments._tree-node', ['node' => $assignment, 'depth' => 0])
            </section>

            <aside class="border-t lg:border-t-0 lg:border-l border-gray-200 p-5 space-y-7">
                {{-- Собранные материалы --}}
                <section>
                    <h3 class="text-sm font-semibold text-gray-900">Собранные материалы</h3>
                    <p class="text-xs text-gray-400 mt-0.5 mb-3">Результаты принятых подпоручений подтягиваются сюда автоматически</p>
                    <div class="space-y-2">
                        @forelse($aggFiles as $f)
                            <a href="{{ route('assignments.file', $f) }}" class="flex items-start gap-2.5 border border-gray-200 rounded-lg px-3 py-2.5 hover:border-[#5B4FE8]/40">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900 truncate">{{ $f->original_name }}</span>
                                    @php $sourceDept = $f->source?->executor?->department?->name; @endphp
                                    <span class="block text-xs text-gray-400 truncate">из «{{ $sourceDept ? $sourceDept . ': ' : '' }}{{ $f->source?->title ?? $f->source?->number }}»</span>
                                </span>
                            </a>
                        @empty
                            <p class="text-sm text-gray-400">Пока пусто — файлы появятся, когда подпоручения будут приняты.</p>
                        @endforelse
                    </div>
                </section>

                {{-- Отчёт исполнителя --}}
                <section>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Отчёт исполнителя</h3>

                    @if($assignment->status === 'returned' && $assignment->return_comment)
                        <div class="mb-3 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5 text-sm">
                            <span class="font-semibold text-red-700">Возвращено на доработку:</span> <span class="text-red-600">{{ $assignment->return_comment }}</span>
                        </div>
                    @endif

                    @if($isExecutor && in_array($assignment->status, ['in_progress', 'returned']))
                        <form method="POST" action="{{ route('assignments.submit', $assignment) }}" enctype="multipart/form-data" x-data="{ files: 0 }">@csrf
                            <textarea name="result_comment" rows="3" placeholder="Опишите результат выполнения поручения…" class="{{ $field }}">{{ old('result_comment') }}</textarea>
                            <label class="mt-3 flex flex-col items-center justify-center gap-1 border-2 border-dashed border-gray-200 rounded-lg py-5 text-sm text-gray-500 cursor-pointer hover:border-[#5B4FE8]/40 focus-within:ring-2 focus-within:ring-[#5B4FE8]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span x-text="files ? 'Выбрано файлов: ' + files : 'Прикрепить итоговые файлы'"></span>
                                <input type="file" name="files[]" multiple class="sr-only" @change="files = $event.target.files.length">
                            </label>
                            <button @disabled(! $canReport)
                                    class="mt-3 w-full py-2.5 rounded-lg text-sm font-medium {{ $canReport ? 'bg-[#5B4FE8] text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">Отчитаться</button>
                            @if($openMandatory > 0)
                                <p class="text-xs text-gray-400 mt-2">Закройте обязательные подпоручения ({{ $openMandatory }}), чтобы отчитаться</p>
                            @endif
                        </form>
                    @elseif($assignment->result_comment || $ownFiles->isNotEmpty())
                        @if($assignment->result_comment)<p class="text-sm text-gray-700 whitespace-pre-line">{{ $assignment->result_comment }}</p>@endif
                        @if($ownFiles->isNotEmpty())
                            <div class="mt-2 space-y-1">
                                @foreach($ownFiles as $f)
                                    <a href="{{ route('assignments.file', $f) }}" class="flex items-center gap-2 text-sm text-[#5B4FE8] hover:underline">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="truncate">{{ $f->original_name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-400">Исполнитель ещё не отчитался.</p>
                    @endif
                </section>

                {{-- История действий --}}
                <section>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">История действий</h3>
                    <div class="space-y-3">
                        @forelse($assignment->events as $e)
                            <div class="flex gap-2.5">
                                @include('partials.user-avatar', ['user' => $e->user, 'size' => 'w-7 h-7 text-[10px]'])
                                <div class="min-w-0 text-sm">
                                    <p class="text-gray-700"><span class="font-medium text-gray-900">{{ $e->user?->name ?? 'Система' }}</span> · {{ \Illuminate\Support\Str::lcfirst($e->label()) }}</p>
                                    @if($e->comment)<p class="text-xs text-gray-500 mt-0.5">{{ $e->comment }}</p>@endif
                                    @if($e->type === 'deadline_changed' && $e->meta)<p class="text-xs text-gray-400">{{ $e->meta['from'] ?? '' }} → {{ $e->meta['to'] ?? '' }}</p>@endif
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $e->created_at->translatedFormat('j F, H:i') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Событий пока нет.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
