@php
    $tabs = [
        ['label' => 'Роли',              'route' => 'admin.roles.index',  'active' => request()->routeIs('admin.roles.index') || request()->routeIs('admin.roles.create') || request()->routeIs('admin.roles.edit')],
        ['label' => 'Матрица прав',      'route' => 'admin.roles.matrix', 'active' => request()->routeIs('admin.roles.matrix')],
        ['label' => 'Наблюдатели',       'route' => 'admin.roles.watchers', 'active' => request()->routeIs('admin.roles.watchers')],
        ['label' => 'Персональные права','route' => 'admin.roles.personal', 'active' => request()->routeIs('admin.roles.personal')],
        ['label' => 'Направления',       'route' => 'admin.roles.directions', 'active' => request()->routeIs('admin.roles.directions')],
    ];
@endphp

<div class="border-b border-gray-200 mb-6">
    <nav class="flex gap-6 -mb-px">
        @foreach($tabs as $tab)
            @if($tab['route'])
                <a href="{{ route($tab['route']) }}"
                   class="pb-3 text-sm font-medium border-b-2 transition-colors
                          {{ ($tab['active'] ?? false)
                             ? 'border-[#5B4FE8] text-[#5B4FE8]'
                             : 'border-transparent text-gray-500 hover:text-gray-900' }}">
                    {{ $tab['label'] }}
                </a>
            @else
                <span class="pb-3 text-sm font-medium border-b-2 border-transparent text-gray-300 cursor-not-allowed"
                      title="Раздел ещё не реализован">{{ $tab['label'] }}</span>
            @endif
        @endforeach
    </nav>
</div>
