<x-app-layout>
    <x-slot name="title">Исполнители заданий командировок — Vamin</x-slot>

    <div class="max-w-2xl">
        <div class="mb-6">
            <a href="{{ route('trips.tasks.index') }}" class="text-xs text-gray-400 hover:text-gray-600">← Задания</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Исполнители заданий</h1>
            <p class="text-sm text-gray-500 mt-1">Кому уходят порождаемые задания командировок (ТЗ 18.3).</p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.trip-tasks.settings.update') }}">
            @csrf @method('PUT')

            <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
                @php
                    $rows = [
                        'hr_user_id'        => ['Выписать деньги, зафиксировать', 'Отдел кадров'],
                        'office_manager_id' => ['Подобрать тур: бронь, билеты',   'Офис-менеджер'],
                        'logistics_id'      => ['Выдать топливную карту (транспорт свой)', 'Директор по логистике'],
                        'transport_id'      => ['Выделить служебный автомобиль (транспорт организации)', 'Транспортный отдел'],
                    ];
                @endphp
                @foreach($rows as $field => [$task, $who])
                    <div class="px-5 py-4 grid grid-cols-1 md:grid-cols-2 gap-3 items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $task }}</p>
                            <p class="text-xs text-gray-400">{{ $who }}</p>
                        </div>
                        <select name="{{ $field }}" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option value="">— не назначен —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" @selected($settings->{$field} == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end mt-4">
                <button class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сохранить</button>
            </div>
        </form>
    </div>
</x-app-layout>
