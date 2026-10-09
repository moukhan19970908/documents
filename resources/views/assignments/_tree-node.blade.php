{{--
    Узел дерева поручений и (рекурсивно) его видимые подпоручения.
    Требует $node (с visibleChildren), $depth (0 — текущее поручение страницы), $rootInitiatorId, $statusBadges.
    Карточка прячется переключателем «что видит участник», ветка под ней остаётся.
--}}
@php
    $isCurrent  = $depth === 0;
    $initiator  = $node->initiator;
    $executor   = $node->executor;
    // «Откуда → куда»: отдел постановщика → отдел исполнителя, а внутри одного отдела — сам исполнитель.
    $sameDept   = $executor->department_id === null || $executor->department_id === $initiator->department_id;
    $heading    = ($initiator->department?->name ?? $initiator->name) . ' → '
        . ($sameDept ? $executor->name : ($executor->department?->name ?? $executor->name))
        . ($isCurrent ? '' : ': ' . $node->title);
    $filesCount = $isCurrent ? $node->files->whereNull('source_assignment_id')->count() : $node->own_files_count;
    $canSub     = app(\App\Services\AssignmentService::class)->canSubAssign(auth()->user(), $node);
    $canAccept  = ! $isCurrent && $node->status === 'submitted' && $node->initiator_id === auth()->id();
    $canDelete  = ! $isCurrent && $node->canBeDeletedBy(auth()->user());   // у текущего — кнопка в шапке
    // Отчитавшийся исполнитель уже не «просрочил» — постановщику важнее, что узел ждёт приёмки.
    $overdue    = $node->isOverdue() && $node->status !== 'submitted';
@endphp

<div>
    <div x-data="{ ret: false }"
         x-show="perspective === null || @js($node->viewerIds($rootInitiatorId)).includes(perspective)"
         class="rounded-xl p-4 {{ $isCurrent ? 'bg-white border-2 border-[#5B4FE8] shadow-sm' : ($depth === 1 ? 'bg-white border border-gray-200' : 'bg-gray-50 border border-gray-200') }}">
        <div class="flex items-start gap-3">
            @include('partials.user-avatar', ['user' => $executor])
            <div class="min-w-0 flex-1">
                @if($isCurrent)
                    <p class="text-sm font-semibold text-gray-900">{{ $heading }}</p>
                @else
                    <a href="{{ route('assignments.show', $node) }}" class="text-sm font-semibold text-gray-900 hover:text-[#5B4FE8]">{{ $heading }}</a>
                @endif
                <p class="text-xs text-gray-500">{{ $executor->name }}@if($executor->department) · {{ $executor->department->name }}@endif</p>
            </div>
            @if($overdue)
                <span class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-full bg-red-50 text-red-600">Просрочено</span>
            @elseif($node->isDone())
                <span class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600">Выполнено ✓</span>
            @else
                <span class="shrink-0 text-xs font-medium px-2.5 py-1 rounded-full {{ $statusBadges[$node->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $node->statusLabel() }}</span>
            @endif
        </div>

        @if($node->body_html)
            @if($isCurrent)
                <div class="mt-3 text-sm text-gray-700 leading-relaxed overflow-x-auto">{!! $node->body_html !!}</div>
            @else
                <p class="mt-2 text-sm text-gray-700 line-clamp-2">{{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($node->body_html)), 240) }}</p>
            @endif
        @endif

        <div class="mt-3 flex items-center gap-3 flex-wrap">
            <span class="text-xs {{ $overdue ? 'text-red-500' : 'text-gray-400' }}">
                {{ $node->due_at ? 'Срок ' . $node->due_at->translatedFormat('j F') : 'Без срока' }}
            </span>
            @if($filesCount > 0)
                <span class="inline-flex items-center gap-1 text-xs text-gray-400" title="Файлы">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    {{ $filesCount }}
                </span>
            @endif
            @unless($node->is_mandatory)<span class="text-xs text-gray-400">необязательное</span>@endunless

            <div class="ml-auto flex items-center gap-2">
                {{-- Приёмка узла его постановщиком --}}
                @if($canAccept)
                    <form method="POST" action="{{ route('assignments.accept', $node) }}">@csrf
                        <button class="px-3 py-1.5 rounded-lg text-xs font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100">Принять</button>
                    </form>
                    <button type="button" @click="ret = !ret" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-[#5B4FE8]/10 text-[#5B4FE8] hover:bg-[#5B4FE8]/15">Вернуть</button>
                @endif

                @if($canSub)
                    @if($isCurrent)
                        <button type="button"
                                @click="panel = 'sub'; $nextTick(() => document.getElementById('panel-sub')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                                class="text-xs font-medium text-[#5B4FE8] hover:underline">+ Подпоручение</button>
                    @else
                        <a href="{{ route('assignments.show', [$node, 'panel' => 'sub']) }}" class="text-xs font-medium text-[#5B4FE8] hover:underline">+ Подпоручение</a>
                    @endif
                @endif

                @if($canDelete)
                    @php $confirm = "Удалить подпоручение {$node->number}?" . ($node->children->isNotEmpty() ? ' Вместе с ним удалятся все его подпоручения.' : ''); @endphp
                    <form method="POST" action="{{ route('assignments.destroy', $node) }}"
                          onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }})">
                        @csrf @method('DELETE')
                        <button type="submit" title="Удалить подпоручение" aria-label="Удалить подпоручение {{ $node->number }}"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if($canAccept)
            <form x-show="ret" x-cloak method="POST" action="{{ route('assignments.return', $node) }}" class="mt-3">@csrf
                <textarea name="return_comment" rows="2" required placeholder="Что доработать"
                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                <button class="mt-2 px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-medium hover:bg-red-700">Вернуть на доработку</button>
            </form>
        @endif
    </div>

    @if($node->visibleChildren->isNotEmpty())
        <div class="mt-3 ml-4 pl-4 border-l-2 border-gray-200 space-y-3">
            @foreach($node->visibleChildren as $child)
                @include('assignments._tree-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
