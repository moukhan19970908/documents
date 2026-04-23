<x-app-layout>
    <x-slot name="title">Уведомления — Vamin</x-slot>

    <div class="max-w-2xl">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Уведомления</h1>
            @if($notifications->total() > 0)
                <form action="{{ route('notifications.read-all') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm text-[#5B4FE8] hover:underline">Отметить все как прочитанные</button>
                </form>
            @endif
        </div>

        <div class="space-y-2">
            @forelse($notifications as $n)
                @php
                    $iconBg = [
                        'approval_approved' => 'bg-green-100 text-green-600',
                        'approval_rejected' => 'bg-red-100 text-red-600',
                        'approval_started'  => 'bg-blue-100 text-blue-600',
                        'stage_changed'     => 'bg-indigo-100 text-indigo-600',
                        'deadline'          => 'bg-yellow-100 text-yellow-600',
                    ][$n->type] ?? 'bg-gray-100 text-gray-600';
                @endphp
                <div class="flex items-start gap-4 bg-white rounded-xl border border-gray-200 p-4 {{ !$n->is_read ? 'border-l-4 border-l-[#5B4FE8]' : '' }}">
                    <div class="w-9 h-9 rounded-full {{ $iconBg }} flex items-center justify-center shrink-0">
                        @if(in_array($n->type, ['approval_approved']))
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @elseif(in_array($n->type, ['approval_rejected']))
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        @elseif($n->type === 'deadline')
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 {{ !$n->is_read ? 'font-semibold' : '' }}">{{ $n->message }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    @if(!$n->is_read)
                        <form action="{{ route('notifications.read', $n) }}" method="POST" class="shrink-0">
                            @csrf
                            <button type="submit" class="w-5 h-5 bg-[#5B4FE8] rounded-full"></button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <p class="text-gray-600 font-medium">Нет уведомлений</p>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
