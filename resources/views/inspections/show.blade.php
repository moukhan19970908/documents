@php
    $statusBadges = [
        'assigned' => 'bg-gray-100 text-gray-600', 'in_progress' => 'bg-blue-50 text-blue-600',
        'submitted' => 'bg-amber-50 text-amber-700', 'done' => 'bg-emerald-50 text-emerald-600',
        'returned' => 'bg-red-50 text-red-600',
    ];
    $openMandatory = $inspection->children->where('is_mandatory', true)->where('status', '!=', 'done')->count();
    $canWorkOn     = $isExecutor && in_array($inspection->status, ['in_progress', 'returned']);
    $canSubmitAct  = $canWorkOn && $openMandatory === 0;
    $canDelete     = $inspection->canBeDeletedBy(auth()->user());
    $hasActions    = ($isExecutor && in_array($inspection->status, ['assigned', 'in_progress', 'returned']))
        || ($isInitiator && $inspection->status === 'submitted') || $canSubRequest || $canSpawn || $canDelete;
    $ownFiles      = $inspection->files->whereNull('source_inspection_id');
    $aggFiles      = $inspection->files->whereNotNull('source_inspection_id');
    $perspective   = $perspectives->isEmpty() ? null : ($perspectives->has(auth()->id()) ? auth()->id() : $perspectives->keys()->first());

    $btnGreen   = 'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-100';
    $btnPurple  = 'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-[#5B4FE8]/10 text-[#5B4FE8] hover:bg-[#5B4FE8]/15';
    $btnNeutral = 'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50';
    $field      = 'w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]';
@endphp

