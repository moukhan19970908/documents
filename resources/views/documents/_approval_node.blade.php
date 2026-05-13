@php
    $avatarBg = match($status) {
        'approved' => 'ring-2 ring-green-400',
        'rejected' => 'ring-2 ring-red-400',
        'waiting'  => 'ring-2 ring-[#6C5CE7]',
        'delegated'=> 'ring-2 ring-yellow-400',
        default    => 'ring-2 ring-gray-200',
    };
    $badgeCls = match($status) {
        'approved' => 'bg-green-100 text-green-700',
        'rejected' => 'bg-red-100 text-red-700',
        'waiting'  => 'bg-[#6C5CE7]/10 text-[#6C5CE7] font-semibold',
        'delegated'=> 'bg-yellow-100 text-yellow-700',
        default    => 'bg-gray-100 text-gray-400',
    };
    $badgeIcon = match($status) {
        'approved' => '✓',
        'rejected' => '✗',
        default    => null,
    };
    $initials = $user ? mb_strtoupper(mb_substr($user->name, 0, 1)) : '?';
@endphp

<div class="flex flex-col items-center shrink-0" style="width:88px">
    {{-- Avatar --}}
    <div class="relative mb-4 pb-1">
        <div class="w-12 h-12 rounded-full overflow-hidden {{ $avatarBg }} bg-indigo-50 flex items-center justify-center">
            @if($user?->avatar)
                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
            @else
                <span class="text-sm font-bold text-indigo-600">{{ $initials }}</span>
            @endif
        </div>
        @if($status === 'approved')
            <span class="absolute -bottom-2 left-1/2 ml-4 mt-1 -translate-x-1/2 w-4 h-4 bg-green-500 rounded-full flex items-center justify-center">
                <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </span>
        @elseif($status === 'rejected')
            <span class="absolute -bottom-2 left-1/2 ml-4 mt-1 -translate-x-1/2 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center">
                <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </span>
        @endif
    </div>

    {{-- Name --}}
    <p class="text-xs font-semibold text-gray-900 text-center leading-tight line-clamp-2 w-full">
        {{ $user?->name ?? '—' }}
    </p>

    {{-- Sub-label (department / role label) --}}
    @if($label)
        <p class="text-[10px] text-gray-400 text-center mt-0.5 leading-tight line-clamp-1 w-full">{{ $label }}</p>
    @endif

    {{-- Status / date --}}
    @if($date)
        <p class="text-[10px] mt-1 text-center leading-tight {{ $status === 'waiting' ? ($isMe ? 'text-[#6C5CE7] font-semibold' : 'text-gray-500') : ($status === 'rejected' ? 'text-red-500' : 'text-gray-400') }}">
            {{ $date }}
        </p>
    @endif
</div>
