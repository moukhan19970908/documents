<x-app-layout>
    <x-slot name="title">Дашборд — Vamin.ru</x-slot>

    {{-- Greeting --}}
    <div class="flex flex-col md:flex-row md:items-start gap-6">
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900">
                @php
                    $hour = now()->hour;
                    $greeting = $hour < 12 ? 'Доброе утро' : ($hour < 18 ? 'Добрый день' : 'Добрый вечер');
                @endphp
                {{ $greeting }}, {{ auth()->user()->name }}.
            </h1>
            <p class="text-sm text-gray-500 mt-1">Статус ваших рабочих процессов.</p>

            {{-- Stats row --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                {{-- Pending --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Ожидают согласования</p>
                    <div class="flex items-end gap-2 mt-3">
                        <span class="text-4xl font-bold text-gray-900">{{ $stats['pending_count'] }}</span>
                        <span class="text-xs text-green-600 font-medium mb-1 flex items-center gap-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            12%
                        </span>
                    </div>
                </div>

                {{-- Processed --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Обработано за неделю</p>
                    <div class="flex items-end gap-2 mt-3">
                        <span class="text-4xl font-bold text-gray-900">{{ $stats['processed_week'] }}</span>
                        <span class="text-xs text-green-600 font-medium mb-1 flex items-center gap-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            4%
                        </span>
                    </div>
                </div>

                {{-- Active phases --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">В процессе</p>
                    <div class="flex items-center gap-4 mt-3">
                        <div>
                            <p class="text-xs text-gray-500">Активность документов сегодня</p>
                            <p class="text-base font-bold text-gray-800">{{ $stats['active_phases'] }}</p>
                        </div>
                        {{-- Simple donut --}}
                        <div x-data="{ progress: 75 }" class="ml-auto">
                            <svg class="w-14 h-14 -rotate-90" viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#E5E7EB" stroke-width="3"/>
                                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#5B4FE8" stroke-width="3"
                                        stroke-dasharray="75 25" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <span class="text-2xl font-bold text-gray-900 -ml-2">75%</span>
                    </div>
                    <div class="mt-2 space-y-1">
                        <div class="flex items-center gap-2 text-xs text-gray-600">
                            <span class="w-2 h-2 rounded-full bg-[#5B4FE8] inline-block"></span>
                            Проверка (45%)
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-600">
                            <span class="w-2 h-2 rounded-full bg-indigo-300 inline-block"></span>
                            Разработка (30%)
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action required table --}}
            <div class="bg-white rounded-xl border border-gray-200 mt-6">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Требуют действия</h2>
                    <a href="{{ route('tasks') }}" class="text-sm text-[#5B4FE8] hover:underline">Смотреть все задачи</a>
                </div>

                @if($pendingApprovals->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-gray-500">Нет задач, требующих действий.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                                    <th class="text-left px-5 py-3 font-semibold">Документ</th>
                                    <th class="text-left px-5 py-3 font-semibold">Назначен</th>
                                    <th class="text-left px-5 py-3 font-semibold">Статус</th>
                                    <th class="text-left px-5 py-3 font-semibold">Действие</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($pendingApprovals as $item)
                                    @php $doc = $item['document']; @endphp
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                </div>
                                                <div>
                                                    <a href="{{ route('documents.show', $doc->id) }}" class="font-medium text-gray-900 hover:text-[#5B4FE8]">
                                                        {{ Str::limit($doc->title, 35) }}
                                                    </a>
                                                    <p class="text-xs text-gray-500">{{ $doc->type?->name }} • {{ $doc->id }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="text-xs text-gray-600">
                                                {{ $item['deadline'] ? $item['deadline']->format('d M, H:i') : '—' }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            @if($item['is_overdue'])
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded bg-red-100 text-red-700">ПЕРЕРАСХОД</span>
                                            @elseif($item['status'] === 'in_progress')
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-700">В ОЖИДАНИИ</span>
                                            @else
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-700">ГОТОВО</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <a href="{{ route('documents.show', $doc->id) }}" class="text-[#5B4FE8] text-xs font-medium hover:underline">
                                                Просмотр →
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Activity sidebar --}}
        <div class="w-full md:w-72 shrink-0">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Последняя активность</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($activity as $log)
                        <div class="px-5 py-3.5 flex gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden shrink-0 mt-0.5">
                                @if($log->user)
                                    <img src="{{ $log->user->avatar_url }}" alt="{{ $log->user->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full bg-gray-200 flex items-center justify-center text-xs text-gray-500">S</div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-700 leading-snug">
                                    <span class="font-medium">{{ $log->user?->name ?? 'Система' }}</span>
                                    — {{ $log->action }}
                                </p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ $log->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center text-xs text-gray-400">Активности нет</div>
                    @endforelse
                </div>
                @if($activity->count() >= 10)
                    <div class="px-5 py-3 border-t border-gray-100">
                        <button class="text-xs text-[#5B4FE8] font-medium hover:underline w-full text-center uppercase tracking-wide">
                            Загрузить больше активности
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
