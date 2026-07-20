<x-app-layout>
    <x-slot name="title">{{ $inspection->title }} — Проверка</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Заголовок --}}
        <div>
            <a href="{{ route('inspections.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← Проверки</a>
            @if($parentVisible)
                <a href="{{ route('inspections.show', $inspection->parent) }}" class="text-sm text-gray-400 hover:text-gray-600 ml-3">↑ Родительская: {{ $inspection->parent->number }}</a>
            @endif
            <div class="flex items-start justify-between mt-1">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $inspection->title }}</h1>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $inspection->statusColor() }}">{{ $inspection->statusLabel() }}</span>
                        @unless($inspection->isRoot())<span class="text-xs text-indigo-500">запрос данных</span>@endunless
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        <span class="font-mono">{{ $inspection->number }}</span>
                        · постановщик {{ $inspection->initiator?->name }} · проверяющий {{ $inspection->executor?->name }}
                    </div>
                    @if($inspection->object_label || $inspection->kind || $inspection->period_from)
                        <div class="text-sm text-gray-500 mt-1">
                            @if($inspection->objectTypeLabel())<span class="text-gray-400">{{ $inspection->objectTypeLabel() }}:</span> {{ $inspection->object_label }}@endif
                            @if($inspection->kind) · {{ $inspection->kindLabel() }}@endif
                            @if($inspection->period_from) · период {{ $inspection->period_from->format('d.m.Y') }}@if($inspection->period_to)–{{ $inspection->period_to->format('d.m.Y') }}@endif @endif
                            @if($inspection->due_at) · срок {{ $inspection->due_at->format('d.m.Y') }}@endif
                        </div>
                    @endif
                </div>
                @if(($isInitiator || auth()->user()->isAdmin()) && $inspection->children->isEmpty() && $inspection->status === 'assigned')
                    <form method="POST" action="{{ route('inspections.destroy', $inspection) }}" onsubmit="return confirm('Удалить проверку?')">
                        @csrf @method('DELETE')
                        <button class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs hover:bg-red-50">Удалить</button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))<div class="px-4 py-3 rounded-lg bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif

        @if($inspection->body_html)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-2">Задача</h2>
                <div class="prose prose-sm max-w-none text-gray-700">{!! $inspection->body_html !!}</div>
            </div>
        @endif

        {{-- Итоговый акт (после сдачи) --}}
        @if($inspection->act_verdict)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-2">Итоговый акт</h2>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $inspection->act_verdict === 'found' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                    {{ $inspection->verdictLabel() }}
                </span>
                @if($inspection->act_violations)
                    <div class="mt-3">
                        <div class="text-xs text-gray-500 mb-1">Перечень нарушений</div>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $inspection->act_violations }}</p>
                    </div>
                @endif
                @if($inspection->result_comment)<p class="text-sm text-gray-600 mt-2">{{ $inspection->result_comment }}</p>@endif
            </div>
        @endif

        {{-- Действия исполнителя --}}
        @if($isExecutor && ! $inspection->isDone())
            <div class="bg-white rounded-xl border-2 border-[#5B4FE8]/30 p-5 space-y-4" x-data="{ submitting: false }">
                <div class="flex items-center gap-2 flex-wrap">
                    @if(in_array($inspection->status, ['assigned', 'returned'], true))
                        <form method="POST" action="{{ route('inspections.start', $inspection) }}">
                            @csrf
                            <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Взять в работу</button>
                        </form>
                    @endif
                    @if(in_array($inspection->status, ['in_progress', 'returned'], true))
                        <button @click="submitting = !submitting" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Сдать акт</button>
                    @endif
                </div>

                @if($inspection->status === 'returned' && $inspection->return_comment)
                    <p class="text-sm text-red-600">Возвращено: {{ $inspection->return_comment }}</p>
                @endif

                {{-- Приложить материалы --}}
                @if(in_array($inspection->status, ['in_progress', 'returned'], true))
                    <form method="POST" action="{{ route('inspections.contribute', $inspection) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="files[]" multiple required
                               class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-gray-100 file:text-gray-700">
                        <button class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Приложить</button>
                    </form>
                @endif

                {{-- Форма итогового акта --}}
                <div x-show="submitting" x-cloak class="border-t border-gray-100 pt-4">
                    <form method="POST" action="{{ route('inspections.submit', $inspection) }}" enctype="multipart/form-data"
                          x-data="{ verdict: '{{ old('act_verdict', 'not_found') }}' }" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Результат проверки</label>
                            <div class="flex gap-3">
                                @foreach($verdicts as $key => $label)
                                    <label class="flex-1 border rounded-lg px-4 py-3 cursor-pointer transition-colors"
                                           :class="verdict === '{{ $key }}' ? '{{ $key === 'found' ? 'border-red-400 bg-red-50' : 'border-green-400 bg-green-50' }}' : 'border-gray-200'">
                                        <input type="radio" name="act_verdict" value="{{ $key }}" x-model="verdict" class="sr-only">
                                        <span class="text-sm font-medium text-gray-900">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div x-show="verdict === 'found'" x-cloak>
                            <label class="text-xs text-gray-500 block mb-1">Перечень нарушений</label>
                            <textarea name="act_violations" rows="3"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('act_violations') }}</textarea>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Комментарий к акту</label>
                            <textarea name="result_comment" rows="2"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('result_comment') }}</textarea>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Файлы (служебные записки)</label>
                            <input type="file" name="files[]" multiple
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-gray-100 file:text-gray-700">
                        </div>
                        <button class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Отправить акт на приёмку</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Приёмка постановщиком --}}
        @if($isInitiator && $inspection->status === 'submitted')
            <div class="bg-white rounded-xl border-2 border-amber-200 p-5" x-data="{ returning: false }">
                <h2 class="font-semibold text-gray-900 mb-3">Приёмка акта</h2>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('inspections.accept', $inspection) }}">
                        @csrf
                        <button class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Принять акт</button>
                    </form>
                    <button @click="returning = !returning" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Вернуть на доработку</button>
                </div>
                <div x-show="returning" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                    <form method="POST" action="{{ route('inspections.return', $inspection) }}" class="space-y-2">
                        @csrf
                        <textarea name="return_comment" rows="2" required placeholder="Причина возврата…"
                                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                        <button class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600">Вернуть</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Порождение поручения по итогам (ТЗ 20.2) --}}
        @if($isInitiator && $inspection->isDone())
            <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{ spawning: false }">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">Поручение по итогам</h2>
                    <button @click="spawning = !spawning" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">Устранить выявленное</button>
                </div>
                <div x-show="spawning" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                    <form method="POST" action="{{ route('inspections.spawn-assignment', $inspection) }}" class="space-y-2">
                        @csrf
                        <input name="title" value="Устранить нарушения по проверке {{ $inspection->number }}" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <select name="executor_id" required
                                    class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— исполнитель —</option>
                                @foreach($executors as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="due_at"
                                   class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>
                        <textarea name="body" rows="2" placeholder="Что устранить…"
                                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                        <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Поставить поручение</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Запросы данных (подпроверки) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="{ adding: false }">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-900">Запросы данных ({{ $children->count() }})</h2>
                @if($canSubRequest)
                    <button @click="adding = !adding" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">+ Запрос данных</button>
                @endif
            </div>

            <div class="space-y-2">
                @forelse($children as $child)
                    <a href="{{ route('inspections.show', $child) }}" class="flex items-center gap-3 border border-gray-100 rounded-lg px-4 py-3 hover:bg-gray-50">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate">{{ $child->title }}</div>
                            <div class="text-xs text-gray-400">{{ $child->number }} · {{ $child->executor?->name }} @unless($child->is_mandatory) · необязательный @endunless</div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $child->statusColor() }}">{{ $child->statusLabel() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-400">Запросов данных нет.</p>
                @endforelse
            </div>

            @if($canSubRequest)
                <div x-show="adding" x-cloak class="mt-4 border-t border-gray-100 pt-4">
                    <form method="POST" action="{{ route('inspections.sub', $inspection) }}" class="space-y-2">
                        @csrf
                        <input name="title" placeholder="Предоставьте данные…" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <select name="executor_id" required
                                    class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                <option value="">— у кого запросить —</option>
                                @foreach($executors as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="due_at"
                                   class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        </div>
                        <label class="flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" name="is_mandatory" value="1" checked class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            Обязательный для закрытия проверки
                        </label>
                        <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Создать запрос</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Файлы --}}
        @if($inspection->files->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Файлы</h2>
                <div class="space-y-1">
                    @foreach($inspection->files as $f)
                        <a href="{{ route('inspections.file', $f) }}" class="flex items-center gap-2 text-sm text-[#5B4FE8] hover:underline">
                            📎 {{ $f->original_name }}
                            @if($f->isAggregated())<span class="text-xs text-gray-400">(из {{ $f->source?->number }})</span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- История --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-4">История</h2>
            <div class="space-y-2">
                @foreach($inspection->events as $ev)
                    <div class="flex gap-3 text-sm">
                        <span class="text-gray-400 text-xs shrink-0 w-28">{{ $ev->created_at->format('d.m.Y H:i') }}</span>
                        <div>
                            <span class="text-gray-700">{{ $ev->label() }}</span>
                            @if($ev->user)<span class="text-gray-400">— {{ $ev->user->name }}</span>@endif
                            @if($ev->comment)<p class="text-gray-500 text-xs mt-0.5">{{ $ev->comment }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
