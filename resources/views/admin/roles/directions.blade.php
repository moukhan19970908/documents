<x-app-layout>
    <x-slot name="title">Направления — Vamin</x-slot>

    @php
        // Уровни доступа департамента → человекочитаемая «область по умолчанию».
        $scopeLabels = [
            'full'       => 'компания',
            'department' => 'направление',
            'own'        => 'подчинённые',
            'none'       => 'нет',
            null         => 'не задана',
        ];
        $plural = fn (int $n) => $n % 10 === 1 && $n % 100 !== 11
            ? 'отдел'
            : (in_array($n % 10, [2, 3, 4]) && !in_array($n % 100, [12, 13, 14]) ? 'отдела' : 'отделов');
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Роли и доступы</h1>
    </div>

    @include('admin.roles.partials.tabs')

    <div class="max-w-5xl space-y-3">
        @forelse($directions as $direction)
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 flex items-center gap-5">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5B4FE8] flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>

                <div class="w-48 shrink-0">
                    <h2 class="font-semibold text-gray-900 truncate">{{ $direction->name }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $direction->children_count }} {{ $plural($direction->children_count) }}</p>
                </div>

                <div class="w-56 shrink-0 flex items-center gap-2.5">
                    @if($direction->head)
                        <img src="{{ $direction->head->avatar_url }}" alt="" class="w-7 h-7 rounded-full shrink-0">
                        <span class="text-sm text-gray-700 truncate">{{ $direction->head->name }}</span>
                    @else
                        <span class="w-7 h-7 rounded-full bg-gray-100 text-gray-300 flex items-center justify-center text-sm shrink-0">—</span>
                        <span class="text-sm text-gray-400">— не назначен —</span>
                    @endif
                </div>

                <p class="flex-1 text-sm text-[#5B4FE8] leading-relaxed">
                    Область по умолчанию: заявки — {{ $scopeLabels[$direction->tasks_access_level] }},
                    архив — {{ $scopeLabels[$direction->archive_access_level] }}
                </p>

                <label class="flex items-center gap-2 shrink-0 cursor-pointer">
                    <input type="checkbox" class="w-5 h-5 rounded accent-[#5B4FE8]">
                    <span class="text-sm text-gray-700">Кросс-видимость</span>
                </label>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">
                Направления не найдены — заведите корневые департаменты в разделе «Оргструктура».
            </div>
        @endforelse
    </div>

    <p class="text-xs text-gray-400 mt-4 max-w-5xl">
        Направление — корневой департамент. Руководитель и область по умолчанию берутся из его настроек;
        флаг «Кросс-видимость» — верстка, он пока не сохраняется.
    </p>
</x-app-layout>
