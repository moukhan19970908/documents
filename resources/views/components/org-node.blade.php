@props(['dept', 'depth' => 0])

@php
    $hasChildren = $dept->children_tree->isNotEmpty();
    $childCount  = $dept->children_tree->count();
    $userCount   = $dept->users->count();
    $isRoot      = $depth === 0;
    $isLevelOne  = $depth === 1;
    $isBitrix    = !empty($dept->bitrix24_department_id);

    $cardClass = match(true) {
        $isRoot     => 'bg-amber-50 border-amber-200',
        $isLevelOne => 'bg-cyan-50 border-cyan-200',
        default     => 'bg-white border-gray-200',
    };

    $btnClass = match(true) {
        $isRoot     => 'border-amber-200 text-amber-600 hover:bg-amber-100',
        $isLevelOne => 'border-cyan-200 text-cyan-600 hover:bg-cyan-100',
        default     => 'border-gray-100 text-indigo-500 hover:bg-indigo-50',
    };

    $iconBg    = $isRoot ? 'bg-amber-200' : ($isLevelOne ? 'bg-cyan-200' : 'bg-gray-100');
    $iconColor = $isRoot ? 'text-amber-700' : ($isLevelOne ? 'text-cyan-700' : 'text-gray-500');

    $roleMap   = ['admin' => 'Администратор', 'director' => 'Директор', 'linear' => 'Сотрудник', 'archiver' => 'Архивариус'];
    $childWord = $childCount === 1 ? 'отдел' : ($childCount < 5 ? 'отдела' : 'отделов');
    $line      = '#67e8f9';
@endphp

<div x-data="{ open: true }" class="flex flex-col items-center">

    {{-- Card --}}
    <div class="w-[200px] rounded-xl border shadow-sm {{ $cardClass }} text-left overflow-hidden flex-shrink-0">
        <div class="px-3 pt-3 flex items-start justify-between gap-1">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 {{ $iconBg }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 {{ $iconColor }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            @if($isBitrix)
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-600 leading-none flex-shrink-0">Б24</span>
            @endif
        </div>

        <div class="px-3 pt-1.5">
            <p class="text-xs font-bold text-gray-800 leading-snug line-clamp-2">{{ $dept->name }}</p>
        </div>

        <div class="px-3 pt-2 flex items-center gap-2 min-h-[36px]">
            @if($dept->head)
                <div class="w-7 h-7 rounded-full overflow-hidden flex-shrink-0 bg-indigo-100 flex items-center justify-center border border-white shadow-sm">
                    @if($dept->head->avatar)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($dept->head->avatar) }}" alt="{{ $dept->head->name }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-[10px] font-bold text-indigo-600">{{ mb_strtoupper(mb_substr($dept->head->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-700 truncate">{{ $dept->head->name }}</p>
                    <p class="text-[10px] text-gray-400 truncate">{{ $dept->head->position ?? $roleMap[$dept->head->role] ?? 'Сотрудник' }}</p>
                </div>
            @else
                <p class="text-[10px] text-gray-300 italic">Руководитель не назначен</p>
            @endif
        </div>

        <div class="px-3 pt-2 pb-1 space-y-0.5">
            <p class="text-[10px] text-gray-400">Подразделения: <span class="font-semibold text-gray-600">{{ $childCount }}</span></p>

            {{-- Employees count with hover popover (fixed position to escape overflow-hidden) --}}
            @if($userCount > 0)
                <div
                    x-data="{ show: false, top: 0, left: 0 }"
                    class="relative"
                    @mouseenter="
                        const r = $el.getBoundingClientRect();
                        top  = r.bottom + 6;
                        left = r.left;
                        show = true;
                    "
                    @mouseleave="show = false"
                >
                    <p class="text-[10px] text-gray-400 cursor-default underline decoration-dotted decoration-gray-300">
                        <span class="font-semibold text-gray-600">{{ $userCount }}</span> сотрудников
                    </p>

                    {{-- Popover rendered at body level via fixed position --}}
                    <div
                        x-show="show"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        :style="`display:none; position:fixed; top:${top}px; left:${left}px; z-index:9999; min-width:230px; max-width:280px;`"
                        @mouseenter="show = true"
                        @mouseleave="show = false"
                    >
                        <div class="bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
                            <div class="px-3 py-2 border-b border-gray-100 bg-gray-50">
                                <p class="text-[11px] font-semibold text-gray-600">{{ $dept->name }}</p>
                            </div>
                            <ul class="py-1 max-h-64 overflow-y-auto">
                                @foreach($dept->users as $u)
                                    <li class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50">
                                        <div class="w-7 h-7 rounded-full overflow-hidden flex-shrink-0 bg-indigo-100 flex items-center justify-center">
                                            @if($u->avatar)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($u->avatar) }}" alt="{{ $u->name }}" class="w-full h-full object-cover">
                                            @else
                                                <span class="text-[10px] font-bold text-indigo-600">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[12px] font-medium text-gray-800 truncate">{{ $u->name }}</p>
                                            <p class="text-[10px] text-gray-400">{{ $roleMap[$u->role] ?? 'Сотрудник' }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-[10px] text-gray-400"><span class="font-semibold text-gray-600">0</span> сотрудников</p>
            @endif
        </div>

        @if($hasChildren)
            <button @click="open = !open"
                class="w-full border-t px-3 py-1.5 flex items-center justify-center gap-1 text-[11px] font-medium transition-colors {{ $btnClass }}">
                <span x-text="open ? '{{ $childCount }} {{ $childWord }} &uarr;' : '{{ $childCount }} {{ $childWord }} &darr;'"></span>
            </button>
        @else
            <div class="px-3 py-1.5 border-t border-gray-100 text-center">
                <p class="text-[10px] text-gray-300">нет подразделений</p>
            </div>
        @endif
    </div>

    @if($hasChildren)
        {{-- Vertical stub from card bottom to horizontal bar --}}
        <div x-show="open" style="width:1px; height:24px; background:{{ $line }}; flex-shrink:0;"></div>

        {{-- Children row --}}
        <div x-show="open" class="flex items-start">
            @foreach($dept->children_tree as $child)
                <div class="flex flex-col items-center" style="padding: 0 16px;">
                    {{-- T-connector: horizontal arms + vertical stub --}}
                    <div class="relative flex justify-center" style="width:100%; height:24px;">
                        @if(!$loop->first)
                            <div style="position:absolute; left:0; right:50%; top:0; height:1px; background:{{ $line }};"></div>
                        @endif
                        @if(!$loop->last)
                            <div style="position:absolute; left:50%; right:0; top:0; height:1px; background:{{ $line }};"></div>
                        @endif
                        <div style="width:1px; height:24px; background:{{ $line }};"></div>
                    </div>
                    <x-org-node :dept="$child" :depth="$depth + 1" />
                </div>
            @endforeach
        </div>
    @endif

</div>
