{{-- Строка реестра в списке «Реестры в пути». $reg — массив из RequestsController::inTransit --}}
<div class="flex items-center gap-4 bg-white border border-gray-200 rounded-xl px-5 py-3.5">
    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $reg['type'] === 'trip' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="font-medium text-gray-900 truncate">{{ $reg['title'] }}</p>
        <p class="text-xs text-gray-400">
            {{ $reg['type_label'] }} · {{ $reg['count'] }} заявок@if($reg['role'] === 'decide' && $reg['creator']) · собрал {{ $reg['creator'] }}@endif
        </p>
    </div>
    <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $reg['color'] }}">{{ $reg['status'] }}</span>
    <a href="{{ $reg['url'] }}" class="shrink-0 px-3.5 py-1.5 rounded-lg text-sm transition {{ $reg['role'] === 'decide' ? 'bg-[#5B4FE8] text-white hover:bg-indigo-700' : 'border border-gray-200 text-gray-700 hover:border-[#5B4FE8]/40 hover:text-[#5B4FE8]' }}">
        {{ $reg['role'] === 'decide' ? 'Открыть и решить' : 'Открыть' }}
    </a>
</div>
