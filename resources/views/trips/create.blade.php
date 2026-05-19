<x-app-layout>
    <x-slot name="title">Новая командировка — Vamin</x-slot>

    <div class="max-w-2xl" x-data="tripCreate()">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('trips.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Новая командировка</h1>
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

        <form action="{{ route('trips.store') }}" method="POST" class="space-y-5">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Город / Направление *</label>
                        <input type="text" name="city" value="{{ old('city') }}" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                               placeholder="Москва, Санкт-Петербург...">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Дата начала *</label>
                        <input type="date" name="date_start" id="date_start" x-model="dateStart"
                               value="{{ old('date_start') }}" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Дата окончания *</label>
                        <input type="date" name="date_end" id="date_end" x-model="dateEnd"
                               value="{{ old('date_end') }}" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Цель поездки *</label>
                        <textarea name="purpose" rows="2" required
                                  class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                                  placeholder="Переговоры, конференция, обучение...">{{ old('purpose') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <h2 class="text-xs font-semibold text-gray-600 uppercase tracking-widest">Расходы</h2>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Суточные (₽/день)</label>
                        <input type="number" name="daily_rate" id="daily_rate" x-model="dailyRate"
                               value="{{ old('daily_rate', 0) }}" min="0" step="0.01"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Проживание (₽)</label>
                        <input type="number" name="accommodation_total" id="accommodation" x-model="accommodation"
                               value="{{ old('accommodation_total', 0) }}" min="0" step="0.01"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Переезд (₽)</label>
                        <input type="number" name="transport_total" id="transport" x-model="transport"
                               value="{{ old('transport_total', 0) }}" min="0" step="0.01"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                </div>

                <div class="bg-[#5B4FE8]/5 rounded-xl p-4 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Суточные × <span x-text="days" class="font-semibold text-gray-900"></span> дн.
                        + Проживание + Переезд
                    </div>
                    <div class="text-lg font-bold text-[#5B4FE8]">
                        <span x-text="formatMoney(total)"></span> ₽
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Комментарий</label>
                <textarea name="comment" rows="2"
                          class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                          placeholder="Дополнительная информация...">{{ old('comment') }}</textarea>
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
                <a href="{{ route('trips.index') }}" class="px-6 py-2.5 text-gray-500 text-sm hover:text-gray-700">Отмена</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    function tripCreate() {
        return {
            dateStart: '{{ old('date_start', '') }}',
            dateEnd: '{{ old('date_end', '') }}',
            dailyRate: {{ old('daily_rate', 0) }},
            accommodation: {{ old('accommodation_total', 0) }},
            transport: {{ old('transport_total', 0) }},

            get days() {
                if (!this.dateStart || !this.dateEnd) return 0;
                const s = new Date(this.dateStart);
                const e = new Date(this.dateEnd);
                const d = Math.round((e - s) / 86400000) + 1;
                return d > 0 ? d : 0;
            },

            get total() {
                return (parseFloat(this.dailyRate) || 0) * this.days
                     + (parseFloat(this.accommodation) || 0)
                     + (parseFloat(this.transport) || 0);
            },

            formatMoney(val) {
                return new Intl.NumberFormat('ru-RU').format(Math.round(val));
            }
        };
    }
    </script>
    @endpush
</x-app-layout>
