<x-app-layout>
    <x-slot name="title">Чаты — Vamin</x-slot>

    @php
        $userId = auth()->id();
        $totalUnread = $chats->sum('unread_count');
        $unreadChats = $chats->where('unread_count', '>', 0)->values();
    @endphp

    {{-- Full-height two-panel layout, breaks out of the main p-4 padding --}}
    <div class="-m-4 md:-m-6 flex bg-white border border-gray-200 rounded-none md:rounded-xl overflow-hidden"
         style="height: calc(100vh - 64px)">

        {{-- ═══ LEFT PANEL: Chat list ═══ --}}
        <div class="w-80 shrink-0 border-r border-gray-100 flex flex-col h-full {{ $activeChat ? 'hidden md:flex' : 'flex' }}">

            {{-- Header --}}
            <div class="px-5 pt-5 pb-3 border-b border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Чаты</h1>
                        <p class="text-xs text-gray-400 mt-0.5">Переписки по документам</p>
                    </div>
                    @if($totalUnread > 0)
                        <span class="bg-[#5B4FE8] text-white text-xs font-semibold px-2 py-0.5 rounded-full">{{ $totalUnread }}</span>
                    @endif
                </div>
                {{-- Search --}}
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="chatSearch" placeholder="Поиск по чатам..." autocomplete="off"
                           class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] focus:bg-white">
                </div>
            </div>

            {{-- Tabs --}}
            <div x-data="{ tab: '{{ $totalUnread > 0 ? 'all' : 'all' }}' }" class="flex flex-col flex-1 overflow-hidden">
                <div class="flex border-b border-gray-100 px-4 pt-2">
                    <button @click="tab='all'"
                            :class="tab==='all' ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="text-sm font-medium pb-2 px-1 mr-4 border-b-2 transition-colors">
                        Все
                        @if($chats->count() > 0)
                            <span class="ml-1 text-xs bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5">{{ $chats->count() }}</span>
                        @endif
                    </button>
                    <button @click="tab='unread'"
                            :class="tab==='unread' ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="text-sm font-medium pb-2 px-1 border-b-2 transition-colors">
                        Непрочитанные
                        @if($totalUnread > 0)
                            <span class="ml-1 text-xs bg-red-100 text-red-600 rounded-full px-1.5 py-0.5">{{ $totalUnread }}</span>
                        @endif
                    </button>
                </div>

                {{-- Chat list --}}
                <div class="flex-1 overflow-y-auto" id="chatListContainer">

                    {{-- ALL TAB --}}
                    <div x-show="tab==='all'">
                        @forelse($chats as $chat)
                            @php $lastMsg = $chat->messages->first(); @endphp
                            <a href="{{ route('chats.show', $chat) }}"
                               data-chat-title="{{ mb_strtolower($chat->document->title ?? '') }}"
                               class="chat-item flex items-center gap-3 px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors cursor-pointer
                                      {{ isset($activeChat) && $activeChat->id === $chat->id ? 'bg-indigo-50 border-l-2 border-l-[#5B4FE8]' : '' }}">
                                @php
                                    $docStatus = $chat->document->status ?? 'draft';
                                    $statusColor = match($docStatus) {
                                        'in_review'        => '#3B82F6',
                                        'requires_changes' => '#F97316',
                                        'approved'         => '#22C55E',
                                        'rejected'         => '#EF4444',
                                        default            => '#9CA3AF',
                                    };
                                    $statusBg = match($docStatus) {
                                        'in_review'        => '#EFF6FF',
                                        'requires_changes' => '#FFF7ED',
                                        'approved'         => '#F0FDF4',
                                        'rejected'         => '#FEF2F2',
                                        default            => '#F9FAFB',
                                    };
                                @endphp
                                <div style="width:40px;height:40px;border-radius:10px;background:{{ $statusBg }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px" fill="none" viewBox="0 0 24 24" stroke="{{ $statusColor }}" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-sm font-semibold text-gray-900 truncate {{ $chat->unread_count > 0 ? '' : 'font-medium' }}">
                                            {{ $chat->document->title ?? 'Документ #' . $chat->document_id }}
                                        </span>
                                        <span class="text-[11px] text-gray-400 shrink-0">
                                            {{ $lastMsg ? $lastMsg->created_at->format('H:i') : '' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-1 mt-0.5">
                                        <p class="text-xs text-gray-500 truncate">
                                            @if($lastMsg)
                                                <span class="font-medium text-gray-600">{{ $lastMsg->user->name }}:</span>
                                                {{ Str::limit($lastMsg->body, 40) }}
                                            @else
                                                <span class="text-gray-400">Нет сообщений</span>
                                            @endif
                                        </p>
                                        @if($chat->unread_count > 0)
                                            <span class="shrink-0 w-5 h-5 bg-[#5B4FE8] text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                                                {{ $chat->unread_count > 99 ? '99+' : $chat->unread_count }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="px-5 py-10 text-center text-gray-400 text-sm">Нет активных чатов</div>
                        @endforelse
                    </div>

                    {{-- UNREAD TAB --}}
                    <div x-show="tab==='unread'" style="display:none">
                        @forelse($unreadChats as $chat)
                            @php $lastMsg = $chat->messages->first(); @endphp
                            <a href="{{ route('chats.show', $chat) }}"
                               class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors cursor-pointer
                                      {{ isset($activeChat) && $activeChat->id === $chat->id ? 'bg-indigo-50 border-l-2 border-l-[#5B4FE8]' : '' }}">
                                @php
                                    $docStatus = $chat->document->status ?? 'draft';
                                    $statusColor = match($docStatus) {
                                        'in_review'        => '#3B82F6',
                                        'requires_changes' => '#F97316',
                                        'approved'         => '#22C55E',
                                        'rejected'         => '#EF4444',
                                        default            => '#9CA3AF',
                                    };
                                    $statusBg = match($docStatus) {
                                        'in_review'        => '#EFF6FF',
                                        'requires_changes' => '#FFF7ED',
                                        'approved'         => '#F0FDF4',
                                        'rejected'         => '#FEF2F2',
                                        default            => '#F9FAFB',
                                    };
                                @endphp
                                <div style="width:40px;height:40px;border-radius:10px;background:{{ $statusBg }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px" fill="none" viewBox="0 0 24 24" stroke="{{ $statusColor }}" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-sm font-semibold text-gray-900 truncate">
                                            {{ $chat->document->title ?? 'Документ #' . $chat->document_id }}
                                        </span>
                                        <span class="text-[11px] text-gray-400 shrink-0">
                                            {{ $lastMsg ? $lastMsg->created_at->format('H:i') : '' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-1 mt-0.5">
                                        <p class="text-xs text-gray-500 truncate">
                                            @if($lastMsg)
                                                <span class="font-medium text-gray-600">{{ $lastMsg->user->name }}:</span>
                                                {{ Str::limit($lastMsg->body, 40) }}
                                            @endif
                                        </p>
                                        <span class="shrink-0 w-5 h-5 bg-[#5B4FE8] text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                                            {{ $chat->unread_count > 99 ? '99+' : $chat->unread_count }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="px-5 py-10 text-center text-gray-400 text-sm">Нет непрочитанных сообщений</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ RIGHT PANEL: Active chat or empty state ═══ --}}
        <div x-data="{ rightTab: 'chat' }" class="flex-1 flex flex-col h-full min-w-0 relative">
            @if(isset($activeChat) && $activeChat)
                @php
                    $doc = $activeChat->document;
                    $participants = $activeChat->participants;
                    $messages = $activeChat->messages;
                @endphp

                {{-- Chat header --}}
                <div class="shrink-0 px-5 py-4 border-b border-gray-100">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            {{-- Back button (mobile) --}}
                            <a href="{{ route('chats.index') }}" class="md:hidden text-gray-400 hover:text-gray-600 shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </a>
                            <div class="w-10 h-10 rounded-full bg-[#5B4FE8] text-white flex items-center justify-center font-bold text-sm shrink-0">
                                {{ mb_strtoupper(mb_substr($doc->title ?? '?', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <h2 class="font-semibold text-gray-900 text-sm truncate">{{ $doc->title ?? 'Документ #' . $activeChat->document_id }}</h2>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    @if($doc->type) {{ $doc->type->name }} · @endif
                                    Инициатор: {{ $doc->initiator->name ?? '—' }}
                                    @if($doc->created_at) · {{ $doc->created_at->format('d.m.Y') }} @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('documents.show', $activeChat->document_id) }}"
                               class="text-xs text-[#5B4FE8] hover:underline whitespace-nowrap">Перейти к документу</a>
                        </div>
                    </div>

                    {{-- Tabs --}}
                    <div class="flex gap-4 mt-3 border-b border-gray-100 -mb-4">
                        <button @click="rightTab='chat'"
                                :class="rightTab==='chat' ? 'text-[#5B4FE8] border-b-2 border-[#5B4FE8]' : 'text-gray-400 border-b-2 border-transparent hover:text-gray-600'"
                                class="text-sm font-medium pb-2 px-0.5 transition-colors">Чат</button>
                        <button @click="rightTab='members'"
                                :class="rightTab==='members' ? 'text-[#5B4FE8] border-b-2 border-[#5B4FE8]' : 'text-gray-400 border-b-2 border-transparent hover:text-gray-600'"
                                class="text-sm font-medium pb-2 px-0.5 transition-colors">
                            Участники
                            <span class="ml-1 text-xs bg-gray-100 text-gray-500 rounded-full px-1.5">{{ $participants->count() }}</span>
                        </button>
                    </div>
                </div>

                {{-- Participants panel --}}
                <div x-show="rightTab==='members'"
                     class="flex-1 overflow-y-auto px-5 py-4"
                     style="display:none">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Участники чата</p>
                    @foreach($participants as $p)
                        <div class="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
                            <div class="w-8 h-8 rounded-full bg-[#5B4FE8] text-white flex items-center justify-center text-xs font-semibold shrink-0">
                                {{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $p->name }}</p>
                                @if($p->department)
                                    <p class="text-xs text-gray-400">{{ $p->department->name ?? '' }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Messages area --}}
                <div x-show="rightTab==='chat'" class="flex-1 flex flex-col overflow-hidden">
                <div x-data="chatWidget({
                             chatId: {{ $activeChat->id }},
                             currentUserId: {{ $userId }},
                             initialMessages: {{ Js::from($messages->map(fn($m) => [
                                 'id'            => $m->id,
                                 'body'          => $m->body,
                                 'created_at'    => $m->created_at->toISOString(),
                                 'user'          => ['id' => $m->user->id, 'name' => $m->user->name],
                                 'read_by_count' => $m->reads->where('user_id', '!=', $m->user_id)->count(),
                             ])) }},
                             firstUnreadId: {{ $firstUnreadId ?? 'null' }},
                             messagesUrl: '{{ route('chats.messages', $activeChat) }}',
                             sendUrl: '{{ route('chats.messages.store', $activeChat) }}',
                             markReadUrl: '{{ route('chats.read', $activeChat) }}'
                         })"
                     @destroy.window="destroy()"
                     class="flex-1 flex flex-col overflow-hidden">

                    {{-- Message list --}}
                    <div class="flex-1 overflow-y-auto"
                         style="background:#efeae2"
                         x-ref="msgContainer">
                        <div style="display:flex;flex-direction:column;min-height:100%;padding:12px">
                        <div style="flex:1"></div>

                        <template x-for="(msg, index) in messages" :key="msg.id">
                            <div>
                                {{-- Date separator --}}
                                <template x-if="showDateSep(index)">
                                    <div style="display:flex;justify-content:center;margin:12px 0 8px">
                                        <span style="background:rgba(255,255,255,0.85);font-size:12px;color:#667781;padding:4px 12px;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.08)"
                                              x-text="formatDate(msg.created_at)"></span>
                                    </div>
                                </template>

                                {{-- Unread separator --}}
                                <template x-if="firstUnreadId !== null && msg.id === firstUnreadId">
                                    <div style="display:flex;justify-content:center;margin:8px 0">
                                        <span style="background:rgba(209,236,241,0.95);font-size:12px;color:#31708f;padding:4px 14px;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.08);font-weight:500">Непрочитанные сообщения</span>
                                    </div>
                                </template>

                                {{-- OWN message (right) --}}
                                <template x-if="msg.user.id == currentUserId">
                                    <div style="display:flex;justify-content:flex-end;margin-bottom:3px;padding-left:64px">
                                        <div style="background:#d9fdd3;border-radius:8px 8px 0 8px;padding:6px 10px 4px 10px;box-shadow:0 1px 2px rgba(0,0,0,.1);max-width:65%;min-width:80px">
                                            <p style="font-size:14px;color:#1f2937;line-height:1.5;word-break:break-word;white-space:pre-wrap;margin:0 0 2px 0" x-text="msg.body"></p>
                                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:3px">
                                                <span style="font-size:11px;color:#667781" x-text="new Date(msg.created_at).toLocaleTimeString('ru',{hour:'2-digit',minute:'2-digit'})"></span>
                                                <template x-if="msg.read_by_count > 0">
                                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:15px;height:15px;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="#53bdeb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 12.5l5 5L16.5 6"/><path d="M7 12.5l5 5L21.5 6" opacity="0.6"/></svg>
                                                </template>
                                                <template x-if="msg.read_by_count === 0">
                                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:15px;height:15px;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="#667781" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- OTHER message (left) --}}
                                <template x-if="msg.user.id != currentUserId">
                                    <div style="display:flex;justify-content:flex-start;margin-bottom:3px;padding-right:64px">
                                        <div style="background:#ffffff;border-radius:8px 8px 8px 0;padding:6px 10px 4px 10px;box-shadow:0 1px 2px rgba(0,0,0,.1);max-width:65%;min-width:80px">
                                            <p style="font-size:12px;font-weight:600;color:#5B4FE8;margin:0 0 3px 0" x-text="msg.user.name"></p>
                                            <p style="font-size:14px;color:#1f2937;line-height:1.5;word-break:break-word;white-space:pre-wrap;margin:0 0 2px 0" x-text="msg.body"></p>
                                            <div style="display:flex;justify-content:flex-end">
                                                <span style="font-size:11px;color:#667781" x-text="new Date(msg.created_at).toLocaleTimeString('ru',{hour:'2-digit',minute:'2-digit'})"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        @if($messages->isEmpty())
                            <div style="display:flex;align-items:center;justify-content:center;padding:48px 0">
                                <span style="background:rgba(255,255,255,0.8);font-size:13px;color:#667781;padding:10px 20px;border-radius:8px">Начните переписку — напишите первое сообщение</span>
                            </div>
                        @endif
                        </div>
                    </div>

                    {{-- Input --}}
                    <div style="flex-shrink:0;display:flex;gap:8px;align-items:flex-end;padding:8px 12px;background:#f0f2f5">
                        <textarea x-model="body"
                                  @keydown="handleKey($event)"
                                  placeholder="Написать сообщение…"
                                  rows="1"
                                  x-ref="textarea"
                                  @input="autoResize($el)"
                                  style="flex:1;font-size:14px;background:#fff;border:none;border-radius:20px;padding:10px 16px;resize:none;outline:none;max-height:128px;color:#1f2937;box-shadow:0 1px 2px rgba(0,0,0,.1);font-family:inherit"></textarea>
                        <button @click="sendMessage()" :disabled="!body.trim() || sending"
                                style="width:44px;height:44px;background:#5B4FE8;border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:opacity 0.15s"
                                :style="!body.trim() || sending ? 'opacity:0.45;cursor:not-allowed' : 'opacity:1'">
                            <svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;color:#fff" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        </button>
                    </div>
                </div>
                </div>{{-- /x-show rightTab=chat wrapper --}}

            @else
                {{-- Empty state --}}
                <div class="flex-1 flex flex-col items-center justify-center gap-4 text-center px-8">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <div>
                        <p class="text-gray-500 font-medium">Выберите чат</p>
                        <p class="text-sm text-gray-400 mt-1">Выберите переписку из списка слева</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

<script>
// Chat list search
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('chatSearch');
    if (!input) return;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        document.querySelectorAll('.chat-item').forEach(el => {
            el.style.display = el.dataset.chatTitle?.includes(q) ? '' : 'none';
        });
    });
});

