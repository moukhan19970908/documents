{{-- Выбор соисполнителей и контролёра. Требует $settings, $executors (пул), $people (контролёры). --}}
@if($settings->coexecutors_enabled || $settings->controller_enabled)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @if($settings->coexecutors_enabled)
            <div>
                <label class="text-xs text-gray-500 block mb-1">Соисполнители</label>
                <div class="border border-gray-200 rounded-lg max-h-40 overflow-y-auto divide-y divide-gray-50">
                    @forelse($executors as $e)
                        <label class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                            <input type="checkbox" name="co_executors[]" value="{{ $e->id }}" class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            {{ $e->name }}
                        </label>
                    @empty
                        <p class="px-3 py-2 text-xs text-gray-400">Нет доступных сотрудников</p>
                    @endforelse
                </div>
                <p class="text-xs text-gray-400 mt-1">Помогают исполнять: видят узел, берут в работу, прикладывают файлы.</p>
            </div>
        @endif

        @if($settings->controller_enabled)
            <div>
                <label class="text-xs text-gray-500 block mb-1">Контролёр</label>
                <select name="controller_id"
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    <option value="">— без контролёра —</option>
                    @foreach($people as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Надзор: видит узел и получает уведомления о сдаче и приёмке.</p>
            </div>
        @endif
    </div>
@endif
