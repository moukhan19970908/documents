<x-app-layout>
    <x-slot name="title">Роли и доступы — Vamin</x-slot>

    @php
        $plural = fn (int $n) => $n % 10 === 1 && $n % 100 !== 11
            ? 'пользователь'
            : (in_array($n % 10, [2, 3, 4]) && !in_array($n % 100, [12, 13, 14]) ? 'пользователя' : 'пользователей');
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Роли и доступы</h1>
    </div>

    @include('admin.roles.partials.tabs')

    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-gray-500">Роль задаёт права пользователя. Один пользователь может иметь несколько ролей.</p>
        <a href="{{ route('admin.roles.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новая роль
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($roles as $role)
            @php $roleUsers = $holders[$role->id]; @endphp
            <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col">
                <div class="flex items-start gap-3 mb-4">
                    @include('admin.partials.role-tile', ['icon' => $role->icon, 'color' => $role->color])
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="font-semibold text-gray-900 truncate">{{ $role->name }}</h2>
                            @if($role->is_system)
                                <span class="text-[10px] font-semibold text-gray-400 border border-gray-200 rounded px-1.5 py-0.5 shrink-0">СИСТ.</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $roleUsers->count() }} {{ $plural($roleUsers->count()) }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-1 mb-4 min-h-8">
                    @foreach($roleUsers->take(4) as $holder)
                        <img src="{{ $holder->avatar_url }}" alt="{{ $holder->name }}" title="{{ $holder->name }}"
                             class="w-8 h-8 rounded-full ring-2 ring-white -mr-2 last:mr-0">
                    @endforeach
                    @if($roleUsers->count() > 4)
                        <span class="ml-3 text-xs font-medium text-gray-400">+{{ $roleUsers->count() - 4 }}</span>
                    @endif
                    @if($roleUsers->isEmpty())
                        <span class="text-xs text-gray-400">Никому не назначена</span>
                    @endif
                </div>

                <div class="flex gap-2 mt-auto">
                    <a href="{{ route('admin.roles.edit', $role) }}"
                       class="flex-1 text-center text-xs font-medium text-[#5B4FE8] border border-[#5B4FE8] px-3 py-2 rounded-lg hover:bg-indigo-50">
                        Настроить
                    </a>
                    <form action="{{ route('admin.roles.duplicate', $role) }}" method="POST">
                        @csrf
                        <button class="text-xs font-medium text-gray-600 border border-gray-200 px-3 py-2 rounded-lg hover:bg-gray-50">Дублировать</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">
                Роли не найдены. Выполните <code>php artisan db:seed --class=RoleSeeder</code> или создайте роль вручную.
            </div>
        @endforelse
    </div>
</x-app-layout>