document.addEventListener('alpine:init', () => {
    Alpine.data('chatWidget', ({ chatId, currentUserId, initialMessages, firstUnreadId, messagesUrl, sendUrl, markReadUrl }) => ({
        messages: (initialMessages ?? []).slice().sort((a, b) => new Date(a.created_at) - new Date(b.created_at)),
        currentUserId: currentUserId,
        firstUnreadId: firstUnreadId,
        body: '',
        sending: false,
        channel: null,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        init() {
            this.$nextTick(() => {
                this.scrollToFirstUnread();
                this.subscribeToChannel();
                this.markRead();
            });
        },

        scrollToFirstUnread() {
            const container = this.$refs.msgContainer;
            if (!container) return;
            if (this.firstUnreadId) {
                // Scroll to unread separator
                this.$nextTick(() => {
                    const els = container.querySelectorAll('[x-data]');
                    container.scrollTop = container.scrollHeight;
                });
            } else {
                container.scrollTop = container.scrollHeight;
            }
        },

        scrollToBottom() {
            const c = this.$refs.msgContainer;
            if (c) c.scrollTop = c.scrollHeight;
        },

        showDateSep(index) {
            if (index === 0) return true;
            const curr = this.messages[index];
            const prev = this.messages[index - 1];
            return new Date(curr.created_at).toDateString() !== new Date(prev.created_at).toDateString();
        },

        formatDate(iso) {
            const d = new Date(iso);
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            if (d.toDateString() === today.toDateString()) return 'Сегодня';
            if (d.toDateString() === yesterday.toDateString()) return 'Вчера';
            return d.toLocaleDateString('ru', { day: 'numeric', month: 'long', year: 'numeric' });
        },

        subscribeToChannel() {
            if (!window.Echo) return;
            this.channel = window.Echo
                .private(`chat.${chatId}`)
                .listen('MessageSent', (e) => {
                    if (!this.messages.some(m => m.id === e.id)) {
                        this.messages = [...this.messages, { ...e, read_by_count: 0 }];
                        this.$nextTick(() => this.scrollToBottom());
                        this.markRead();
                    }
                });
        },

        async sendMessage() {
            if (!this.body.trim() || this.sending) return;
            this.sending = true;
            const text = this.body;
            this.body = '';
            this.$nextTick(() => { if (this.$refs.textarea) this.$refs.textarea.style.height = ''; });
            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Socket-ID': window.Echo?.socketId() ?? '',
                },
                body: JSON.stringify({ body: text }),
            });
            if (res.ok) {
                const msg = await res.json();
                this.messages = [...this.messages, msg];
                this.$nextTick(() => this.scrollToBottom());
            } else {
                this.body = text;
            }
            this.sending = false;
        },

        async markRead() {
            await fetch(markReadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
            });
        },

        handleKey(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        },

        autoResize(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 128) + 'px';
        },

        destroy() {
            this.channel?.stopListening('MessageSent');
            window.Echo?.leave(`chat.${chatId}`);
        },
    }));
});
</script>
</x-app-layout>

