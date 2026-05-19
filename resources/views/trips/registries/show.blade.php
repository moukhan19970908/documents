<x-app-layout>
    <x-slot name="title">{{ $registry->title }} — Vamin</x-slot>

    <div class="max-w-4xl">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('trips.registries.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="flex-1 flex items-center gap-3">
                <h1 class="text-xl font-bold text-gray-900">{{ $registry->title }}</h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $registry->status_color }}">
                    {{ $registry->status_label }}
                </span>
            </div>
            <div class="flex gap-2">
                @if($registry->status === 'draft' && $registry->created_by === auth()->id())
                    <form action="{{ route('trips.registries.send', $registry) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            Отправить на согласование
                        </button>
                    </form>
                @endif
                @if($registry->status === 'approved' && $registry->created_by === auth()->id())
                    <form action="{{ route('trips.registries.send-accounting', $registry) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                            Передать в бухгалтерию
                        </button>
                    </form>
                @endif
                @if($registry->status === 'sent_to_accounting' && auth()->user()->isAccounting())
                    <form action="{{ route('trips.registries.accept', $registry) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                            Принято
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-5">{{ session('success') }}</div>
        @endif

        {{-- Items table --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-5">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-800">Заявки в реестре</h2>
                <span class="text-xs text-gray-400">{{ $registry->items->count() }} заявок</span>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-3 font-semibold">Сотрудник</th>
                        <th class="text-left px-5 py-3 font-semibold">Отдел</th>
                        <th class="text-left px-5 py-3 font-semibold">Даты</th>
                        <th class="text-right px-5 py-3 font-semibold">Суточные</th>
                        <th class="text-right px-5 py-3 font-semibold">Проживание</th>
                        <th class="text-right px-5 py-3 font-semibold">Переезд</th>
                        <th class="text-right px-5 py-3 font-semibold">Итого</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($registry->items as $item)
                        @php $trip = $item->tripRequest; @endphp
                        <tr>
                            <td class="px-5 py-3.5 font-medium text-gray-900">{{ $trip->user->name }}</td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $trip->user->department?->name }}</td>
                            <td class="px-5 py-3.5 text-gray-600 text-xs">
                                {{ $trip->date_start->format('d.m.Y') }} — {{ $trip->date_end->format('d.m.Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-right text-gray-700">{{ number_format($trip->daily_rate * $trip->days_count, 0, '.', ' ') }}</td>
                            <td class="px-5 py-3.5 text-right text-gray-700">{{ number_format($trip->accommodation_total, 0, '.', ' ') }}</td>
                            <td class="px-5 py-3.5 text-right text-gray-700">{{ number_format($trip->transport_total, 0, '.', ' ') }}</td>
                            <td class="px-5 py-3.5 text-right font-semibold text-gray-900">{{ number_format($trip->total_amount, 0, '.', ' ') }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-gray-50 border-t border-gray-200">
                        <td colspan="6" class="px-5 py-3.5 text-right text-sm font-semibold text-gray-700">Общая сумма реестра</td>
                        <td class="px-5 py-3.5 text-right font-bold text-[#5B4FE8]">{{ number_format($registry->total_amount, 0, '.', ' ') }} ₽</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Approval history --}}
        @if($registry->approvalLogs->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">История</h2>
                <div class="space-y-3">
                    @foreach($registry->approvalLogs as $log)
                        <div class="flex items-start gap-3 text-sm">
                            <div class="w-7 h-7 rounded-full bg-[#5B4FE8]/15 flex items-center justify-center text-[#5B4FE8] text-xs font-bold shrink-0 mt-0.5">
                                {{ mb_substr($log->approver->name, 0, 1) }}
                            </div>
                            <div>
                                <span class="font-medium text-gray-900">{{ $log->approver->name }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ $log->action_label }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</span>
                                @if($log->comment)
                                    <p class="text-gray-500 mt-0.5">{{ $log->comment }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
