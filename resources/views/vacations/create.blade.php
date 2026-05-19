<x-app-layout>
    <x-slot name="title">Новая заявка на отпуск — Vamin</x-slot>

    <div class="max-w-xl" x-data="vacationCreate()">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('vacations.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Новая заявка на отпуск</h1>
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

        <form action="{{ route('vacations.store') }}" method="POST" class="space-y-5">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Вид отпуска *</label>
                    <select name="vacation_type" required
                            class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8] bg-white">
                        <option value="annual" {{ old('vacation_type') === 'annual' ? 'selected' : '' }}>Ежегодный оплачиваемый</option>
                        <option value="unpaid" {{ old('vacation_type') === 'unpaid' ? 'selected' : '' }}>За свой счёт</option>
                        <option value="sick_leave" {{ old('vacation_type') === 'sick_leave' ? 'selected' : '' }}>Больничный</option>
                        <option value="other" {{ old('vacation_type') === 'other' ? 'selected' : '' }}>Иное</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Дата начала *</label>
                        <input type="date" name="date_start" x-model="dateStart"
                               value="{{ old('date_start') }}" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Дата окончания *</label>
                        <input type="date" name="date_end" x-model="dateEnd"
                               value="{{ old('date_end') }}" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                </div>
                <div class="bg-[#5B4FE8]/5 rounded-xl px-4 py-3 flex items-center justify-between text-sm">
                    <span class="text-gray-600">Количество дней</span>
                    <span class="font-bold text-[#5B4FE8]" x-text="days + ' дн.'"></span>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Комментарий</label>
                    <textarea name="comment" rows="2"
                              class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                              placeholder="При необходимости укажите дополнительную информацию...">{{ old('comment') }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" name="submit" value="1"
                        class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Отправить на согласование
                </button>
                <button type="submit" name="submit" value="0"
                        class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                    Сохранить черновик
                </button>
                <a href="{{ route('vacations.index') }}" class="px-6 py-2.5 text-gray-500 text-sm hover:text-gray-700">Отмена</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    function vacationCreate() {
        return {
            dateStart: '{{ old('date_start', '') }}',
            dateEnd: '{{ old('date_end', '') }}',
            get days() {
                if (!this.dateStart || !this.dateEnd) return 0;
                const d = Math.round((new Date(this.dateEnd) - new Date(this.dateStart)) / 86400000) + 1;
                return d > 0 ? d : 0;
            }
        };
    }
    </script>
    @endpush
</x-app-layout>
