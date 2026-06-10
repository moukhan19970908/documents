<x-app-layout>
    <x-slot name="title">Чаты — Vamin</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Чаты</h1>
        <p class="text-sm text-gray-500 mt-1">Переписки по документам, в которых вы участвуете</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @forelse($chats as $chat)
            @php $lastMsg = $chat->messages->first(); @endphp
            <a href="{{ route('chats.show', $chat) }}"
               class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 rounded-full bg-[#5B4FE8] text-white flex items-center justify-center font-semibold text-sm shrink-0">
                    {{ mb_substr($chat->document->title ?? '?', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-gray-900 text-sm truncate">{{ $chat->document->title ?? 'Документ #' . $chat->document_id }}</span>
                        @if($lastMsg)
                            <span class="text-xs text-gray-400 shrink-0">{{ $lastMsg->created_at->diffForHumans() }}</span>
                        @endif
                    </div>
                    @if($lastMsg)
                        <p class="text-sm text-gray-500 truncate mt-0.5">
                            <span class="font-medium text-gray-600">{{ $lastMsg->user->name }}:</span>
                            {{ $lastMsg->body }}
                        </p>
                    @else
                        <p class="text-sm text-gray-400 mt-0.5">Нет сообщений</p>
                    @endif
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        @empty
            <div class="px-6 py-12 text-center text-gray-400 text-sm">Нет активных чатов</div>
        @endforelse
    </div>
</x-app-layout>
