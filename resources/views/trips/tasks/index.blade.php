<x-app-layout>
    <x-slot name="title">Задания командировок — Vamin</x-slot>

    <div class="max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('requests.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← Заявки</a>
                <h1 class="text-2xl font-bold text-gray-900 mt-1">Задания командировок</h1>
                <p class="text-sm text-gray-500">Назначенные вам задания по командировкам сотрудников.</p>
            </div>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.trip-tasks.settings') }}" class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Исполнители</a>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <div class="space-y-3">
            @forelse($tasks as $task)
                <div class="bg-white border border-gray-200 rounded-xl p-5" x-data="{ open: false }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $task->title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $task->whoLabel() }} · командировка
                                <a href="{{ route('trips.show', $task->trip) }}" class="text-[#5B4FE8] hover:underline">T-{{ $task->trip_request_id }}</a>
                                · {{ $task->trip->user->name }}@if($task->trip->city) · {{ $task->trip->city }}@endif
                            </p>
                        </div>
                        <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                    </div>

                    @if($task->result_comment)
                        <p class="text-sm text-gray-600 mt-2">{{ $task->result_comment }}</p>
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

                    @if(! $task->isDone())
                        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-100">
                            @if($task->status === 'pending')
                                <form method="POST" action="{{ route('trips.tasks.take', $task) }}">@csrf
                                    <button class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Взять в работу</button>
                                </form>
                            @endif
                            <button type="button" @click="open = !open" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Приложить результат</button>
                        </div>

                        <div x-show="open" x-cloak class="mt-3">
                            <form method="POST" action="{{ route('trips.tasks.complete', $task) }}" enctype="multipart/form-data">@csrf
                                <textarea name="result_comment" rows="2" placeholder="Комментарий к результату…"
                                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"></textarea>
                                <input type="file" name="files[]" multiple class="mt-2 block text-sm text-gray-600">
                                <div class="flex justify-end mt-3">
                                    <button class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Выполнено</button>
                                </div>
                            </form>
                        </div>
                    @else
                        <p class="text-xs text-gray-400 mt-3 pt-3 border-t border-gray-100">
                            Выполнено {{ $task->done_at?->format('d.m.Y H:i') }}@if($task->doneBy) · {{ $task->doneBy->name }}@endif
                        </p>
                    @endif
                </div>
            @empty
                <div class="text-center text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl py-16">
                    Заданий нет.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
