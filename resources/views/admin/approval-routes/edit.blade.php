<x-app-layout>
    <x-slot name="title">Изменить маршрут — Vamin</x-slot>

    @php
        $initSteps = $route->steps->map(fn($s) => [
            'approver_role_level' => $s->approver_role_level,
            'approver_user_id' => $s->approver_user_id ?? '',
        ])->values()->toArray();
        if (empty($initSteps)) {
            $initSteps = [['approver_role_level' => 1, 'approver_user_id' => '']];
        }
    @endphp

    <div class="max-w-2xl" x-data="routeForm({ steps: {{ json_encode($initSteps) }} })">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('admin.approval-routes.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Изменить маршрут</h1>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.approval-routes.update', $route) }}" method="POST" class="space-y-5">
            @csrf @method('PUT')

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Название *</label>
                    <input type="text" name="name" value="{{ old('name', $route->name) }}" required
                           class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Тип заявок *</label>
                        <select name="request_type" required
                                class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                            <option value="trip" {{ old('request_type', $route->request_type) === 'trip' ? 'selected' : '' }}>Командировки</option>
                            <option value="vacation" {{ old('request_type', $route->request_type) === 'vacation' ? 'selected' : '' }}>Отпуска</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Отдел (необязательно)</label>
                        <select name="department_id"
                                class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                            <option value="">Все отделы</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('department_id', $route->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ old('is_active', $route->is_active) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]">
                    <label for="is_active" class="text-sm text-gray-600">Активен</label>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xs font-semibold text-gray-600 uppercase tracking-widest">Шаги согласования</h2>
                    <button type="button" @click="addStep()"
                            class="flex items-center gap-1 text-xs text-[#5B4FE8] hover:text-indigo-700 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Добавить шаг
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(step, i) in steps" :key="i">
                        <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
                            <span class="text-xs font-bold text-gray-400 w-5" x-text="i+1 + '.'"></span>
                            <div class="flex-1 grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Уровень роли</label>
                                    <select :name="`steps[${i}][approver_role_level]`" x-model="step.approver_role_level"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                                        @foreach(range(1, 7) as $lvl)
                                            <option value="{{ $lvl }}">Уровень {{ $lvl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Или конкретный сотрудник</label>
                                    <select :name="`steps[${i}][approver_user_id]`" x-model="step.approver_user_id"
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                                        <option value="">По уровню роли</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="button" @click="removeStep(i)" x-show="steps.length > 1"
                                    class="text-gray-300 hover:text-red-400 transition-colors ml-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Сохранить
                </button>
                <a href="{{ route('admin.approval-routes.index') }}" class="px-6 py-2.5 text-gray-500 text-sm hover:text-gray-700">Отмена</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    function routeForm({ steps }) {
        return {
            steps,
            addStep() { this.steps.push({ approver_role_level: 1, approver_user_id: '' }); },
            removeStep(i) { if (this.steps.length > 1) this.steps.splice(i, 1); }
        };
    }
    </script>
    @endpush
</x-app-layout>
