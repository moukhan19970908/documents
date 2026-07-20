<x-app-layout>
    <x-slot name="title">{{ $procedure->title }} — Процедура</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Заголовок --}}
        <div>
            <a href="{{ route('procedures.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← Процедуры</a>
            <div class="flex items-start justify-between mt-1">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $procedure->title }}</h1>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $procedure->statusColor() }}">{{ $procedure->statusLabel() }}</span>
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        <span class="font-mono">{{ $procedure->number }}</span> · {{ $procedure->template?->name }} · инициатор {{ $procedure->initiator?->name }}
                    </div>
                </div>
                @if($procedure->initiator_id === auth()->id() || auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('procedures.destroy', $procedure) }}" onsubmit="return confirm('Удалить процедуру?')">
                        @csrf @method('DELETE')
                        <button class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs hover:bg-red-50">Удалить</button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))<div class="px-4 py-3 rounded-lg bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif
        @if($procedure->status === 'stopped')
            <div class="px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">
                <strong>Процедура остановлена.</strong> {{ $procedure->stopped_reason }}
            </div>
        @endif

        {{-- Данные формы --}}
        @if(!empty($procedure->data['form']))
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-2">Данные</h2>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $procedure->data['form'] }}</p>
            </div>
        @endif

        {{-- Панель действия по активному этапу --}}
        @if($active && $canAct)
            <div class="bg-white rounded-xl border-2 border-[#5B4FE8]/30 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Ваш этап</span>
                    <h2 class="font-semibold text-gray-900">{{ $active->title }}</h2>
                </div>

                @includeWhen(in_array($active->type, ['form','approval']), 'procedures._act-stage')
                @includeWhen($active->type === 'branch', 'procedures._act-branch')
                @includeWhen($active->type === 'checklist', 'procedures._act-checklist')
            </div>
        @elseif($active)
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-sm text-gray-500">
                Текущий этап «{{ $active->title }}» ждёт действия: {{ $active->executor?->name ?? 'исполнитель не назначен' }}.
            </div>
        @endif

        {{-- Таймлайн этапов --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Этапы</h2>
            <div class="space-y-2">
                @foreach($procedure->runs as $run)
                    <div class="flex gap-3 items-start p-3 rounded-lg {{ $run->status === 'active' ? 'bg-indigo-50/50' : '' }}">
                        <span class="w-6 h-6 shrink-0 rounded-full bg-gray-100 text-gray-500 text-xs flex items-center justify-center font-semibold">{{ $loop->iteration }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium text-gray-900">{{ $run->title }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $run->statusColor() }}">{{ $run->statusLabel() }}</span>
                                @if($run->verdict)
                                    <span class="text-xs {{ $run->verdict === 'positive' ? 'text-green-600' : 'text-red-600' }}">{{ $run->verdict === 'positive' ? '✓ положительно' : '✗ негативно' }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                @if($run->executor) {{ $run->executor->name }} @endif
                                @if($run->acted_at) · {{ $run->acted_at->format('d.m.Y H:i') }} @endif
                            </div>
                            @if($run->comment)<p class="text-sm text-gray-600 mt-1">{{ $run->comment }}</p>@endif
                            @foreach($run->files as $f)
                                <a href="{{ route('procedures.file', $f) }}" class="inline-flex items-center gap-1 text-xs text-[#5B4FE8] hover:underline mt-1 mr-3">📎 {{ $f->original_name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Чек-лист (заполненный) --}}
        @if($procedure->checklistEntries->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Чек-лист</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-50">
                        @foreach($procedure->checklistEntries as $e)
                            <tr>
                                <td class="py-2 pr-3">
                                    @if($e->department)<span class="text-gray-400">{{ $e->department }}:</span> @endif{{ $e->title }}
                                    @if($e->source === 'custom')<span class="ml-1 text-xs text-indigo-500">(добавлено)</span>@endif
                                </td>
                                <td class="py-2 px-3 font-medium {{ $e->isActive() ? 'text-gray-900' : 'text-gray-400' }}">{{ $e->answerLabel() }}</td>
                                <td class="py-2 pl-3 text-right text-xs text-gray-400">{{ $e->executor?->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Задачи --}}
        @if($procedure->tasks->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4">Порождённые задачи ({{ $procedure->tasks->where('status','done')->count() }}/{{ $procedure->tasks->count() }})</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-50">
                        @foreach($procedure->tasks as $task)
                            <tr>
                                <td class="py-2 pr-3">{{ $task->title }}</td>
                                <td class="py-2 px-3 text-gray-500">{{ $task->assignee?->name }}</td>
                                <td class="py-2 px-3 text-gray-500">{{ $task->due_at?->format('d.m.Y') }}</td>
                                <td class="py-2 pl-3 text-right"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- События --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-4">История</h2>
            <div class="space-y-2">
                @foreach($procedure->events as $ev)
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
