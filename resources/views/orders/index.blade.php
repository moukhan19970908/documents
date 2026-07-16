<x-app-layout>
    <x-slot name="title">Приказы — Vamin</x-slot>

    @php $user = auth()->user(); @endphp

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Приказы</h1>

        <div class="flex items-center gap-3">
            {{-- Переключатель перспективы --}}
            @if($user->canIssueOrders())
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                    <a href="{{ route('orders.index', ['as' => 'issuer']) }}"
                       class="px-3 py-1.5 rounded-md font-medium {{ $perspective === 'issuer' ? 'bg-[#5B4FE8] text-white' : 'text-gray-500 hover:text-gray-900' }}">Издающий</a>
                    <a href="{{ route('orders.index', ['as' => 'employee']) }}"
                       class="px-3 py-1.5 rounded-md font-medium {{ $perspective === 'employee' ? 'bg-[#5B4FE8] text-white' : 'text-gray-500 hover:text-gray-900' }}">Сотрудник</a>
                </div>
                <a href="{{ route('orders.create') }}"
                   class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Издать приказ
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    {{-- Фильтры --}}
    <form method="GET" class="flex flex-wrap items-center gap-3 mb-5">
        <input type="hidden" name="as" value="{{ $perspective }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="relative flex-1 min-w-64">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по номеру, названию…"
                   class="w-full text-sm border border-gray-200 rounded-lg pl-10 pr-4 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>
        <select name="status" onchange="this.form.submit()" class="text-sm border border-gray-200 rounded-lg px-3 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <option value="">Статус: Все</option>
            <option value="draft" @selected(request('status')==='draft')>Черновик</option>
            <option value="on_approval" @selected(request('status')==='on_approval')>На согласовании</option>
            <option value="published" @selected(request('status')==='published')>Опубликован</option>
        </select>
        <select name="initiator" onchange="this.form.submit()" class="text-sm border border-gray-200 rounded-lg px-3 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <option value="">Инициатор: Все</option>
            @foreach($initiators as $i)
                <option value="{{ $i->id }}" @selected(request('initiator')==$i->id)>{{ $i->name }}</option>
            @endforeach
        </select>
        <select name="department" onchange="this.form.submit()" class="text-sm border border-gray-200 rounded-lg px-3 py-2.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <option value="">Направление-адресат: Все</option>
            @foreach($directions as $d)
                <option value="{{ $d->id }}" @selected(request('department')==$d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
    </form>

    {{-- Вкладки --}}
    <div class="border-b border-gray-200 mb-5">
        <nav class="flex gap-6 -mb-px">
            <a href="{{ route('orders.index', ['as' => $perspective, 'tab' => 'all']) }}"
               class="pb-3 text-sm font-medium border-b-2 {{ $tab === 'all' ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-900' }}">Все приказы</a>
            <a href="{{ route('orders.index', ['tab' => 'ack']) }}"
               class="pb-3 text-sm font-medium border-b-2 flex items-center gap-2 {{ $tab === 'ack' ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
                Требуют моего ознакомления
                @if($ackPending > 0)
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full bg-red-500 text-white">{{ $ackPending }}</span>
                @endif
            </a>
        </nav>
    </div>

    <div class="space-y-3">
        @forelse($orders as $order)
            @php
                $overdue = $order->status === 'published' && $order->recipients_total > 0
                    && $order->ack_deadline && $order->ack_deadline->isPast()
                    && $order->acknowledged_total < $order->recipients_total;
                $pct = $order->recipients_total > 0 ? round($order->acknowledged_total / $order->recipients_total * 100) : 0;
                $colors = ['gray' => 'bg-gray-100 text-gray-600', 'blue' => 'bg-blue-50 text-blue-600', 'green' => 'bg-emerald-50 text-emerald-600'];
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center gap-5">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-bold text-gray-400">[ПР]</span>
                        <a href="{{ route('orders.show', $order) }}" class="font-semibold text-gray-900 hover:text-[#5B4FE8] truncate">{{ $order->title }}</a>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $colors[$order->statusColor()] }}">{{ $order->statusLabel() }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ $order->number ?? '— без номера' }}
                        @if($order->published_at) · издан {{ $order->published_at->translatedFormat('j M') }} @endif
                    </p>
                    <div class="flex items-center gap-2 mt-2">
                        <img src="{{ $order->initiator->avatar_url }}" alt="" class="w-5 h-5 rounded-full">
                        <span class="text-xs text-gray-500">{{ $order->initiator->name }}</span>
                    </div>
                </div>

                @if($order->status === 'published')
                    <div class="w-64 shrink-0">
                        <p class="text-sm {{ $overdue ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            Ознакомлено {{ $order->acknowledged_total }} из {{ $order->recipients_total }}
                        </p>
                        <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $overdue ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @else
                    <div class="w-64 shrink-0 text-xs text-gray-400">{{ $order->statusLabel() }}</div>
                @endif

                <a href="{{ route('orders.show', $order) }}"
                   class="shrink-0 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Открыть</a>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">
                {{ $tab === 'ack' ? 'Нет приказов, требующих вашего ознакомления.' : 'Приказов пока нет.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-5">{{ $orders->links() }}</div>
</x-app-layout>
