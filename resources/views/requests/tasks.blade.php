<x-app-layout>
    <x-slot name="title">Мои задания — Vamin</x-slot>

    @include('requests.partials.nav', ['active' => 'tasks', 'title' => 'Мои задания'])

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">{{ session('success') }}</div>
    @endif

    <div class="space-y-3 max-w-3xl">
        @forelse($tasks as $task)
            <div class="bg-white border border-gray-200 rounded-xl p-5" x-data="{ res: false, chat: false }">
                {{-- Заголовок --}}
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $task->title }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $task->whoLabel() }} · командировка
                            <a href="{{ route('trips.show', $task->trip) }}" class="text-[#5B4FE8] hover:underline">T-{{ $task->trip_request_id }}</a>
                            · {{ $task->trip->user->name }}
                        </p>
                    </div>
                    <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                </div>

                {{-- Контекст родительской заявки --}}
                <div class="mt-3 rounded-lg bg-[#5B4FE8]/5 border border-[#5B4FE8]/10 px-4 py-3">
                    <p class="text-[11px] font-semibold text-[#5B4FE8] uppercase tracking-widest mb-2">Контекст родительской заявки</p>
                    <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                        @if($task->trip->city)
                            <dt class="text-gray-400">Город</dt><dd class="text-gray-800 font-medium">{{ $task->trip->city }}</dd>
                        @endif
                        <dt class="text-gray-400">Даты</dt>
                        <dd class="text-gray-800 font-medium">{{ $task->trip->date_start?->format('d.m.Y') }} — {{ $task->trip->date_end?->format('d.m.Y') }}</dd>
                        @if($task->trip->transport_type)
                            <dt class="text-gray-400">Транспорт</dt><dd class="text-gray-800 font-medium">{{ $task->trip->transport_type }}</dd>
                        @endif
                        @if($task->trip->purpose)
                            <dt class="text-gray-400">Цель</dt><dd class="text-gray-700">{{ $task->trip->purpose }}</dd>
                        @endif
                    </dl>
                </div>

                {{-- Результат + файлы --}}
                @if($task->result_comment)
                    <p class="text-sm text-gray-600 mt-3">{{ $task->result_comment }}</p>
                @endif
                @if($task->files->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($task->files as $f)
                            <a href="{{ route('trips.tasks.file', $f) }}" class="inline-flex items-center gap-1.5 text-xs text-[#5B4FE8] hover:underline">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                {{ $f->original_name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Действия --}}
                <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t border-gray-100">
                    @if($task->status === 'pending')
                        <form method="POST" action="{{ route('trips.tasks.take', $task) }}">@csrf
                            <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Взять в работу</button>
                        </form>
                    @endif
                    @if(! $task->isDone())
                        <button type="button" @click="res = !res" class="px-4 py-2 border border-emerald-200 text-emerald-700 rounded-lg text-sm hover:bg-emerald-50">Приложить результат</button>
                    @endif
                    <button type="button" @click="chat = !chat" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Задать вопрос инициатору</button>

                    @if($task->isDone())
                        <span class="text-xs text-gray-400 ml-auto">
                            Выполнено {{ $task->done_at?->format('d.m.Y H:i') }}@if($task->doneBy) · {{ $task->doneBy->name }}@endif
                        </span>
                    @endif
                </div>

                {{-- Панель результата --}}
                @if(! $task->isDone())
                    <div x-show="res" x-cloak class="mt-3">
                        <form method="POST" action="{{ route('trips.tasks.complete', $task) }}" enctype="multipart/form-data">@csrf
                            <textarea name="result_comment" rows="2" placeholder="Комментарий к результату…"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                            <input type="file" name="files[]" multiple class="mt-2 block text-sm text-gray-600">
                            <div class="flex justify-end mt-3">
                                <button class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Отметить выполненным</button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Тред вопросов инициатору --}}
                <div x-show="chat" x-cloak class="mt-3 pt-3 border-t border-gray-100">
                    @include('requests.partials.task-thread', ['task' => $task, 'placeholder' => 'Задать вопрос инициатору…'])
                </div>
            </div>
        @empty
            <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-16">
                Заданий нет.
            </div>
        @endforelse
    </div>
</x-app-layout>
