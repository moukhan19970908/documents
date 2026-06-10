<x-app-layout>
    <x-slot name="title">{{ $chat->document->title ?? 'Чат' }} — Vamin</x-slot>

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('chats.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $chat->document->title ?? 'Документ #' . $chat->document_id }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Чат по документу ·
                <a href="{{ route('documents.show', $chat->document_id) }}" class="text-[#5B4FE8] hover:underline">Перейти к документу</a>
            </p>
        </div>
    </div>

    <div x-data="chatWidget({
                 chatId: {{ $chat->id }},
                 currentUserId: {{ auth()->id() }},
                 initialMessages: {{ Js::from($chat->messages->map(fn($m) => ['id' => $m->id, 'body' => $m->body, 'created_at' => $m->created_at->toISOString(), 'user' => ['id' => $m->user->id, 'name' => $m->user->name]])) }},
                 messagesUrl: '{{ route('chats.messages', $chat) }}',
                 sendUrl: '{{ route('chats.messages.store', $chat) }}'
             })"
         @destroy.window="destroy()"
         class="flex flex-col bg-white rounded-xl border border-gray-200 overflow-hidden"
         style="height: calc(100vh - 220px); min-height: 400px">

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto px-5 py-4 flex flex-col-reverse gap-3"
             x-ref="msgContainer">

            <template x-for="msg in messages" :key="msg.id">
                <div class="flex gap-3" :class="msg.user.id === currentUserId ? 'flex-row-reverse' : ''">
                    <div class="w-8 h-8 rounded-full bg-[#5B4FE8] text-white flex items-center justify-center text-xs font-semibold shrink-0 mt-0.5"
                         x-text="msg.user.name.charAt(0).toUpperCase()"></div>
                    <div class="max-w-lg" :class="msg.user.id === currentUserId ? 'items-end' : 'items-start'" style="display:flex;flex-direction:column">
                        <div class="flex items-center gap-2 mb-1"
                             :class="msg.user.id === currentUserId ? 'flex-row-reverse' : ''">
                            <span class="text-xs font-medium text-gray-700" x-text="msg.user.name"></span>
                            <span class="text-xs text-gray-400"
                                  x-text="new Date(msg.created_at).toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' })"></span>
                        </div>
                        <div class="px-3 py-2 rounded-xl text-sm leading-relaxed break-words"
                             :class="msg.user.id === currentUserId
                                ? 'bg-[#5B4FE8] text-white rounded-tr-none'
                                : 'bg-gray-100 text-gray-800 rounded-tl-none'"
                             x-text="msg.body"></div>
                    </div>
                </div>
            </template>

            <div class="text-center">
                <button x-show="nextCursor" @click="loadMore()" :disabled="loading"
                        class="text-xs text-[#5B4FE8] hover:underline disabled:opacity-40">
                    Загрузить предыдущие
                </button>
            </div>
        </div>

        {{-- Input --}}
        <div class="shrink-0 border-t border-gray-100 px-4 py-3 flex gap-3 items-end bg-gray-50">
            <textarea x-model="body"
                      @keydown="handleKey($event)"
                      placeholder="Написать сообщение… (Enter — отправить, Shift+Enter — новая строка)"
                      rows="2"
                      class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white"></textarea>
            <button @click="sendMessage()" :disabled="!body.trim() || sending"
                    class="px-4 py-2 text-sm bg-[#5B4FE8] text-white rounded-xl font-medium hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed shrink-0">
                Отправить
            </button>
        </div>
    </div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('chatWidget', ({ chatId, currentUserId, initialMessages, messagesUrl, sendUrl }) => ({
        messages: initialMessages ?? [],
        currentUserId: currentUserId,
        body: '',
        nextCursor: null,
        loading: false,
        sending: false,
        channel: null,

        init() {
            this.subscribeToChannel();
        },

        async loadMore() {
            if (!this.nextCursor || this.loading) return;
            this.loading = true;
            const res = await fetch(messagesUrl + '?cursor=' + this.nextCursor, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            this.messages = [...this.messages, ...json.data];
            this.nextCursor = json.next_cursor;
            this.loading = false;
        },

        subscribeToChannel() {
            if (!window.Echo) return;
            this.channel = window.Echo
                .private(`chat.${chatId}`)
                .listen('MessageSent', (e) => {
                    if (!this.messages.some(m => m.id === e.id)) {
                        this.messages = [e, ...this.messages];
                    }
                });
        },

        async sendMessage() {
            if (!this.body.trim() || this.sending) return;
            this.sending = true;
            const text = this.body;
            this.body = '';
            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Socket-ID': window.Echo?.socketId() ?? '',
                },
                body: JSON.stringify({ body: text }),
            });
            if (res.ok) {
                const msg = await res.json();
                this.messages = [msg, ...this.messages];
            } else {
                this.body = text;
            }
            this.sending = false;
        },

        handleKey(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        },

        destroy() {
            this.channel?.stopListening('MessageSent');
            window.Echo?.leave(`chat.${chatId}`);
        },
    }));
});
</script>
</x-app-layout>
