<x-app-layout>
    <x-slot name="title">Задачи по процедурам — Vamin</x-slot>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Задачи по процедурам</h1>
        <p class="text-sm text-gray-500 mb-6">Задачи, порождённые чек-листами процедур</p>

        @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif

        {{-- На приёмку (инициатор принимает или возвращает) --}}
        @if($reviewTasks->isNotEmpty())
            <h2 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                На приёмку
                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full bg-amber-500 text-white">{{ $reviewTasks->count() }}</span>
            </h2>
            <div class="space-y-3 mb-8">
                @foreach($reviewTasks as $task)
                    <div class="bg-white rounded-xl border border-amber-200 p-4" x-data="{ returning: false }">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-gray-900">{{ $task->title }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    Процедура: <a href="{{ route('procedures.show', $task->procedure) }}" class="text-[#5B4FE8] hover:underline">{{ $task->procedure->title }}</a>
                                    · исполнитель {{ $task->assignee?->name }}
                                    @if($task->submitted_at) · сдана {{ $task->submitted_at->format('d.m.Y H:i') }} @endif
                                </div>
                                @if($task->result_comment)<p class="text-sm text-gray-600 mt-1">Результат: {{ $task->result_comment }}</p>@endif
                                @foreach($task->files as $f)
                                    <a href="{{ route('procedures.tasks.file', $f) }}" class="inline-flex items-center gap-1 text-xs text-[#5B4FE8] hover:underline mt-1 mr-3">📎 {{ $f->original_name }}</a>
                                @endforeach
                            </div>
                            <div class="flex flex-col gap-1.5 shrink-0">
                                <form method="POST" action="{{ route('procedures.tasks.accept', $task) }}">
                                    @csrf
                                    <button class="w-full px-3 py-1.5 bg-green-600 text-black rounded-lg text-xs hover:bg-green-700">Принять</button>
                                </form>
                                <button @click="returning = !returning" class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs hover:bg-gray-50">Вернуть</button>
                            </div>
                        </div>
                        <div x-show="returning" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                            <form method="POST" action="{{ route('procedures.tasks.return', $task) }}" class="space-y-2">
                                @csrf
                                <textarea name="comment" rows="2" required placeholder="Причина возврата на доработку…"
                                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                                <button class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600">Вернуть на доработку</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Мои задачи (исполнитель) --}}
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Мои задачи</h2>
        <div class="space-y-3">
            @forelse($myTasks as $task)
                <div class="bg-white rounded-xl border border-gray-200 p-4"
                     x-data="{ submitting: false, extending: false }">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-gray-900">{{ $task->title }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                Процедура: <a href="{{ route('procedures.show', $task->procedure) }}" class="text-[#5B4FE8] hover:underline">{{ $task->procedure->title }}</a>
                                @if($task->due_at) · срок {{ $task->due_at->format('d.m.Y') }} @endif
                            </div>
                            @if($task->description)<p class="text-sm text-gray-600 mt-1">{{ $task->description }}</p>@endif
                            @if($task->status === 'returned' && $task->return_comment)
                                <p class="text-sm text-red-600 mt-1">Возвращено: {{ $task->return_comment }}</p>
                            @endif
                            @if($task->result_comment)<p class="text-sm text-gray-500 mt-1">Ваш результат: {{ $task->result_comment }}</p>@endif
                            @foreach($task->files as $f)
                                <a href="{{ route('procedures.tasks.file', $f) }}" class="inline-flex items-center gap-1 text-xs text-[#5B4FE8] hover:underline mt-1 mr-3">📎 {{ $f->original_name }}</a>
                            @endforeach
                        </div>
                        @if(! in_array($task->status, ['submitted', 'done'], true))
                            <div class="flex flex-col gap-1.5 shrink-0">
                                @if($task->status === 'pending')
                                    <form method="POST" action="{{ route('procedures.tasks.take', $task) }}">
                                        @csrf
                                        <button class="w-full px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs hover:bg-gray-200">Взять в работу</button>
                                    </form>
                                @endif
                                <button @click="submitting = !submitting" class="px-3 py-1.5 bg-[#5B4FE8] text-white rounded-lg text-xs hover:bg-indigo-700">Сдать на приёмку</button>
                                <button @click="extending = !extending" class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded-lg text-xs hover:bg-gray-50">Перенести срок</button>
                            </div>
                        @elseif($task->status === 'submitted')
                            <span class="text-xs text-amber-600 shrink-0">Ждёт приёмки инициатора</span>
                        @endif
                    </div>

                    {{-- Сдача на приёмку --}}
                    <div x-show="submitting" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                        <form method="POST" action="{{ route('procedures.tasks.submit', $task) }}" enctype="multipart/form-data" class="space-y-2">
                            @csrf
                            <textarea name="result_comment" rows="2" placeholder="Комментарий к результату…"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                            <input type="file" name="files[]" multiple
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 file:mr-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-gray-100 file:text-gray-700">
                            <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сдать на приёмку</button>
                        </form>
                    </div>

                    {{-- Перенос срока (обязательный комментарий, ТЗ 19.3) --}}
                    <div x-show="extending" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                        <form method="POST" action="{{ route('procedures.tasks.deadline', $task) }}" class="space-y-2">
                            @csrf
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Новый срок</label>
                                <input type="date" name="due_at" required
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Причина переноса (обязательно)</label>
                                <textarea name="comment" rows="2" required
                                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                            </div>
                            <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Перенести и уведомить инициатора</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400">Задач нет.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
