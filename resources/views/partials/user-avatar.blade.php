{{-- Кружок с инициалами; цвет закреплён за человеком, чтобы участников было легко различать. Требует $user, $size — необязательно. --}}
@php
    $palette = [
        'bg-indigo-50 text-indigo-600', 'bg-emerald-50 text-emerald-600', 'bg-sky-50 text-sky-600',
        'bg-amber-50 text-amber-700', 'bg-rose-50 text-rose-600', 'bg-violet-50 text-violet-600',
    ];
@endphp
<span title="{{ $user?->name }}"
      class="{{ $size ?? 'w-8 h-8 text-xs' }} {{ $palette[($user?->id ?? 0) % count($palette)] }} rounded-full font-semibold flex items-center justify-center shrink-0">{{ $user?->initials() ?: '—' }}</span>