<x-app-layout>
    <x-slot name="title">{{ $inspection->number }} — Проверка</x-slot>

    <div class="max-w-7xl bg-white border border-gray-200 rounded-xl overflow-hidden"
         x-data="{ panel: @js(request('panel', '')), perspective: @js($perspective) }">

        {{-- ── Шапка ── --}}
        <div class="px-6 pt-5 pb-5">
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-2">
                <a href="{{ route('inspections.index') }}" class="hover:text-gray-600">Проверки</a>
                @if($parentVisible)
                    <span>›</span>
                    <a href="{{ route('inspections.show', $inspection->parent) }}" class="font-mono hover:text-gray-600">{{ $inspection->parent->number }}</a>
                @endif
                <span>›</span>
                <span class="font-mono text-gray-500">{{ $inspection->number }}</span>
            </nav>

            @if(session('success'))
                <div class="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
            @endif

            <div class="flex items-start justify-between gap-4">
                <h1 class="text-xl font-bold text-gray-900">{{ $inspection->title }}</h1>
                <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                    @if($inspection->parent_id)
                        <span class="text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">Подпроверка · ур. {{ $inspection->depth }}</span>
                    @endif
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $statusBadges[$inspection->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $inspection->statusLabel() }}</span>
                    @if($inspection->isOverdue())
                        <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-red-50 text-red-600">Просрочено</span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-x-5 gap-y-2 flex-wrap mt-3 text-sm">
                <span class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">Постановщик</span>
                    @include('partials.user-avatar', ['user' => $inspection->initiator, 'size' => 'w-6 h-6 text-[10px]'])
                    <span class="text-gray-800">{{ $inspection->initiator->name }}</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">Проверяющий</span>
                    @include('partials.user-avatar', ['user' => $inspection->executor, 'size' => 'w-6 h-6 text-[10px]'])
                    <span class="text-gray-800">{{ $inspection->executor->name }}@if($inspection->executor->department), {{ $inspection->executor->department->name }}@endif</span>
                </span>
                <span class="flex items-center gap-1.5 font-medium {{ $inspection->isOverdue() ? 'text-red-600' : ($inspection->isDone() ? 'text-gray-500' : 'text-amber-600') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $inspection->due_at ? 'Срок: ' . $inspection->due_at->translatedFormat('j F') : 'Без срока' }}
                </span>
            </div>

            @if($inspection->object_label || $inspection->kind || $inspection->period_from)
                <div class="flex items-center gap-x-5 gap-y-1 flex-wrap mt-2 text-sm">
                    @if($inspection->object_label)
                        <span><span class="text-xs text-gray-400 mr-1.5">{{ $inspection->objectTypeLabel() ?? 'Объект' }}</span>{{ $inspection->object_label }}</span>
                    @endif
                    @if($inspection->kind)
                        <span><span class="text-xs text-gray-400 mr-1.5">Вид</span>{{ $inspection->kindLabel() }}</span>
                    @endif
                    @if($inspection->period_from)
                        <span><span class="text-xs text-gray-400 mr-1.5">Период</span>{{ $inspection->period_from->format('d.m.Y') }}@if($inspection->period_to) – {{ $inspection->period_to->format('d.m.Y') }}@endif</span>
                    @endif
                </div>
            @endif

            {{-- Действия --}}
            @if($hasActions)
            <div class="flex flex-wrap gap-2 mt-4">
                @if($isExecutor && in_array($inspection->status, ['assigned', 'returned']))
                    <form method="POST" action="{{ route('inspections.start', $inspection) }}">@csrf
                        <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-[#5B4FE8] text-white hover:bg-indigo-700">Взять в работу</button>
                    </form>
                @endif
                @if($isInitiator && $inspection->status === 'submitted')
                    <form method="POST" action="{{ route('inspections.accept', $inspection) }}">@csrf
                        <button class="{{ $btnGreen }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Принять акт
                        </button>
                    </form>
                    <button type="button" @click="panel = panel === 'return' ? '' : 'return'" class="{{ $btnPurple }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Вернуть
                    </button>
                @endif
                @if($canWorkOn)
                    <button type="button" @click="panel = panel === 'contribute' ? '' : 'contribute'" class="{{ $btnNeutral }}">Приложить материалы</button>
                @endif
                @if($canSubRequest)
                    <button type="button" @click="panel = panel === 'sub' ? '' : 'sub'" class="{{ $btnPurple }}">+ Подпроверка</button>
                @endif
                @if($canSpawn)
                    <button type="button" @click="panel = panel === 'spawn' ? '' : 'spawn'" class="{{ $btnNeutral }}">Поручение по итогам</button>
                @endif
                @if($canDelete)
                    @php $confirm = "Удалить проверку {$inspection->number}?" . ($inspection->children->isNotEmpty() ? ' Вместе с ней удалятся все её подпроверки.' : ''); @endphp
                    <form method="POST" action="{{ route('inspections.destroy', $inspection) }}" class="ml-auto"
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

            {{-- Панель: приложить материалы --}}
            <div x-show="panel === 'contribute'" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Приложить материалы</h3>
                <form method="POST" action="{{ route('inspections.contribute', $inspection) }}" enctype="multipart/form-data">@csrf
                    <input type="file" name="files[]" multiple required class="block text-sm text-gray-600">
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Приложить</button>
                    </div>
                </form>
            </div>

            {{-- Панель: возврат --}}
            <div x-show="panel === 'return'" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Возврат на доработку</h3>
                <form method="POST" action="{{ route('inspections.return', $inspection) }}">@csrf
                    <textarea name="return_comment" rows="3" required placeholder="Что доработать (обязательно)" class="{{ $field }}"></textarea>
                    <div class="flex justify-end mt-3">
                        <button class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Вернуть</button>
                    </div>
                </form>
            </div>

            {{-- Панель: подпроверка --}}
            @if($canSubRequest)
                <div id="panel-sub" x-show="panel === 'sub'" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-gray-900 mb-3">Новая подпроверка</h3>
                    <form method="POST" action="{{ route('inspections.sub', $inspection) }}" class="space-y-3">@csrf
                        <input type="text" name="title" required placeholder="Что проверить" class="{{ $field }}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <select name="executor_id" required class="{{ $field }}">
                                <option value="">— проверяющий —</option>
                                @foreach($executors as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="due_at" class="{{ $field }}">
                        </div>
                        <textarea name="body_html" rows="2" placeholder="Задача (необязательно)" class="{{ $field }}"></textarea>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_mandatory" value="1" checked class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            Обязательная (акт нельзя сдать, пока она не принята)
                        </label>
                        <div class="flex justify-end">
                            <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Поставить подпроверку</button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Панель: поручение по итогам (ТЗ 20.2) --}}
            @if($canSpawn)
                <div x-show="panel === 'spawn'" x-cloak class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-gray-900 mb-3">Поручение по итогам проверки</h3>
                    <form method="POST" action="{{ route('inspections.spawn-assignment', $inspection) }}" class="space-y-3">@csrf
                        <input name="title" value="Устранить нарушения по проверке {{ $inspection->number }}" required class="{{ $field }}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <select name="executor_id" required class="{{ $field }}">
                                <option value="">— исполнитель —</option>
                                @foreach($executors as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="due_at" class="{{ $field }}">
                        </div>
                        <textarea name="body" rows="2" placeholder="Что устранить…" class="{{ $field }}"></textarea>
                        <div class="flex justify-end">
                            <button class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Поставить поручение</button>
                        </div>
                    </form>
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

        {{-- ── Дерево слева, материалы/акт/история справа (на узком экране — друг под другом) ── --}}
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem] border-t border-gray-200">
            <section class="bg-gray-50 p-6 min-w-0">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Дерево проверок</h2>
                @include('inspections._tree-node', ['node' => $inspection, 'depth' => 0])
            </section>

            <aside class="border-t lg:border-t-0 lg:border-l border-gray-200 p-5 space-y-7">
                {{-- Собранные материалы --}}
                <section>
                    <h3 class="text-sm font-semibold text-gray-900">Собранные материалы</h3>
                    <p class="text-xs text-gray-400 mt-0.5 mb-3">Результаты принятых подпроверок подтягиваются сюда автоматически</p>
                    <div class="space-y-2">
                        @forelse($aggFiles as $f)
                            <a href="{{ route('inspections.file', $f) }}" class="flex items-start gap-2.5 border border-gray-200 rounded-lg px-3 py-2.5 hover:border-[#5B4FE8]/40">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900 truncate">{{ $f->original_name }}</span>
                                    @php $sourceDept = $f->source?->executor?->department?->name; @endphp
                                    <span class="block text-xs text-gray-400 truncate">из «{{ $sourceDept ? $sourceDept . ': ' : '' }}{{ $f->source?->title ?? $f->source?->number }}»</span>
                                </span>
                            </a>
                        @empty
                            <p class="text-sm text-gray-400">Пока пусто — файлы появятся, когда подпроверки будут приняты.</p>
                        @endforelse
                    </div>
                </section>

                {{-- Итоговый акт --}}
                <section>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Итоговый акт</h3>

                    @if($inspection->status === 'returned' && $inspection->return_comment)
                        <div class="mb-3 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5 text-sm">
                            <span class="font-semibold text-red-700">Возвращено на доработку:</span> <span class="text-red-600">{{ $inspection->return_comment }}</span>
                        </div>
                    @endif

                    @if($canWorkOn)
                        <form method="POST" action="{{ route('inspections.submit', $inspection) }}" enctype="multipart/form-data"
                              x-data="{ verdict: @js(old('act_verdict', 'not_found')), files: 0 }" class="space-y-3">@csrf
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($verdicts as $key => $label)
                                    <label class="border rounded-lg px-3 py-2.5 text-sm font-medium cursor-pointer text-center transition-colors focus-within:ring-2 focus-within:ring-[#5B4FE8]"
                                           :class="verdict === '{{ $key }}' ? '{{ $key === 'found' ? 'border-red-300 bg-red-50 text-red-700' : 'border-emerald-300 bg-emerald-50 text-emerald-700' }}' : 'border-gray-200 text-gray-600'">
                                        <input type="radio" name="act_verdict" value="{{ $key }}" x-model="verdict" class="sr-only">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <textarea x-show="verdict === 'found'" x-cloak name="act_violations" rows="3" placeholder="Перечень выявленных нарушений…" class="{{ $field }}">{{ old('act_violations') }}</textarea>
                            <textarea name="result_comment" rows="3" placeholder="Комментарий к акту…" class="{{ $field }}">{{ old('result_comment') }}</textarea>
                            <label class="flex flex-col items-center justify-center gap-1 border-2 border-dashed border-gray-200 rounded-lg py-5 text-sm text-gray-500 cursor-pointer hover:border-[#5B4FE8]/40 focus-within:ring-2 focus-within:ring-[#5B4FE8]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span x-text="files ? 'Выбрано файлов: ' + files : 'Прикрепить служебные записки'"></span>
                                <input type="file" name="files[]" multiple class="sr-only" @change="files = $event.target.files.length">
                            </label>
                            <button @disabled(! $canSubmitAct)
                                    class="w-full py-2.5 rounded-lg text-sm font-medium {{ $canSubmitAct ? 'bg-[#5B4FE8] text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">Сдать акт</button>
                            @if($openMandatory > 0)
                                <p class="text-xs text-gray-400">Закройте обязательные подпроверки ({{ $openMandatory }}), чтобы сдать акт</p>
                            @endif
                        </form>
                    @elseif($inspection->act_verdict)
                        <span class="inline-flex text-xs font-medium px-2.5 py-1 rounded-full {{ $inspection->act_verdict === 'found' ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }}">{{ $inspection->verdictLabel() }}</span>
                        @if($inspection->act_violations)
                            <p class="text-xs text-gray-400 mt-3 mb-0.5">Перечень нарушений</p>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $inspection->act_violations }}</p>
                        @endif
                        @if($inspection->result_comment)
                            <p class="text-sm text-gray-700 whitespace-pre-line mt-3">{{ $inspection->result_comment }}</p>
                        @endif
                        @if($ownFiles->isNotEmpty())
                            <div class="mt-3 space-y-1">
                                @foreach($ownFiles as $f)
                                    <a href="{{ route('inspections.file', $f) }}" class="flex items-center gap-2 text-sm text-[#5B4FE8] hover:underline">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="truncate">{{ $f->original_name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-400">Проверяющий ещё не сдал акт.</p>
                    @endif
                </section>

                {{-- История действий --}}
                <section>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">История действий</h3>
                    <div class="space-y-3">
                        @forelse($inspection->events as $e)
                            <div class="flex gap-2.5">
                                @include('partials.user-avatar', ['user' => $e->user, 'size' => 'w-7 h-7 text-[10px]'])
                                <div class="min-w-0 text-sm">
                                    <p class="text-gray-700"><span class="font-medium text-gray-900">{{ $e->user?->name ?? 'Система' }}</span> · {{ \Illuminate\Support\Str::lcfirst($e->label()) }}</p>
                                    @if($e->comment)<p class="text-xs text-gray-500 mt-0.5">{{ $e->comment }}</p>@endif
                                    @if($e->type === 'subrequest_created' && ($e->meta['child_number'] ?? null))
                                        <p class="text-xs font-mono text-gray-500 mt-0.5">{{ $e->meta['child_number'] }}</p>
                                    @elseif($e->type === 'assignment_spawned' && ($e->meta['assignment_id'] ?? null))
                                        <a href="{{ route('assignments.show', $e->meta['assignment_id']) }}" class="text-xs font-mono text-[#5B4FE8] hover:underline">{{ $e->meta['assignment_number'] ?? 'поручение' }}</a>
                                    @endif
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
