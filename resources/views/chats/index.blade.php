<x-app-layout>
    <x-slot name="title">Чаты — Vamin</x-slot>

    @php
        $userId = auth()->id();
        $totalUnread = $chats->sum('unread_count');
    @endphp

    {{-- Full-height two-panel layout, breaks out of the main p-4 padding --}}
    <div class="-m-4 md:-m-6 flex bg-white border border-gray-200 rounded-none md:rounded-xl overflow-hidden"
         style="height: calc(100vh - 64px)">

        {{-- ═══ LEFT PANEL: Chat list ═══ --}}
        <div class="shrink-0 border-r border-gray-100 flex flex-col h-full {{ $activeChat ? 'hidden md:flex' : 'flex' }}"
             style="width: calc(var(--spacing) * 135);"
             x-data="chatPanel({ favorites: {{ Js::from($chats->where('is_favorite', true)->pluck('id')->values()) }} })"
             x-init="$nextTick(() => apply())">

            {{-- Header --}}
            <div class="px-5 pt-5 pb-3 border-b border-gray-100 space-y-3">
                <div class="flex items-center justify-between">
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
                    <input type="text" x-model="search" @input="apply()" placeholder="Поиск по чатам..." autocomplete="off"
                           class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] focus:bg-white">
                </div>
                {{-- Document type filter --}}
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 018 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                    <select x-model="type" @change="apply()"
                            class="w-full pl-9 pr-8 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] focus:bg-white text-gray-700">
                        <option value="">Все типы документов</option>
                        @foreach($documentTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="flex border-b border-gray-100 px-4 pt-2 gap-4">
                <button @click="tab='all'; apply()"
                        :class="tab==='all' ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="text-sm font-medium pb-2 px-1 border-b-2 transition-colors whitespace-nowrap">
                    Все
                    @if($chats->count() > 0)
                        <span class="ml-1 text-xs bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5">{{ $chats->count() }}</span>
                    @endif
                </button>
                <button @click="tab='unread'; apply()"
                        :class="tab==='unread' ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="text-sm font-medium pb-2 px-1 border-b-2 transition-colors whitespace-nowrap">
                    Непрочитанные
                    @if($totalUnread > 0)
                        <span class="ml-1 text-xs bg-red-100 text-red-600 rounded-full px-1.5 py-0.5">{{ $totalUnread }}</span>
                    @endif
                </button>
                <button @click="tab='favorite'; apply()"
                        :class="tab==='favorite' ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="text-sm font-medium pb-2 px-1 border-b-2 transition-colors whitespace-nowrap">
                    Избранное
                    <span class="ml-1 text-xs bg-amber-100 text-amber-600 rounded-full px-1.5 py-0.5" x-show="favorites.length > 0" x-text="favorites.length"></span>
                </button>
            </div>

            {{-- Chat list --}}
            <div class="flex-1 overflow-y-auto" x-ref="list">
                @forelse($chats as $chat)
                    @php
                        $lastMsg  = $chat->messages->first();
                        $deptName = $chat->document->initiator->department->name ?? null;
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
                    <div class="chat-item flex items-stretch border-b border-gray-50 hover:bg-gray-50 transition-colors
                                {{ isset($activeChat) && $activeChat->id === $chat->id ? 'bg-indigo-50 border-l-2 border-l-[#5B4FE8]' : '' }}"
                         data-id="{{ $chat->id }}"
                         data-title="{{ mb_strtolower($chat->document->title ?? '') }}"
                         data-type="{{ $chat->document->document_type_id }}"
                         data-unread="{{ $chat->unread_count > 0 ? 1 : 0 }}">
                        <a href="{{ route('chats.show', $chat) }}" class="flex items-center gap-3 flex-1 min-w-0 pl-4 py-3">
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
                                @if($deptName)
                                    <p class="text-[11px] text-gray-400 truncate mt-0.5 flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        {{ $deptName }}
                                    </p>
                                @endif
                                <div class="flex items-center justify-between gap-1 mt-0.5">
                                    <p class="text-xs text-gray-500 truncate">
                                        @if($lastMsg)
                                            <span class="font-medium text-gray-600">{{ $lastMsg->user->name }}:</span>
                                            {{ $lastMsg->body !== '' ? Str::limit($lastMsg->body, 40) : '📎 ' . Str::limit($lastMsg->file_name ?? 'файл', 30) }}
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
                        {{-- Favorite toggle --}}
                        <button type="button" @click="toggleFav({{ $chat->id }}, $event)" title="В избранное"
                                :class="favorites.includes({{ $chat->id }}) ? 'text-amber-400' : 'text-gray-300 hover:text-amber-400'"
                                class="flex items-center px-3 shrink-0 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                                 :fill="favorites.includes({{ $chat->id }}) ? 'currentColor' : 'none'">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5l2.17 4.4 4.86.7-3.52 3.43.83 4.84-4.34-2.28-4.34 2.28.83-4.84L4.5 8.6l4.86-.7 2.12-4.4z"/>
                            </svg>
                        </button>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-gray-400 text-sm">Нет активных чатов</div>
                @endforelse

                {{-- No-results placeholder (shown by apply() when filters match nothing) --}}
                <div x-ref="empty" style="display:none" class="px-5 py-10 text-center text-gray-400 text-sm">Ничего не найдено</div>
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
                                 'attachment'    => $m->attachment,
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
                                            <template x-if="msg.attachment">
                                                <div style="margin-bottom:4px">
                                                    <template x-if="msg.attachment.is_image">
                                                        <a :href="msg.attachment.preview_url" target="_blank"><img :src="msg.attachment.preview_url" style="max-width:220px;max-height:220px;border-radius:8px;display:block"></a>
                                                    </template>
                                                    <template x-if="!msg.attachment.is_image">
                                                        <a :href="msg.attachment.download_url" style="display:flex;align-items:center;gap:8px;background:rgba(0,0,0,0.06);border-radius:8px;padding:8px 10px;text-decoration:none;max-width:240px">
                                                            <svg xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px;flex-shrink:0;color:#5B4FE8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                            <span style="min-width:0">
                                                                <span style="display:block;font-size:13px;color:#1f2937;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="msg.attachment.name"></span>
                                                                <span style="font-size:11px;color:#667781" x-text="msg.attachment.size"></span>
                                                            </span>
                                                        </a>
                                                    </template>
                                                </div>
                                            </template>
                                            <p x-show="msg.body" style="font-size:14px;color:#1f2937;line-height:1.5;word-break:break-word;white-space:pre-wrap;margin:0 0 2px 0" x-text="msg.body"></p>
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
                                            <template x-if="msg.attachment">
                                                <div style="margin-bottom:4px">
                                                    <template x-if="msg.attachment.is_image">
                                                        <a :href="msg.attachment.preview_url" target="_blank"><img :src="msg.attachment.preview_url" style="max-width:220px;max-height:220px;border-radius:8px;display:block"></a>
                                                    </template>
                                                    <template x-if="!msg.attachment.is_image">
                                                        <a :href="msg.attachment.download_url" style="display:flex;align-items:center;gap:8px;background:rgba(0,0,0,0.05);border-radius:8px;padding:8px 10px;text-decoration:none;max-width:240px">
                                                            <svg xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px;flex-shrink:0;color:#5B4FE8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                            <span style="min-width:0">
                                                                <span style="display:block;font-size:13px;color:#1f2937;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="msg.attachment.name"></span>
                                                                <span style="font-size:11px;color:#667781" x-text="msg.attachment.size"></span>
                                                            </span>
                                                        </a>
                                                    </template>
                                                </div>
                                            </template>
                                            <p x-show="msg.body" style="font-size:14px;color:#1f2937;line-height:1.5;word-break:break-word;white-space:pre-wrap;margin:0 0 2px 0" x-text="msg.body"></p>
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

                    {{-- Pending attachment bar --}}
                    <div x-show="file" style="display:none;flex-shrink:0;background:#f0f2f5;padding:8px 12px 0">
                        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px">
                            <div style="display:flex;align-items:center;gap:10px">
                                <svg xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px;flex-shrink:0;color:#5B4FE8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                <div style="flex:1;min-width:0">
                                    <p style="font-size:13px;color:#1f2937;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0" x-text="file?.name"></p>
                                    <p style="font-size:11px;color:#667781;margin:0" x-text="humanSize(file?.size)"></p>
                                </div>
                                <button type="button" @click="removeFile()" style="border:none;background:none;cursor:pointer;color:#9ca3af;padding:4px;line-height:0" title="Убрать файл">
                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            {{-- «Приложить файл к связанным документам?» — off by default (stays a chat attachment). --}}
                            <label style="display:flex;align-items:center;gap:8px;margin-top:8px;padding-top:8px;border-top:1px solid #f3f4f6;cursor:pointer">
                                <input type="checkbox" x-model="attachToDoc" style="width:16px;height:16px;accent-color:#5B4FE8;cursor:pointer">
                                <span style="font-size:13px;color:#374151">Приложить файл к связанным документам?</span>
                            </label>
                        </div>
                    </div>

                    {{-- Input --}}
                    <div style="flex-shrink:0;display:flex;gap:8px;align-items:flex-end;padding:8px 12px;background:#f0f2f5">
                        <input type="file" x-ref="fileInput" @change="onFileChange($event)" style="display:none">
                        <button type="button" @click="$refs.fileInput.click()" title="Прикрепить файл"
                                style="width:44px;height:44px;background:#fff;border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;box-shadow:0 1px 2px rgba(0,0,0,.1);color:#54656f">
                            <svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                        </button>
                        <textarea x-model="body"
                                  @keydown="handleKey($event)"
                                  placeholder="Написать сообщение…"
                                  rows="1"
                                  x-ref="textarea"
                                  @input="autoResize($el)"
                                  style="flex:1;font-size:14px;background:#fff;border:none;border-radius:20px;padding:10px 16px;resize:none;outline:none;max-height:128px;color:#1f2937;box-shadow:0 1px 2px rgba(0,0,0,.1);font-family:inherit"></textarea>
                        <button @click="sendMessage()" :disabled="(!body.trim() && !file) || sending"
                                style="width:44px;height:44px;background:#5B4FE8;border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:opacity 0.15s"
                                :style="(!body.trim() && !file) || sending ? 'opacity:0.45;cursor:not-allowed' : 'opacity:1'">
                            <svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
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
document.addEventListener('alpine:init', () => {
    // Left panel: search + document-type filter + Все/Непрочитанные/Избранное tabs + favorites.
    Alpine.data('chatPanel', ({ favorites }) => ({
        search: '',
        type: '',
        tab: 'all',
        favorites: favorites ?? [],
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        favUrl: '{{ route('chats.favorite', ['chat' => '__ID__']) }}',

        apply() {
            const q    = this.search.trim().toLowerCase();
            const type = this.type;
            const tab  = this.tab;
            let visible = 0;

            this.$refs.list.querySelectorAll('.chat-item').forEach(el => {
                const id          = Number(el.dataset.id);
                const matchSearch = !q || (el.dataset.title || '').includes(q);
                const matchType   = !type || el.dataset.type === type;
                const matchTab    = tab === 'all'
                    ? true
                    : (tab === 'unread' ? el.dataset.unread === '1' : this.favorites.includes(id));
                const show = matchSearch && matchType && matchTab;
                el.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            if (this.$refs.empty) this.$refs.empty.style.display = visible === 0 ? '' : 'none';
        },

        toggleFav(id, ev) {
            ev.preventDefault();
            ev.stopPropagation();
            const wasFav = this.favorites.includes(id);
            // Optimistic update
            this.favorites = wasFav ? this.favorites.filter(x => x !== id) : [...this.favorites, id];
            this.apply();

            fetch(this.favUrl.replace('__ID__', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
            })
            .then(r => r.json())
            .then(d => {
                const has = this.favorites.includes(id);
                if (d.favorited && !has) this.favorites = [...this.favorites, id];
                if (!d.favorited && has) this.favorites = this.favorites.filter(x => x !== id);
                this.apply();
            })
            .catch(() => {
                // Revert on failure
                this.favorites = wasFav ? [...this.favorites, id] : this.favorites.filter(x => x !== id);
                this.apply();
            });
        },
    }));

    Alpine.data('chatWidget', ({ chatId, currentUserId, initialMessages, firstUnreadId, messagesUrl, sendUrl, markReadUrl }) => ({
        messages: (initialMessages ?? []).slice().sort((a, b) => new Date(a.created_at) - new Date(b.created_at)),
        currentUserId: currentUserId,
        firstUnreadId: firstUnreadId,
        body: '',
        file: null,
        attachToDoc: false,
        sending: false,
        channel: null,
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        onFileChange(e) {
            this.file = e.target.files[0] ?? null;
            this.attachToDoc = false;
        },

        removeFile() {
            this.file = null;
            this.attachToDoc = false;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        humanSize(bytes) {
            if (!bytes && bytes !== 0) return '';
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' МБ';
            if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' КБ';
            return bytes + ' Б';
        },

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
            if ((!this.body.trim() && !this.file) || this.sending) return;
            this.sending = true;
            const text = this.body;
            const file = this.file;
            const attachToDoc = this.attachToDoc;
            this.body = '';
            this.removeFile();
            this.$nextTick(() => { if (this.$refs.textarea) this.$refs.textarea.style.height = ''; });

            const form = new FormData();
            form.append('body', text);
            if (file) {
                form.append('file', file);
                form.append('attach_to_document', attachToDoc ? '1' : '0');
            }

            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Socket-ID': window.Echo?.socketId() ?? '',
                },
                body: form,
            });
            if (res.ok) {
                const msg = await res.json();
                this.messages = [...this.messages, msg];
                this.$nextTick(() => this.scrollToBottom());
            } else {
                // Restore the text so the user doesn't lose it (file must be re-picked).
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

