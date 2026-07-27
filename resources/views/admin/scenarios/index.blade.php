<x-app-layout>
    <x-slot name="title">Конструктор процессов — Vamin</x-slot>

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Конструктор процессов</h1>
            <p class="text-sm text-gray-500 mt-1">Сценарий описывает маршрут документа: параметры запуска, звенья и права.</p>
        </div>
        <a href="{{ route('admin.scenarios.create') }}" class="flex items-center gap-2 bg-[#5B4FE8] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Новый сценарий
        </a>
    </div>

    {{-- Фильтр по направлениям и отделам --}}
    @if($directions->isNotEmpty())
        <form method="GET" class="mb-5 flex items-end gap-3">
            <div class="w-64">
                <label class="text-xs text-gray-500 font-medium block mb-1">Направление</label>
                <select name="direction" onchange="this.form.querySelector('[name=department]')?.remove(); this.form.submit()"
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <option value="">Все направления</option>
                    @foreach($directions as $dir)
                        <option value="{{ $dir->id }}" {{ $directionId === $dir->id ? 'selected' : '' }}>{{ $dir->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($directionId && $departments->isNotEmpty())
                <div class="w-64">
                    <label class="text-xs text-gray-500 font-medium block mb-1">Отдел</label>
                    <select name="department" onchange="this.form.submit()"
                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <option value="">Все отделы направления</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ $departmentId === $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if($directionId)
                <a href="{{ route('admin.scenarios.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Сбросить</a>
            @endif
        </form>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($scenarios as $scenario)
            <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#5B4FE8]/8 text-[#5B4FE8] flex items-center justify-center shrink-0">
                        @include('admin.partials.scenario-icon', ['icon' => $scenario->icon, 'class' => 'w-4 h-4'])
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-gray-900 leading-snug">{{ $scenario->name }}</h2>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            @if($scenario->status === 'draft')
                                <span class="bg-amber-50 text-amber-600 text-xs font-medium px-2 py-0.5 rounded">Черновик</span>
                            @else
                                <span class="bg-emerald-50 text-emerald-600 text-xs font-medium px-2 py-0.5 rounded">Опубликован</span>
                            @endif
                            @if($scenario->process_type)
                                <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded">{{ \App\Models\Workflow::PROCESS_TYPES[$scenario->process_type] ?? $scenario->process_type }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-400 mt-3">
                    {{ $scenario->stages_count }} звеньев
                    · {{ $scenario->parameters->count() }} параметров
                    @if($scenario->versions->isNotEmpty())
                        · версия <span class="font-mono">v{{ $scenario->versions->first()->version_label }}</span>
                    @endif
                    @if($scenario->owner) · владелец: {{ $scenario->owner->name }} @endif
                </p>

                @if($scenario->subtypes->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach($scenario->subtypes as $subtype)
                            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">
                                {{ $subtype->type?->code ? '[' . $subtype->type->code . '] ' : '' }}{{ $subtype->name }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-amber-600 mt-2">Не привязан к подтипу — не подставится автоматически</p>
                @endif

                <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                    <a href="{{ route('admin.scenarios.edit', $scenario) }}" class="text-xs font-medium text-[#5B4FE8] border border-[#5B4FE8] px-3 py-1.5 rounded-lg hover:bg-indigo-50">Изменить</a>
                    <form action="{{ route('admin.scenarios.destroy', $scenario) }}" method="POST" onsubmit="return confirm('Удалить сценарий?')" class="ml-auto">
                        @csrf @method('DELETE')
                        <button class="text-xs font-medium text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50">Удалить</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-500">Сценариев пока нет</div>
        @endforelse
    </div>

    @if($scenarios->hasPages())
        <div class="mt-6">{{ $scenarios->links() }}</div>
    @endif
</x-app-layout>
