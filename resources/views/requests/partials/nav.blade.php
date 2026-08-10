{{-- Общий верх раздела «Заявки»: заголовок, «Подать заявку», вкладки. $active ∈ mine|approve|tasks --}}
<div class="flex items-start justify-between gap-4 mb-5">
    <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>

    <div class="relative shrink-0" x-data="{ creating: false }" @click.outside="creating = false">
        <button type="button" @click="creating = !creating"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#5B4FE8] text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
            Подать заявку
        </button>
        <div x-show="creating" x-transition x-cloak
             class="absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded-xl shadow-lg p-1.5 z-20">
            <a href="{{ route('vacations.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <span><span class="block text-sm font-medium text-gray-900">Отпуск</span><span class="block text-xs text-gray-400">Ежегодный, за свой счёт, больничный</span></span>
            </a>
            <a href="{{ route('trips.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50">
                <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </span>
                <span><span class="block text-sm font-medium text-gray-900">Командировка</span><span class="block text-xs text-gray-400">Транспорт, проживание, регион</span></span>
            </a>
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg opacity-50 cursor-default">
                <span class="w-8 h-8 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
                </span>
                <span><span class="block text-sm font-medium text-gray-500">Иное</span><span class="block text-xs text-gray-400">Появится на следующем этапе</span></span>
            </div>
        </div>
    </div>
</div>

<div class="flex items-center gap-6 border-b border-gray-200 mb-5">
    <a href="{{ route('requests.index') }}"
       @class(['flex items-center gap-2 pb-3 -mb-px border-b-2 text-sm font-semibold transition',
               'border-[#5B4FE8] text-gray-900' => $active === 'mine',
               'border-transparent text-gray-500 hover:text-gray-800' => $active !== 'mine'])>
        Мои заявки
        <span class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500">{{ $counts['total'] }}</span>
    </a>
    @if($canSeeApprovals)
        <a href="{{ route('requests.approvals') }}"
           @class(['flex items-center gap-2 pb-3 -mb-px border-b-2 text-sm font-semibold transition',
                   'border-[#5B4FE8] text-gray-900' => $active === 'approve',
                   'border-transparent text-gray-500 hover:text-gray-800' => $active !== 'approve'])>
            На согласовании
            @if($pendingTotal > 0)<span class="text-xs px-1.5 py-0.5 rounded-full bg-[#5B4FE8] text-white">{{ $pendingTotal }}</span>@endif
        </a>
    @endif
    @if($canSeeTasks)
        <a href="{{ route('requests.tasks') }}"
           @class(['flex items-center gap-2 pb-3 -mb-px border-b-2 text-sm font-semibold transition',
                   'border-[#5B4FE8] text-gray-900' => $active === 'tasks',
                   'border-transparent text-gray-500 hover:text-gray-800' => $active !== 'tasks'])>
            Мои задания
            @if($tasksOpen > 0)<span class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500">{{ $tasksOpen }}</span>@endif
        </a>
    @endif
</div>

@isset($coveringFor)
    @if($coveringFor->isNotEmpty())
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3 mb-5 flex items-start gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span>
                Вы замещаете:
                {{ $coveringFor->map(fn ($s) => ($s->absent?->name ?? '—') . ' (до ' . $s->date_to->format('d.m') . ')')->join(', ') }}.
                Его входящие согласования и задания показаны здесь.
            </span>
        </div>
    @endif
@endisset
