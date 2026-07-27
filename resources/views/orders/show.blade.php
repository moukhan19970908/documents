<x-app-layout>
    <x-slot name="title">{{ $order->number ?? $order->title }} — Vamin</x-slot>

    @php
        $user = auth()->user();
        $total = $acknowledged->count() + $pending->count();
        $pct = $total > 0 ? round($acknowledged->count() / $total * 100) : 0;
        $overdue = $order->ackOverdue();
        $myAck = $order->acknowledgments()->where('user_id', $user->id)->first();
        $canManage = $order->initiator_id === $user->id || $user->canIssueOrders();
        $canDelete = $order->canBeDeletedBy($user);
        $statusColors = ['gray' => 'bg-gray-100 text-gray-600', 'blue' => 'bg-blue-50 text-blue-600', 'green' => 'bg-emerald-50 text-emerald-600'];
    @endphp

    {{-- Хлебные крошки --}}
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
        <a href="{{ route('orders.index') }}" class="hover:text-gray-700">Приказы</a>
        <span>›</span>
        <span class="text-gray-600">{{ $order->number ?? 'черновик' }}</span>
    </div>

    <div class="flex items-start justify-between gap-4 mb-1">
        <h1 class="text-2xl font-bold text-gray-900">
            @if($order->number)
                Приказ № {{ $order->seq }} от {{ optional($order->effective_at)->format('d.m.Y') }} — {{ $order->title }}
            @else
                {{ $order->title }}
            @endif
            <span class="align-middle text-xs px-2 py-0.5 rounded-full {{ $statusColors[$order->statusColor()] }}">{{ $order->statusLabel() }}</span>
        </h1>

        @if($order->isPublished() && $canDelete)
            <form method="POST" action="{{ route('orders.destroy', $order) }}" class="shrink-0"
                  onsubmit="return confirm('Удалить приказ {{ $order->number }}? Данные ознакомления будут потеряны.')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-red-500 hover:bg-red-50 hover:border-red-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Удалить приказ
                </button>
            </form>
        @endif
    </div>
    <p class="text-sm text-gray-500 mb-6">
        Инициатор: {{ $order->initiator->name }}@if($order->initiator->department), {{ $order->initiator->department->name }}@endif · {{ $order->kindLabel() }}
    </p>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    @if($order->status !== 'published')
        {{-- ── Черновик ── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl p-8">
                @if($order->file_path)
                    <a href="{{ route('orders.file', $order) }}" class="text-[#5B4FE8] hover:underline text-sm">📎 {{ $order->file_name }}</a>
                @else
                    <div class="prose max-w-none text-sm">{!! $order->renderedBody() !!}</div>
                @endif
            </div>
            <div class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Адресаты</p>
                    @php $aud = $order->audience ?? []; @endphp
                    <div class="flex flex-wrap gap-2">
                        @foreach($aud['departments'] ?? [] as $d)
                            <span class="text-xs bg-indigo-50 border border-indigo-100 rounded-full px-2.5 py-1">{{ $d['name'] }} ({{ $d['count'] }})</span>
                        @endforeach
                        @foreach($aud['users'] ?? [] as $u)
                            <span class="text-xs bg-violet-50 border border-violet-100 rounded-full px-2.5 py-1">{{ $u['name'] }}</span>
                        @endforeach
                        @if(empty($aud['departments']) && empty($aud['users']))
                            <span class="text-sm text-gray-400">Адресаты не выбраны</span>
                        @endif
                    </div>
                    @if($order->ack_deadline)
                        <p class="text-xs text-gray-400 mt-3">Срок ознакомления: {{ $order->ack_deadline->format('d.m.Y') }}</p>
                    @endif
                </div>

                @if($order->status === 'on_approval')
                    @php
                        $myApproval = $order->approvals->first(fn ($a) => $a->approver_id === $user->id && $a->status === 'pending');
                        $ap = ['pending' => ['ожидает', 'text-amber-500'], 'approved' => ['согласовано', 'text-emerald-600'], 'rejected' => ['отклонено', 'text-red-500']];
                    @endphp
                    <div class="bg-white border border-gray-200 rounded-xl p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Согласование</p>
                        <div class="space-y-3">
                            @foreach($order->approvals as $a)
                                <div class="flex items-center gap-3">
                                    <img src="{{ $a->approver->avatar_url }}" alt="" class="w-8 h-8 rounded-full">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-900 truncate">{{ $a->approver->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $a->role_label }}</p>
                                    </div>
                                    <span class="text-xs {{ $ap[$a->status][1] }} shrink-0">{{ $ap[$a->status][0] }}</span>
                                </div>
                                @if($a->comment)
                                    <p class="text-xs text-red-500 pl-11 -mt-2">{{ $a->comment }}</p>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    @if($myApproval)
                        <div x-data="{ rejecting: false }" class="bg-white border border-gray-200 rounded-xl p-5 space-y-2">
                            <form method="POST" action="{{ route('orders.approve', $order) }}">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Согласовать</button>
                            </form>
                            <button type="button" @click="rejecting = !rejecting" class="w-full px-4 py-2.5 text-red-500 rounded-lg text-sm hover:bg-red-50">Отклонить</button>
                            <form x-show="rejecting" x-cloak method="POST" action="{{ route('orders.reject', $order) }}" class="space-y-2 pt-1">
                                @csrf
                                <textarea name="comment" rows="2" placeholder="Причина отклонения" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-300"></textarea>
                                <button type="submit" class="w-full px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">Отклонить приказ</button>
                            </form>
                        </div>
                    @endif
                @elseif($canManage)
                    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-2">
                        <form method="POST" action="{{ route('orders.publish', $order) }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Опубликовать приказ</button>
                        </form>
                        <a href="{{ route('orders.edit', $order) }}" class="block text-center w-full px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Редактировать</a>
                        @if($canDelete)
                            <form method="POST" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('Удалить приказ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-full px-4 py-2.5 text-red-500 rounded-lg text-sm hover:bg-red-50">Удалить</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- ── Опубликован: трекинг ознакомления ── --}}
        <div class="grid grid-cols-1 lg:grid-cols-[16rem_1fr] gap-6 mb-6">
            <div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl h-56 flex items-center justify-center">
                    <svg class="w-12 h-12 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                @if($order->file_path)
                    <a href="{{ route('orders.file', $order) }}" class="mt-3 flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Скачать файл</a>
                @else
                    <a href="{{ route('orders.pdf', $order) }}" target="_blank" class="mt-3 flex items-center justify-center gap-2 w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Скачать PDF
                    </a>
                @endif
            </div>

            <div class="bg-white border border-gray-200 rounded-xl px-6 py-5 flex items-center gap-6">
                <div class="text-4xl font-bold text-gray-900 shrink-0">{{ $pct }}%</div>
                <div class="flex-1">
                    <p class="text-sm {{ $overdue ? 'text-red-600' : 'text-gray-500' }} mb-2">Ознакомлено {{ $acknowledged->count() }} из {{ $total }}</p>
                    <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full {{ $overdue ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @if($order->ack_deadline)
                    <div class="text-right shrink-0">
                        <p class="text-xs text-gray-400">Срок ознакомления</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $order->ack_deadline->format('d.m.Y') }}</p>
                        @php $days = now()->startOfDay()->diffInDays($order->ack_deadline, false); @endphp
                        <p class="text-xs {{ $days < 0 ? 'text-red-500' : 'text-amber-500' }}">
                            {{ $days < 0 ? 'просрочено' : 'осталось ' . $days . ' ' . \Illuminate\Support\Str::plural('день', $days) }}
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Кнопка «ознакомиться» для адресата --}}
        @if($myAck && ! $myAck->acknowledged_at)
            <form method="POST" action="{{ route('orders.acknowledge', $order) }}" class="mb-6">
                @csrf
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Ознакомиться с приказом</button>
            </form>
        @endif

        <div x-data="{ q: '', dept: '' }">
            <div class="flex items-center gap-3 mb-4">
                <div class="relative w-72">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="q" placeholder="Поиск по имени…" class="w-full text-sm border border-gray-200 rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                <select x-model="dept" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <option value="">Отдел: Все</option>
                    @foreach($deptFilter as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Ознакомлены --}}
                <div>
                    <p class="text-sm font-semibold text-emerald-600 mb-3">Ознакомлены ({{ $acknowledged->count() }})</p>
                    <div class="space-y-2">
                        @forelse($acknowledged as $ack)
                            @php $u = $ack->user; @endphp
                            <div x-show="(q === '' || '{{ mb_strtolower($u->name) }}'.includes(q.toLowerCase())) && (dept === '' || dept === '{{ $u->department_id }}')"
                                 class="bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-center gap-3">
                                <img src="{{ $u->avatar_url }}" alt="" class="w-9 h-9 rounded-full shrink-0">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $u->name }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ $u->position }}@if($u->department) · {{ $u->department->name }}@endif</p>
                                </div>
                                <span class="text-xs text-gray-400 shrink-0">{{ $ack->acknowledged_at->translatedFormat('j M, H:i') }}</span>
                                <svg class="w-5 h-5 text-emerald-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Пока никто не ознакомился.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Не ознакомлены --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-gray-700">Не ознакомлены ({{ $pending->count() }})</p>
                        @if($canManage && $pending->count() > 0)
                            <form method="POST" action="{{ route('orders.remind', $order) }}">
                                @csrf
                                <button type="submit" class="text-sm px-3 py-1.5 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50">Напомнить не ознакомленным</button>
                            </form>
                        @endif
                    </div>
                    <div class="space-y-2">
                        @forelse($pending as $ack)
                            @php $u = $ack->user; @endphp
                            <div x-show="(q === '' || '{{ mb_strtolower($u->name) }}'.includes(q.toLowerCase())) && (dept === '' || dept === '{{ $u->department_id }}')"
                                 class="bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-center gap-3">
                                <img src="{{ $u->avatar_url }}" alt="" class="w-9 h-9 rounded-full shrink-0">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium {{ $overdue ? 'text-red-600' : 'text-gray-900' }} truncate">{{ $u->name }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ $u->position }}@if($u->department) · {{ $u->department->name }}@endif</p>
                                </div>
                                @if($overdue)
                                    <span class="text-xs text-red-500 shrink-0 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                                        просрочено
                                    </span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Все ознакомились 🎉</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
