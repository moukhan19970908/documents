@php
    $tabs = [
        ['label' => 'Профиль', 'route' => 'profile.edit',     'active' => request()->routeIs('profile.edit')],
        ['label' => 'Пароль',  'route' => 'profile.password', 'active' => request()->routeIs('profile.password')],
    ];
@endphp

<div class="border-b border-gray-200 mb-6">
    <nav class="flex gap-6 -mb-px">
        @foreach($tabs as $tab)
            <a href="{{ route($tab['route']) }}"
               class="pb-3 text-sm font-medium border-b-2 transition-colors
                      {{ $tab['active']
                         ? 'border-[#5B4FE8] text-[#5B4FE8]'
                         : 'border-transparent text-gray-500 hover:text-gray-900' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
