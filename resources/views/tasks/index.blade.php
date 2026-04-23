<x-app-layout>
    <x-slot name="title">Задачи — Vamin</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Мои задачи</h1>
            <p class="text-sm text-gray-500 mt-1">Документы, ожидающие вашего действия</p>
        </div>
        <span class="bg-[#5B4FE8] text-white text-sm font-bold px-3 py-1.5 rounded-full">{{ $tasks->total() }}</span>
    </div>

    {{-- Filter tabs --}}
    <div class="flex gap-1 mb-4 bg-white rounded-xl border border-gray-200 p-1.5">
        @foreach(['all' => 'Все', 'pending' => 'Ожидают', 'overdue' => 'Просрочены', 'completed' => 'Завершены'] as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['filter' => $key]) }}"
               class="flex-1 text-center text-sm py-2 rounded-lg font-medium transition-colors
                      {{ $filter === $key ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Tasks list --}}
    <div class="space-y-3">
        @forelse($tasks as $task)
            @php
                $isOverdue = $task->deadline && now()->gt($task->deadline) && $task->status !== 'approved';
                $priorityColor = $isOverdue ? 'border-l-red-400' : 'border-l-indigo-400';
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $priorityColor }} p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            @if($task->type)
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $task->type->name }}</span>
                            @endif
                            @if($isOverdue)
                                <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded font-semibold">Просрочено</span>
                            @endif
                        </div>
                        <a href="{{ route('documents.show', $task) }}" class="font-semibold text-gray-900 hover:text-[#5B4FE8] block truncate">
                            {{ $task->title }}
                        </a>
                        <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <img src="{{ $task->initiator->avatar_url }}" class="w-4 h-4 rounded-full" alt="">
                                {{ $task->initiator->name }}
                            </span>
                            @if($task->deadline)
                                <span class="flex items-center gap-1 {{ $isOverdue ? 'text-red-500 font-semibold' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    {{ $task->deadline->format('d.m.Y') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('documents.show', $task) }}"
                       class="shrink-0 bg-[#5B4FE8] text-white text-xs font-medium px-3 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                        Рассмотреть
                    </a>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="text-gray-600 font-medium">Нет активных задач</p>
                <p class="text-gray-400 text-sm mt-1">Все документы обработаны</p>
            </div>
        @endforelse
    </div>

    @if($tasks->hasPages())
        <div class="mt-4">
            {{ $tasks->links() }}
        </div>
    @endif
</x-app-layout>
