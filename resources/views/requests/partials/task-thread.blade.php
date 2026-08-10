{{-- Тред задания: вопросы инициатору и ответы. $task — TripTask (с messages.user). --}}
<div>
    @if($task->messages->isNotEmpty())
        <div class="space-y-2.5 mb-3">
            @foreach($task->messages as $m)
                @php
                    $role = $m->user_id === $task->trip->user_id ? 'инициатор'
                          : ($m->user_id === $task->assignee_id ? 'исполнитель' : null);
                @endphp
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-[11px] font-bold shrink-0">
                        {{ mb_substr($m->user?->name ?? '?', 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs">
                            <span class="font-medium text-gray-800">{{ $m->user?->name }}</span>
                            @if($role)<span class="text-gray-400">· {{ $role }}</span>@endif
                            <span class="text-gray-300">· {{ $m->created_at->format('d.m H:i') }}</span>
                        </p>
                        <p class="text-sm text-gray-700">{{ $m->body }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('trips.tasks.ask', $task) }}" class="flex items-start gap-2">
        @csrf
        <textarea name="body" rows="2" required placeholder="{{ $placeholder ?? 'Написать сообщение…' }}"
                  class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]/30 focus:border-[#5B4FE8]"></textarea>
        <button type="submit" class="px-3.5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition shrink-0">Отправить</button>
    </form>
</div>
