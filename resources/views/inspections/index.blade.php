<x-app-layout>
    <x-slot name="title">Проверки — Vamin</x-slot>

    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Проверки</h1>
                <p class="text-sm text-gray-500 mt-1">Внутренние проверки: дерево с запросами данных и итоговым актом (ТЗ 20).</p>
            </div>
            @if($canInitiate)
                <a href="{{ route('inspections.create') }}"
                   class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">+ Новая проверка</a>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>@endif

        @php
            $tabs = ['incoming' => 'Мне на исполнение', 'outgoing' => 'Мои'];
            if ($canAll) $tabs['all'] = 'Все';
        @endphp
        <div class="flex gap-1 mb-4 border-b border-gray-200">
            @foreach($tabs as $key => $label)
                <a href="{{ route('inspections.index', ['tab' => $key]) }}"
                   class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                          {{ $tab === $key ? 'border-[#5B4FE8] text-[#5B4FE8]' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                    @if($key === 'incoming' && $incomingPending > 0)
                        <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold rounded-full bg-[#5B4FE8] text-white">{{ $incomingPending }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase tracking-wider bg-gray-50">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold">Номер</th>
                        <th class="text-left px-5 py-3 font-semibold">Проверка</th>
                        <th class="text-left px-5 py-3 font-semibold">Проверяющий</th>
                        <th class="text-left px-5 py-3 font-semibold">Статус</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($inspections as $i)
                        <tr class="hover:bg-gray-50/50 transition-colors cursor-pointer" onclick="window.location='{{ route('inspections.show', $i) }}'">
                            <td class="px-5 py-3.5 font-mono text-xs text-gray-500">{{ $i->number ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <span class="font-medium text-gray-900">{{ $i->title }}</span>
                                @if(! $i->isRoot())<span class="ml-1 text-xs text-indigo-500">запрос данных</span>@endif
                                @if($i->object_label || $i->kind)
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        @if($i->objectTypeLabel()){{ $i->objectTypeLabel() }}: {{ $i->object_label }}@endif
                                        @if($i->kind) · {{ $i->kindLabel() }}@endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $i->executor?->name }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $i->statusColor() }}">{{ $i->statusLabel() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">Проверок нет.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inspections->hasPages())<div class="mt-4">{{ $inspections->links() }}</div>@endif
    </div>
</x-app-layout>
