<x-app-layout>
    <x-slot name="title">Мои задачи — Vamin</x-slot>

    @php
        $kindMeta = [
            'dorabotka' => ['label' => 'Доработка',    'btn' => 'Доработать',   'color' => '#F97316', 'icon' => 'edit'],
            'soglas'    => ['label' => 'Согласование', 'btn' => 'Согласовать',  'color' => '#3B82F6', 'icon' => 'check'],
            'oznak'     => ['label' => 'Ознакомление', 'btn' => 'Ознакомиться', 'color' => '#F59E0B', 'icon' => 'eye'],
            'priem'     => ['label' => 'Приёмка',      'btn' => 'Принять',      'color' => '#8B5CF6', 'icon' => 'inbox'],
        ];

        $tabDefs = [
            'all'       => 'Все',
            'dorabotka' => 'Доработать',
            'soglas'    => 'Согласовать',
            'oznak'     => 'Ознакомиться',
            'priem'     => 'Принять',
            'overdue'   => 'Просрочено',
        ];

        $counts = ['all' => $items->count(), 'dorabotka' => 0, 'soglas' => 0, 'oznak' => 0, 'priem' => 0, 'overdue' => 0];
        foreach ($items as $it) {
            $counts[$it['kind']]++;
            if ($it['is_overdue']) { $counts['overdue']++; }
        }
    @endphp

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900">Мои задачи</h1>
                <span class="bg-[#5B4FE8]/10 text-[#5B4FE8] text-sm font-bold px-2.5 py-0.5 rounded-full">{{ $items->count() }}</span>
            </div>
            <p class="text-sm text-gray-500 mt-1">Документы, ожидающие вашего действия</p>
        </div>

        @if($canSeeAll)
            <div class="inline-flex bg-white border border-gray-200 rounded-lg p-1 text-sm shrink-0">
                <a href="{{ request()->fullUrlWithQuery(['scope' => 'mine']) }}"
                   class="px-3 py-1.5 rounded-md font-medium transition-colors {{ $scope === 'mine' ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:text-gray-900' }}">Обычный</a>
                <a href="{{ request()->fullUrlWithQuery(['scope' => 'all']) }}"
                   class="px-3 py-1.5 rounded-md font-medium transition-colors {{ $scope === 'all' ? 'bg-[#5B4FE8] text-white' : 'text-gray-600 hover:text-gray-900' }}">Администратор</a>
            </div>
        @endif
    </div>

    <div x-data="{ tab: 'all', show(kind, overdue) { return this.tab === 'all' || (this.tab === 'overdue' ? overdue : this.tab === kind); } }">
        {{-- Filter tabs --}}
        <div class="flex items-center gap-1 mb-4 border-b border-gray-200 overflow-x-auto">
            @foreach($tabDefs as $key => $label)
                @if(in_array($key, ['all', 'overdue']) || $counts[$key] > 0)
                    <button type="button" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? '{{ $key === 'overdue' ? 'text-red-600 border-red-500' : 'text-[#5B4FE8] border-[#5B4FE8]' }}' : 'text-gray-500 border-transparent hover:text-gray-800'"
                            class="flex items-center gap-1.5 px-3 py-2.5 -mb-px border-b-2 text-sm font-medium whitespace-nowrap transition-colors">
                        {{ $label }}
                        <span :class="tab === '{{ $key }}' ? '{{ $key === 'overdue' ? 'bg-red-100 text-red-600' : 'bg-[#5B4FE8]/10 text-[#5B4FE8]' }}' : 'bg-gray-100 text-gray-500'"
                              class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-semibold rounded-full transition-colors">{{ $counts[$key] }}</span>
                    </button>
                @endif
            @endforeach
        </div>

        {{-- Cards --}}
        <div class="space-y-3">
            @forelse($items as $item)
                @php
                    $kind = $item['kind'];
                    $meta = $kindMeta[$kind];
                    $abbr = $item['abbr'];
                    $link = $item['link'];

                    $dl = $item['deadline'];
                    if ($item['is_overdue']) {
                        $dText = 'Просрочено'; $dCls = 'text-red-600'; $dWarn = true;
                    } elseif ($dl && $dl->isToday()) {
                        $dText = 'Срок сегодня'; $dCls = 'text-amber-600'; $dWarn = false;
                    } elseif ($dl && $dl->isTomorrow()) {
                        $dText = 'Срок завтра'; $dCls = 'text-amber-600'; $dWarn = false;
                    } elseif ($dl) {
                        $dText = 'Срок ' . $dl->translatedFormat('j F'); $dCls = 'text-gray-500'; $dWarn = false;
                    } else {
                        $dText = null;
                    }

                    $showAssignee = $item['assignee'] && $item['assignee']->id !== $item['initiator']->id;
                @endphp
                <div x-show="show('{{ $kind }}', {{ $item['is_overdue'] ? 'true' : 'false' }})"
                     class="bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 transition-colors">
                    <div class="flex items-start gap-4">
                        {{-- Kind icon --}}
                        <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                             style="background: {{ $meta['color'] }}1A; color: {{ $meta['color'] }}">
                            @switch($meta['icon'])
                                @case('edit')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    @break
                                @case('eye')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    @break
                                @case('inbox')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    @break
                                @default
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @endswitch
                        </div>

                        {{-- Main --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-semibold text-gray-400">[{{ $abbr }}]</span>
                                <a href="{{ $link }}" class="font-semibold text-gray-900 hover:text-[#5B4FE8] truncate">{{ $item['title'] }}</a>
                                <span class="px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap"
                                      style="background: {{ $meta['color'] }}1A; color: {{ $meta['color'] }}">{{ $meta['label'] }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ $item['ref'] }}@if($item['stage_label']) · {{ $item['stage_label'] }}@endif
                            </p>
                            <div class="flex items-center gap-x-4 gap-y-1 mt-2 text-xs text-gray-500 flex-wrap">
                                <span class="flex items-center gap-1.5">
                                    <img src="{{ $item['initiator']->avatar_url }}" class="w-4 h-4 rounded-full" alt="">
                                    <span class="text-gray-400">Инициатор:</span> {{ $item['initiator']->name }}
                                </span>
                                @if($showAssignee)
                                    <span class="flex items-center gap-1.5">
                                        <img src="{{ $item['assignee']->avatar_url }}" class="w-4 h-4 rounded-full" alt="">
                                        <span class="text-gray-400">Исполнитель:</span> {{ $item['assignee']->name }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Right: deadline + actions --}}
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            @if($dText)
                                <span class="flex items-center gap-1 text-xs font-medium {{ $dCls }}">
                                    @if($dWarn ?? false)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @endif
                                    {{ $dText }}
                                </span>
                            @endif
                            <div class="flex items-center gap-2">
                                <a href="{{ $link }}"
                                   class="px-4 py-1.5 rounded-lg text-sm font-medium text-white hover:opacity-90 transition-opacity"
                                   style="background: {{ $meta['color'] }}">{{ $meta['btn'] }}</a>
                                <a href="{{ $link }}"
                                   class="px-4 py-1.5 rounded-lg text-sm font-medium border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors">Открыть</a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p class="text-gray-600 font-medium">Нет активных задач</p>
                    <p class="text-gray-400 text-sm mt-1">Все документы обработаны</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
