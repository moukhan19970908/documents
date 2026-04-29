<x-app-layout>
    <x-slot name="title">{{ $document->title }} — Vamin</x-slot>

    @php
        $statusColors = [
            'draft'            => 'bg-gray-100 text-gray-600',
            'in_review'        => 'bg-blue-100 text-blue-700',
            'requires_changes' => 'bg-orange-100 text-orange-700',
            'approved'         => 'bg-green-100 text-green-700',
            'signed'           => 'bg-green-100 text-green-700',
            'rejected'         => 'bg-red-100 text-red-700',
            'archived'         => 'bg-gray-100 text-gray-500',
        ];
        $statusBadge = $statusColors[$document->status] ?? 'bg-gray-100 text-gray-600';

        $approval     = $document->activeApproval;
        $activeStage  = $approval?->activeStage();
        $deadline     = $activeStage?->deadline_at;
        $isOverdue    = $activeStage?->is_overdue ?? false;

        $myApproverEntry = null;
        if ($activeStage) {
            $myApproverEntry = $activeStage->workflowStage?->approvers
                ->firstWhere('approver_id', auth()->id());
        }
        $canApprove = $myApproverEntry !== null;
    @endphp

    {{-- ── Top bar ── --}}
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('documents.index') }}" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Назад к списку
        </a>
        <div class="flex items-center gap-4">
            @if($deadline)
                <span class="text-sm font-medium {{ $isOverdue ? 'text-red-600' : 'text-gray-600' }} flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Крайний срок: {{ $deadline->format('d.m.Y H:i') }}
                </span>
            @endif
            <button class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </button>
        </div>
    </div>

    {{-- ── Title ── --}}
    <div class="mb-5">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ $document->title }}</h1>
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full {{ $statusBadge }}">
                {{ $document->status_label }}
            </span>
        </div>
        <p class="text-sm text-gray-500 mt-1">
            Инициатор: <span class="font-medium text-gray-700">{{ $document->initiator->name }}</span>
            &nbsp;•&nbsp; Создан: {{ $document->created_at->format('d.m.Y') }}
        </p>
    </div>

    {{-- ── Tabs ── --}}
    <div x-data="{ tab: 'overview' }">
        <div class="flex gap-0 border-b border-gray-200 mb-6 overflow-x-auto">
            @foreach([
                ['key' => 'overview',  'label' => 'Обзор'],
                ['key' => 'approval',  'label' => 'Процесс согласования'],
                ['key' => 'files',     'label' => 'Документ и версии'],
                ['key' => 'chat',      'label' => 'Чат'],
                ['key' => 'history',   'label' => 'История'],
                ['key' => 'related',   'label' => 'Связанные документы'],
            ] as $t)
            <button @click="tab = '{{ $t['key'] }}'"
                    :class="tab === '{{ $t['key'] }}' ? 'border-b-2 border-[#5B4FE8] text-[#5B4FE8] font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2.5 text-sm whitespace-nowrap transition-colors -mb-px">
                {{ $t['label'] }}
            </button>
            @endforeach
        </div>

        {{-- ── TAB: ОБЗОР ── --}}
        <div x-show="tab === 'overview'">
            <div class="flex gap-5 items-start">

                {{-- Left: О документе --}}
                <div class="w-64 shrink-0">
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">О документе</p>
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs text-gray-400">Тип документа</p>
                                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $document->type?->name ?? '—' }}</p>
                            </div>
                            @if($document->data)
                                @foreach($document->type?->fields ?? [] as $field)
                                    @if(isset($document->data[$field->field_key]))
                                        <div>
                                            <p class="text-xs text-gray-400">{{ $field->label }}</p>
                                            <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $document->data[$field->field_key] }}</p>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                            <div>
                                <p class="text-xs text-gray-400">Инициатор</p>
                                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $document->initiator->name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Дата создания</p>
                                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $document->created_at->format('d.m.Y') }}</p>
                            </div>
                            @if($deadline)
                                <div>
                                    <p class="text-xs text-gray-400">Крайний срок</p>
                                    <p class="text-sm font-medium mt-0.5 {{ $isOverdue ? 'text-red-600' : 'text-gray-900' }}">
                                        {{ $deadline->format('d.m.Y H:i') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Center: Document viewer --}}
                <div class="flex-1 min-w-0">
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50">
                            <p class="text-xs font-medium text-gray-600 truncate">
                                {{ $document->currentFile?->file_name ?? 'Файл не загружен' }}
                                @if($document->currentFile)
                                    <span class="text-gray-400 ml-1">• {{ $document->currentFile->formatted_size }} • v{{ $document->currentFile->version }}</span>
                                @endif
                            </p>
                            @if($document->currentFile)
                                <a href="{{ route('documents.files.download', [$document, $document->currentFile]) }}"
                                   class="flex items-center gap-1 text-xs text-[#5B4FE8] hover:underline font-medium shrink-0 ml-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Скачать
                                </a>
                            @endif
                        </div>

                        @if($document->currentFile)
                            @php $mime = $document->currentFile->mime_type; @endphp
                            @if($mime === 'application/pdf')
                                <iframe src="{{ route('documents.files.preview', [$document, $document->currentFile]) }}"
                                        class="w-full border-0" style="height:680px"></iframe>
                            @elseif(str_contains($mime, 'wordprocessingml') || str_contains($mime, 'msword'))
                                <div class="p-4 bg-gray-50" style="min-height:680px">
                                    <div id="docx-render-container" class="w-full flex flex-col items-center gap-4"></div>
                                </div>
                                <script>
                                document.addEventListener('DOMContentLoaded', async () => {
                                    const loadScript = (src) => new Promise((resolve, reject) => {
                                        if (document.querySelector('script[src="'+src+'"]')) { resolve(); return; }
                                        const s = document.createElement('script'); s.src = src;
                                        s.onload = resolve; s.onerror = reject;
                                        document.head.appendChild(s);
                                    });
                                    try {
                                        if (typeof JSZip === 'undefined') await loadScript('https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js');
                                        if (typeof docx === 'undefined')  await loadScript('https://cdn.jsdelivr.net/npm/docx-preview@0.3.4/dist/docx-preview.min.js');
                                        const res = await fetch('{{ route('documents.files.preview', [$document, $document->currentFile]) }}', { credentials: 'same-origin' });
                                        if (!res.ok) throw new Error('HTTP ' + res.status);
                                        const buf = await res.arrayBuffer();
                                        const container = document.getElementById('docx-render-container');
                                        await docx.renderAsync(buf, container, null, { className:'docx-render', inWrapper:false, ignoreWidth:true, ignoreHeight:true, breakPages:true, useBase64URL:true });
                                    } catch(e) { console.error(e); }
                                });
                                </script>
                            @elseif(str_starts_with($mime, 'image/'))
                                <div class="flex items-center justify-center p-8 bg-gray-50" style="min-height:680px">
                                    <img src="{{ route('documents.files.preview', [$document, $document->currentFile]) }}"
                                         class="max-w-full object-contain rounded-lg shadow" alt="">
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center gap-4 py-24 bg-gray-50 text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-14 h-14 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <p class="text-sm">Предпросмотр недоступен</p>
                                    <a href="{{ route('documents.files.download', [$document, $document->currentFile]) }}"
                                       class="text-sm bg-[#5B4FE8] text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Скачать</a>
                                </div>
                            @endif
                        @else
                            <div class="flex flex-col items-center justify-center gap-3 py-24 bg-gray-50 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-sm">Файл не загружен</p>
                            </div>
                        @endif
                    </div>

                    @can('update', $document)
                        <form action="{{ route('documents.files.store', $document) }}" method="POST" enctype="multipart/form-data" class="mt-3 flex items-center gap-3">
                            @csrf
                            <input type="file" name="file" class="flex-1 text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 file:text-xs hover:file:bg-gray-200">
                            <button type="submit" class="px-4 py-2 text-sm bg-[#5B4FE8] text-white rounded-lg font-medium hover:bg-indigo-700 whitespace-nowrap">
                                Загрузить версию
                            </button>
                        </form>
                    @endcan
                </div>

                {{-- Right: Ваш шаг / Actions --}}
                <div class="w-72 shrink-0 space-y-4">

                    {{-- Start approval modal (draft) / Resubmit (requires_changes) --}}
                    @if($document->status === 'requires_changes')
                        @can('update', $document)
                            <div class="bg-white rounded-xl border border-orange-200 p-4">
                                <div class="mb-3 flex items-start gap-2 p-3 bg-orange-50 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                    <p class="text-xs text-orange-700">Документ отправлен на доработку. Загрузите исправленную версию и нажмите кнопку ниже.</p>
                                </div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Повторное согласование</p>
                                <form action="{{ route('documents.resubmit', $document) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full text-white py-2.5 rounded-lg text-sm font-semibold transition-colors" style="background:#5B4FE8">
                                        Отправить на повторное согласование
                                    </button>
                                </form>
                            </div>
                        @endcan
                    @elseif($document->status === 'draft')
                        @can('update', $document)
                            <div x-data="approvalModal" class="bg-white rounded-xl border border-gray-200 p-4">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Запустить согласование</p>
                                <button @click="open = true" type="button"
                                        class="w-full bg-[#5B4FE8] text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
                                    Запустить согласование
                                </button>

                                <div x-show="open"
                                     x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition-opacity duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                                     style="display:none" @click.self="open = false">

                                    <div x-show="open"
                                         x-transition:enter="transition transform duration-200 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition transform duration-150 ease-in" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                         class="bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col" style="max-height:85vh; display:none">

                                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                                            <div>
                                                <h2 class="text-base font-semibold text-gray-900">Выбор согласующих</h2>
                                                <p class="text-xs text-gray-500 mt-0.5">Выберите сотрудников для согласования документа</p>
                                            </div>
                                            <button @click="open = false" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div class="px-6 pt-4">
                                            <div class="relative">
                                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                                <input x-model="search" type="text" placeholder="Поиск по имени или отделу..."
                                                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                            </div>
                                        </div>
                                        <div class="px-6 pt-3 pb-1">
                                            <p class="text-xs text-gray-500">Выбрано: <span class="font-semibold text-[#5B4FE8]" x-text="selected.length"></span></p>
                                        </div>
                                        <form action="{{ route('documents.start-approval', $document) }}" method="POST"
                                              class="flex flex-col min-h-0 overflow-hidden flex-1">
                                            @csrf
                                            <div class="overflow-y-auto px-6 py-2 space-y-1.5" style="max-height:340px">
                                                @foreach($approvers as $approver)
                                                    <label x-show="matchesSearch('{{ addslashes($approver->name) }}', '{{ addslashes($approver->department?->name ?? '') }}')"
                                                           class="flex items-center gap-3 p-2.5 rounded-xl cursor-pointer hover:bg-gray-50 border border-transparent"
                                                           :class="selected.includes('{{ $approver->id }}') ? 'border-[#5B4FE8] bg-indigo-50' : ''">
                                                        <input type="checkbox" name="approvers[]" value="{{ $approver->id }}"
                                                               x-model="selected" class="w-4 h-4 rounded text-[#5B4FE8] border-gray-300 focus:ring-[#5B4FE8]">
                                                        <div class="w-8 h-8 rounded-full bg-[#5B4FE8] text-white flex items-center justify-center text-xs font-semibold shrink-0">
                                                            {{ strtoupper(mb_substr($approver->name, 0, 1)) }}
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-medium text-gray-900 truncate">{{ $approver->name }}</p>
                                                            <p class="text-xs text-gray-500 truncate">{{ $approver->department?->name ?? '—' }}</p>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <div class="px-6 py-4 border-t border-gray-100 flex items-center gap-3">
                                                <button type="button" @click="open = false"
                                                        class="flex-1 border border-gray-200 text-gray-700 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-50">Отмена</button>
                                                <button type="submit" :disabled="selected.length === 0"
                                                        class="flex-1 bg-[#5B4FE8] text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
                                                    Запустить
                                                    <span x-show="selected.length > 0" x-text="'(' + selected.length + ')'" class="ml-1"></span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endcan
                    @endif

                    {{-- Ваш шаг --}}
                    @if($canApprove)
                        @php $currentApprover = auth()->user(); @endphp
                        <div class="bg-white rounded-xl border border-gray-200 p-4" x-data="{ comment: '', delegateOpen: false }">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">Ваш шаг</p>

                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 bg-indigo-100 flex items-center justify-center border-2 border-white shadow">
                                    @if($currentApprover->avatar)
                                        <img src="{{ $currentApprover->avatar_url }}" alt="{{ $currentApprover->name }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-sm font-bold text-indigo-600">{{ mb_strtoupper(mb_substr($currentApprover->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $currentApprover->name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $currentApprover->department?->name ?? ($currentApprover->position ?? '—') }}</p>
                                </div>
                            </div>

                            @if($deadline)
                                <p class="text-xs text-gray-500 mb-4 flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 {{ $isOverdue ? 'text-red-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Ожидает ваше решение до {{ $deadline->format('d.m.Y H:i') }}
                                </p>
                            @endif

                            <form action="{{ route('documents.approve', $document) }}" method="POST" class="mb-2">
                                @csrf
                                <input type="hidden" name="comment" :value="comment">
                                <button type="submit" class="w-full text-white py-2.5 rounded-lg text-sm font-semibold transition-colors" style="background:#22c55e">
                                    Согласовать
                                </button>
                            </form>

                            <form action="{{ route('documents.reject', $document) }}" method="POST" class="mb-2">
                                @csrf
                                <input type="hidden" name="comment" :value="comment">
                                <button type="submit" class="w-full text-white py-2.5 rounded-lg text-sm font-semibold transition-colors" style="background:#ef4444">
                                    Отклонить
                                </button>
                            </form>

                            <form action="{{ route('documents.request-changes', $document) }}" method="POST" class="mb-2">
                                @csrf
                                <input type="hidden" name="comment" :value="comment">
                                <button type="submit" class="w-full text-white py-2.5 rounded-lg text-sm font-semibold transition-colors" style="background:#f97316">
                                    Отправить на доработку
                                </button>
                            </form>

                            <button @click="delegateOpen = !delegateOpen"
                                    class="w-full text-white py-2.5 rounded-lg text-sm font-semibold transition-colors mb-3" style="background:#3b82f6">
                                Делегировать
                            </button>

                            <form x-show="delegateOpen" action="{{ route('documents.delegate', $document) }}" method="POST" class="mb-3 space-y-2" style="display:none">
                                @csrf
                                <select name="delegated_to" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                    <option value="">— Выберите пользователя —</option>
                                    @foreach(\App\Models\User::where('id', '!=', auth()->id())->where('is_active', true)->orderBy('name')->get() as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="w-full border border-gray-200 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                                    Подтвердить делегирование
                                </button>
                            </form>

                            <textarea x-model="comment" rows="3"
                                      placeholder="Добавить комментарий..."
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] resize-none text-gray-700 placeholder-gray-300"></textarea>
                        </div>
                    @endif

                    {{-- Approval sheet --}}
                    @if(in_array($document->status, ['approved', 'signed', 'archived']))
                        <a href="{{ route('documents.approval-sheet', $document) }}"
                           class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 p-4 hover:bg-gray-50 transition-colors">
                            <div class="w-9 h-9 bg-red-50 rounded-lg flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Лист согласования</p>
                                <p class="text-xs text-gray-500">Скачать PDF</p>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── TAB: ПРОЦЕСС СОГЛАСОВАНИЯ ── --}}
        <div x-show="tab === 'approval'">
            @if($approval)
                @php
                    $stages = $approval->stages;
                    $nodes  = [[
                        'type'   => 'initiator',
                        'label'  => 'Инициатор',
                        'name'   => $document->initiator->name,
                        'date'   => $document->created_at->format('d.m.Y'),
                        'status' => 'done',
                        'isMe'   => false,
                    ]];
                    foreach ($stages as $stage) {
                        foreach ($stage->workflowStage?->approvers ?? [] as $ap) {
                            $decision = $stage->decisions->where('user_id', $ap->approver_id)->sortByDesc('decided_at')->first();
                            if ($decision) {
                                $ns = $decision->action === 'approve' ? 'approved' : ($decision->action === 'reject' ? 'rejected' : 'delegated');
                            } elseif ($stage->status === 'in_progress') {
                                $ns = 'waiting';
                            } elseif ($stage->status === 'approved') {
                                $ns = 'approved';
                            } else {
                                $ns = 'pending';
                            }
                            $nodes[] = [
                                'type'   => 'approver',
                                'label'  => $stage->workflowStage?->name ?? 'Согласование',
                                'name'   => $ap->user?->name ?? '—',
                                'date'   => $decision?->decided_at?->format('d.m.Y'),
                                'status' => $ns,
                                'isMe'   => $ap->approver_id === auth()->id(),
                            ];
                        }
                    }
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-800 mb-6">Цепочка согласования</h2>
                    <div class="flex items-start gap-0 overflow-x-auto pb-2">
                        @foreach($nodes as $i => $node)
                            @php
                                $circleCls = match($node['status']) {
                                    'done', 'approved' => 'bg-green-100 text-green-700',
                                    'rejected'         => 'bg-red-100 text-red-700',
                                    'waiting'          => 'bg-[#5B4FE8] text-white',
                                    'delegated'        => 'bg-yellow-100 text-yellow-700',
                                    default            => 'bg-gray-100 text-gray-400',
                                };
                            @endphp
                            <div class="flex items-center flex-shrink-0">
                                <div class="flex flex-col items-center" style="width:100px">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $circleCls }}">
                                        @if(in_array($node['status'], ['done','approved']))
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @elseif($node['status'] === 'rejected')
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        @elseif($node['status'] === 'waiting')
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        @else
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-center px-1">
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $node['label'] }}</p>
                                        <p class="text-xs font-medium text-gray-900 mt-0.5 leading-tight">{{ $node['name'] }}</p>
                                        @if($node['date'])
                                            <p class="text-[10px] text-gray-400 mt-0.5">{{ $node['date'] }}</p>
                                        @elseif($node['status'] === 'waiting')
                                            <p class="text-[10px] mt-0.5 {{ $node['isMe'] ? 'text-[#5B4FE8] font-medium' : 'text-gray-400' }}">
                                                {{ $node['isMe'] ? 'Ожидает вас' : 'В ожидании' }}
                                            </p>
                                        @else
                                            <p class="text-[10px] text-gray-300 mt-0.5">Заблокировано</p>
                                        @endif
                                    </div>
                                </div>
                                @if(!$loop->last)
                                    <div class="flex-shrink-0 w-6 h-px bg-gray-200 mt-[-32px]"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 text-sm">
                    Согласование ещё не запущено
                </div>
            @endif
        </div>

        {{-- ── TAB: ДОКУМЕНТ И ВЕРСИИ ── --}}
        <div x-show="tab === 'files'">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-800 mb-4">Версии документа</h2>
                @if($document->files->isNotEmpty())
                    <div class="space-y-0">
                        @foreach($document->files->sortByDesc('version') as $file)
                            <div class="flex items-center gap-3 py-3 border-b border-gray-50 last:border-0">
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-mono">v{{ $file->version }}</span>
                                <span class="flex-1 text-sm text-gray-700">{{ $file->file_name }}</span>
                                <span class="text-xs text-gray-400">{{ $file->formatted_size }}</span>
                                <span class="text-xs text-gray-400">{{ $file->created_at->format('d.m.Y') }}</span>
                                @if($file->is_current)
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Текущая</span>
                                @endif
                                <a href="{{ route('documents.files.download', [$document, $file]) }}" class="text-xs text-[#5B4FE8] hover:underline">Скачать</a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400">Файлы не загружены</p>
                @endif
            </div>
        </div>

        {{-- ── TAB: ЧАТ ── --}}
        <div x-show="tab === 'chat'">
            <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 text-sm">
                Чат находится в разработке
            </div>
        </div>

        {{-- ── TAB: ИСТОРИЯ ── --}}
        <div x-show="tab === 'history'">
            @php
                $approvalKeywords = ['начал процесс', 'согласовал', 'отказал', 'отправил на доработку', 'делегировал'];
                $auditLogs = \App\Models\AuditLog::with('user')
                    ->where('model_type', 'App\Models\Document')
                    ->where('model_id', $document->id)
                    ->where(function ($q) use ($approvalKeywords) {
                        foreach ($approvalKeywords as $keyword) {
                            $q->orWhere('action', 'LIKE', '%' . $keyword . '%');
                        }
                    })
                    ->latest()->get();
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-800 mb-4">История изменений</h2>
                @forelse($auditLogs as $log)
                    <div class="flex gap-3 py-3 border-b border-gray-50 last:border-0">
                        <div class="w-7 h-7 rounded-full overflow-hidden shrink-0 mt-0.5 bg-gray-100 flex items-center justify-center">
                            @if($log->user)
                                <img src="{{ $log->user->avatar_url }}" alt="" class="w-full h-full object-cover">
                            @else
                                <span class="text-xs text-gray-400">S</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-700">
                                <span class="font-medium">{{ $log->user?->name ?? 'Система' }}</span>
                                — {{ $log->action }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $log->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">История пуста</p>
                @endforelse
            </div>
        </div>

        {{-- ── TAB: СВЯЗАННЫЕ ДОКУМЕНТЫ ── --}}
        <div x-show="tab === 'related'">
            <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 text-sm">
                Связанные документы в разработке
            </div>
        </div>

    </div>

</x-app-layout>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('approvalModal', () => ({
        open: false,
        search: '',
        selected: [],
        matchesSearch(name, department) {
            if (!this.search) return true;
            const q = this.search.toLowerCase();
            return name.toLowerCase().includes(q) || department.toLowerCase().includes(q);
        }
    }));
});
</script>

<style>
.docx-render section {
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    border-radius: 4px;
    margin-bottom: 24px;
    width: 100% !important;
    max-width: 820px;
    box-sizing: border-box;
}
</style>

