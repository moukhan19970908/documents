<x-app-layout>
    <x-slot name="title">{{ $document->title }} — ArchManuscript</x-slot>

    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-3">
            @php
                $statusColors = [
                    'draft'            => 'bg-gray-100 text-gray-700',
                    'in_review'        => 'bg-blue-100 text-blue-700',
                    'requires_changes' => 'bg-red-100 text-red-700',
                    'approved'         => 'bg-green-100 text-green-700',
                    'signed'           => 'bg-indigo-100 text-indigo-700',
                    'archived'         => 'bg-gray-100 text-gray-700',
                ];
            @endphp
            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded {{ $statusColors[$document->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ strtoupper($document->status_label) }}
            </span>
            <span class="text-xs text-gray-500">Обновлено {{ $document->updated_at->diffForHumans() }}</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $document->title }}</h1>
        @if($document->data['description'] ?? null)
            <p class="text-gray-500 mt-1">{{ $document->data['description'] }}</p>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-5">

            {{-- Info cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Counterparty details --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">Детали контрагента</p>
                    @if($document->data)
                        @foreach($document->type?->fields ?? [] as $field)
                            @if(isset($document->data[$field->field_key]))
                                <div class="mb-3">
                                    <p class="text-xs text-gray-500">{{ $field->label }}</p>
                                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $document->data[$field->field_key] }}</p>
                                </div>
                            @endif
                        @endforeach
                        @if(empty($document->type?->fields?->count()))
                            <p class="text-sm text-gray-500">Нет данных</p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">Нет данных</p>
                    @endif
                </div>

                {{-- Finance & dates --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">Финансы и сроки</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Инициатор</p>
                            <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $document->initiator->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Дата создания</p>
                            <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $document->created_at->format('d M, Y') }}</p>
                        </div>
                        @if($document->activeApproval)
                            @php $activeStage = $document->activeApproval->activeStage(); @endphp
                            @if($activeStage?->deadline_at)
                                <div>
                                    <p class="text-xs text-gray-500">Крайний срок</p>
                                    <p class="text-sm font-semibold {{ $activeStage->is_overdue ? 'text-red-600' : 'text-gray-900' }} mt-0.5">
                                        {{ $activeStage->deadline_at->format('d M, Y') }}
                                    </p>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Approval workflow stepper --}}
            @if($document->activeApproval || $document->approvals->isNotEmpty())
                @php
                    $approval = $document->approvals->first();
                    $stages = $approval?->stages ?? collect();
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-5 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Воркфлоу одобрения
                    </p>
                    <div class="flex items-start gap-2 overflow-x-auto pb-2">
                        @foreach($stages as $index => $stage)
                            @php
                                $isApproved  = $stage->status === 'approved';
                                $isCurrent   = $stage->status === 'in_progress';
                                $isPending   = $stage->status === 'pending';
                                $isRejected  = $stage->status === 'rejected';
                                $approverDecision = $stage->decisions->last();
                            @endphp
                            <div class="flex items-center flex-shrink-0">
                                <div class="flex flex-col items-center w-28">
                                    {{-- Circle --}}
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium
                                        @if($isApproved) bg-green-100 text-green-700
                                        @elseif($isCurrent) bg-[#5B4FE8] text-white
                                        @elseif($isRejected) bg-red-100 text-red-700
                                        @else bg-gray-100 text-gray-500
                                        @endif">
                                        @if($isApproved)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @elseif($isCurrent)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        @elseif($isPending)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        @endif
                                    </div>
                                    {{-- Label --}}
                                    <div class="mt-2 text-center">
                                        <p class="text-xs font-medium text-gray-800">{{ $stage->workflowStage?->name }}</p>
                                        @if($approverDecision)
                                            <p class="text-[10px] text-gray-500 mt-0.5">{{ $approverDecision->user->name }}</p>
                                            <p class="text-[10px] text-gray-400">{{ $approverDecision->decided_at?->format('d M') }}</p>
                                        @elseif($isCurrent)
                                            <p class="text-[10px] text-[#5B4FE8] mt-0.5">В ожидании • Вы</p>
                                        @else
                                            <p class="text-[10px] text-gray-400 mt-0.5">Заблокировано</p>
                                        @endif
                                    </div>
                                </div>
                                {{-- Arrow connector --}}
                                @if(!$loop->last)
                                    <div class="w-8 h-px bg-gray-200 mx-1 mt-[-20px]"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Document file --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Основной документ
                    </p>
                    @if($document->currentFile)
                        <a href="{{ route('documents.files.download', [$document, $document->currentFile]) }}"
                           class="flex items-center gap-1.5 text-xs text-[#5B4FE8] font-medium hover:underline">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Скачать оригинал
                        </a>
                    @endif
                </div>

                @if($document->currentFile)
                    <div class="bg-gray-50 rounded-xl p-8 flex flex-col items-center justify-center gap-3 border border-gray-200">
                        <div class="w-16 h-16 bg-red-50 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="text-center">
                            <p class="text-sm font-medium text-gray-900">{{ $document->currentFile->file_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $document->currentFile->formatted_size }} • v{{ $document->currentFile->version }}</p>
                        </div>
                    </div>
                @else
                    <div class="text-center py-6 text-sm text-gray-500">Файл не загружен</div>
                @endif

                {{-- Upload new version --}}
                @can('update', $document)
                    <form action="{{ route('documents.files.store', $document) }}" method="POST" enctype="multipart/form-data" class="mt-4">
                        @csrf
                        <div class="flex items-center gap-3">
                            <input type="file" name="file" class="flex-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 file:text-sm file:font-medium hover:file:bg-gray-200">
                            <button type="submit" class="px-4 py-2 text-sm bg-[#5B4FE8] text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors whitespace-nowrap">
                                Загрузить версию
                            </button>
                        </div>
                    </form>
                @endcan

                {{-- Version history --}}
                @if($document->files->count() > 1)
                    <div class="mt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">История версий</p>
                        <div class="space-y-2">
                            @foreach($document->files->sortByDesc('version') as $file)
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-mono">v{{ $file->version }}</span>
                                    <span class="flex-1 text-gray-700">{{ $file->file_name }}</span>
                                    <span class="text-xs text-gray-400">{{ $file->created_at->format('d.m.Y') }}</span>
                                    @if($file->is_current)
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Текущая</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right column: actions --}}
        <div class="space-y-5">
            @if($document->status === 'draft')
                @can('update', $document)
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">Запустить согласование</p>
                        <form action="{{ route('documents.start-approval', $document) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-[#5B4FE8] text-white py-3 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
                                Запустить согласование
                            </button>
                        </form>
                    </div>
                @endcan
            @endif

            @can('approve', $document)
                <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{ comment: '' }">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">Требуется действие</p>

                    <div class="mb-3">
                        <label class="text-xs text-gray-600 font-medium block mb-1">Заметки к ревью (опционально)</label>
                        <textarea
                            x-model="comment"
                            rows="4"
                            placeholder="Добавьте конкретный отзыв или условия для одобрения..."
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] resize-none"
                        ></textarea>
                    </div>

                    <form action="{{ route('documents.approve', $document) }}" method="POST" class="mb-3">
                        @csrf
                        <input type="hidden" name="comment" :value="comment">
                        <button type="submit" class="w-full bg-[#5B4FE8] text-white py-3 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors min-h-[48px]">
                            Одобрить документ
                        </button>
                    </form>

                    <form action="{{ route('documents.reject', $document) }}" method="POST" class="mb-3">
                        @csrf
                        <input type="hidden" name="comment" :value="comment">
                        <button type="submit" class="w-full border border-red-300 text-red-600 py-3 rounded-lg text-sm font-semibold hover:bg-red-50 transition-colors min-h-[48px]">
                            Отклонить и вернуть
                        </button>
                    </form>

                    <div x-data="{ delegateOpen: false }">
                        <button @click="delegateOpen = !delegateOpen" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 mx-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            Делегировать
                        </button>
                        <form x-show="delegateOpen" action="{{ route('documents.delegate', $document) }}" method="POST" class="mt-3 space-y-2" style="display:none">
                            @csrf
                            <select name="delegated_to" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— Выберите пользователя —</option>
                                @foreach(\App\Models\User::where('id', '!=', auth()->id())->where('is_active', true)->get() as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full border border-gray-200 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                                Подтвердить делегирование
                            </button>
                        </form>
                    </div>

                    <p class="text-xs text-gray-500 mt-4">
                        Одобрение этого документа юридически обязывает организацию соблюдать условия, указанные в
                        @if($document->currentFile) <span class="font-medium">{{ $document->currentFile->file_name }}</span> @endif.
                    </p>
                </div>
            @endcan

            {{-- PDF Download --}}
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
</x-app-layout>
