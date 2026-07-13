@php
    // Literal class strings so the Tailwind scanner keeps them.
    $roleTiles = [
        'indigo'  => 'bg-indigo-50 text-indigo-600',
        'blue'    => 'bg-blue-50 text-blue-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'rose'    => 'bg-rose-50 text-rose-600',
        'slate'   => 'bg-slate-100 text-slate-600',
    ];
    $tile = $roleTiles[$color ?? 'indigo'] ?? $roleTiles['indigo'];
@endphp
<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $tile }}">
    @include('admin.partials.role-icon', ['icon' => $icon ?? 'user', 'class' => 'w-4 h-4'])
</div>
