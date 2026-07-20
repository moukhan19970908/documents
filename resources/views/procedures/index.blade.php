<x-app-layout>
    <x-slot name="title">Процедуры — Vamin</x-slot>

    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Процедуры</h1>
                <p class="text-sm text-gray-500 mt-1">Сценарные процедуры с этапами, чек-листом и задачами</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('procedures.tasks.index') }}"
                   class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">Задачи процедур</a>
                @if($canStart)
                    <a href="{{ route('procedures.create') }}"
                       class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">+ Запустить процедуру</a>
                @endif
            </div>
        </div>

        @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>@endif

        {{-- Вкладки --}}
        @php
            $tabs = ['mine' => 'Мои', 'inbox' => 'Требуют действия'];
            if ($canViewAll) $tabs['all'] = 'Все';
        @endphp
        <div class="flex gap-1 mb-4 border-b border-gray-200">
            @foreach($tabs as $key => $label)
                <a href="{{ route('procedures.index', ['tab' => $key]) }}"
                   class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                          {{ $tab === $key ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                    @if($key === 'inbox' && $inboxCount > 0)
                        <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full bg-[#5B4FE8] text-white">{{ $inboxCount }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase tracking-wider bg-gray-50">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold">Номер</th>
                        <th class="text-left px-5 py-3 font-semibold">Название</th>
                        <th class="text-left px-5 py-3 font-semibold">Сценарий</th>
                        <th class="text-left px-5 py-3 font-semibold">Инициатор</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($procedures as $p)
                        <tr class="hover:bg-gray-50/50 transition-colors cursor-pointer" onclick="window.location='{{ route('procedures.show', $p) }}'">
                            <td class="px-5 py-3.5 font-mono text-xs text-gray-500">{{ $p->number ?? '—' }}</td>
                            <td class="px-5 py-3.5"><span class="font-medium text-gray-900">{{ $p->title }}</span></td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $p->template?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $p->initiator?->name }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $p->statusColor() }}">{{ $p->statusLabel() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">Процедур нет.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($procedures->hasPages())
            <div class="mt-4">{{ $procedures->links() }}</div>
        @endif
    </div>
</x-app-layout>
