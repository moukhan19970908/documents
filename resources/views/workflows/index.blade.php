<x-app-layout>
    <x-slot name="title">Маршруты согласования — Vamin</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Маршруты согласования</h1>
            <p class="text-sm text-gray-500 mt-1">Настройка процессов для типов документов</p>
        </div>
        <a href="{{ route('workflows.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новый маршрут
        </a>
    </div>

    <div class="space-y-4">
        @forelse($workflows as $wf)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="font-semibold text-gray-900">{{ $wf->name }}</h2>
                        @if($wf->description)
                            <p class="text-sm text-gray-500 mt-0.5">{{ $wf->description }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('workflows.builder', $wf) }}" class="text-xs font-medium text-[#5B4FE8] border border-[#5B4FE8] px-3 py-1.5 rounded-lg hover:bg-indigo-50">Редактировать</a>
                        <form action="{{ route('workflows.destroy', $wf) }}" method="POST" onsubmit="return confirm('Удалить маршрут?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50">Удалить</button>
                        </form>
                    </div>
                </div>
                {{-- Stages --}}
                <div class="flex items-center gap-2 flex-wrap">
                    @foreach($wf->stages->sortBy('order') as $i => $stage)
                        @if($i > 0)
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        @endif
                        <div class="flex flex-col items-center">
                            <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-1.5 text-xs font-medium text-indigo-700">
                                {{ $stage->name }}
                            </div>
                            <span class="text-xs text-gray-400 mt-1">{{ $stage->approvers_count }} {{ trans_choice('согласующий|согласующих|согласующих', $stage->approvers_count) }}</span>
                        </div>
                    @endforeach
                    @if($wf->stages->isEmpty())
                        <p class="text-sm text-gray-400">Нет этапов — <a href="{{ route('workflows.builder', $wf) }}" class="text-[#5B4FE8] hover:underline">добавить</a></p>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
                <p class="text-gray-500">Маршруты ещё не созданы</p>
            </div>
        @endforelse
    </div>
</x-app-layout>
