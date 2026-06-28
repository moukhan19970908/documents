<x-app-layout>
    <x-slot name="title">Редактировать командировку T-{{ $trip->id }} — Vamin</x-slot>

    @php
        $initType = old('city_type', $trip->location_type ?? '');
        $initName = old('city_name', in_array($trip->location_type, ['other_rf', 'abroad']) ? $trip->city : '');
    @endphp

    <div class="max-w-2xl" x-data="tripCreate(@js($norms), {
        cityType: '{{ $initType }}',
        cityName: @js($initName),
        dateStart: '{{ old('date_start', $trip->date_start->format('Y-m-d')) }}',
        dateEnd: '{{ old('date_end', $trip->date_end->format('Y-m-d')) }}',
        dailyRate: {{ old('daily_rate', $trip->daily_rate) }},
        accommodation: {{ old('accommodation_total', $trip->accommodation_total) }},
    })">
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('trips.show', $trip) }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Командировка T-{{ $trip->id }}</h1>
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

        <form action="{{ route('trips.update', $trip) }}" method="POST" class="space-y-5">
            @csrf @method('PUT')

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Направление *</label>
                        <select name="city_type" x-model="cityType" @change="onCityChange()" required
                                class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            <option value="">— выберите —</option>
                            <option value="moscow">Москва</option>
                            <option value="spb">Санкт-Петербург</option>
                            <option value="sochi">Сочи</option>
                            <option value="other_rf">Другие города РФ</option>
                            <option value="abroad">За границей</option>
                        </select>
                    </div>
                    <div class="col-span-2" x-show="needsCityInput" x-cloak>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Город *</label>
                        <input type="text" name="city_name" x-model="cityName" x-bind:required="needsCityInput"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]"
                               placeholder="Введите город">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Дата начала *</label>
                        <input type="date" name="date_start" x-model="dateStart" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Дата окончания *</label>
                        <input type="date" name="date_end" x-model="dateEnd" required
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Цель поездки *</label>
                        <textarea name="purpose" rows="2" required
                                  class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('purpose', $trip->purpose) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold text-gray-600 uppercase tracking-widest">Расходы</h2>
                    <span class="text-xs text-gray-400">Категория: <span class="font-medium text-gray-600">{{ $norms['label'] }}</span></span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Суточные (₽/день)</label>
                        <input type="number" name="daily_rate" x-model="dailyRate" min="0" step="0.01"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Проживание (₽)</label>
                        <input type="number" name="accommodation_total" x-model="accommodation" min="0" step="0.01"
                               class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <p class="text-xs text-gray-400 mt-1" x-text="accommodationHint" x-show="accommodationHint"></p>
                    </div>
                </div>

                <div class="bg-[#5B4FE8]/5 rounded-xl p-4 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Суточные × <span x-text="days" class="font-semibold text-gray-900"></span> дн.
                        + Проживание
                    </div>
                    <div class="text-lg font-bold text-[#5B4FE8]">
                        <span x-text="formatMoney(total)"></span> ₽
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1.5">Комментарий</label>
                <textarea name="comment" rows="2"
                          class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">{{ old('comment', $trip->comment) }}</textarea>
            </div>


            <div class="flex items-center gap-3">
                <button type="submit" name="submit" value="1"
                        class="px-6 py-2.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Сохранить и отправить
                </button>
                <button type="submit" name="submit" value="0"
                        class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                    Сохранить черновик
                </button>
                <a href="{{ route('trips.show', $trip) }}" class="px-6 py-2.5 text-gray-500 text-sm hover:text-gray-700">Отмена</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    function tripCreate(norms, initial) {
        return {
            norms: norms,
            cityType: initial.cityType || '',
            cityName: initial.cityName || '',
            dateStart: initial.dateStart || '',
            dateEnd: initial.dateEnd || '',
            dailyRate: parseFloat(initial.dailyRate) || 0,
            accommodation: parseFloat(initial.accommodation) || 0,

            init() {
                this.$watch('dateStart', () => this.syncAccommodation());
                this.$watch('dateEnd', () => this.syncAccommodation());
            },

            get needsCityInput() {
                return this.cityType === 'other_rf' || this.cityType === 'abroad';
            },

            get nightlyLimit() {
                if (!this.cityType) return null;
                const v = this.norms.nightly[this.cityType];
                return (v === null || v === undefined) ? null : parseFloat(v);
            },

            get days() {
                if (!this.dateStart || !this.dateEnd) return 0;
                const s = new Date(this.dateStart);
                const e = new Date(this.dateEnd);
                const d = Math.round((e - s) / 86400000) + 1;
                return d > 0 ? d : 0;
            },

            get total() {
                return (parseFloat(this.dailyRate) || 0) * this.days
                     + (parseFloat(this.accommodation) || 0);
            },

            get accommodationHint() {
                if (!this.cityType) return '';
                if (this.cityType === 'abroad') return 'За границей — введите сумму вручную.';
                if (this.nightlyLimit === null) return 'По фактическим расходам.';
                return 'Норма: ' + this.formatMoney(this.nightlyLimit) + ' ₽/сутки';
            },

            onCityChange() {
                if (!this.cityType || this.cityType === 'abroad') return;
                this.dailyRate = this.norms.dailyRate;
                this.syncAccommodation();
            },

            syncAccommodation() {
                if (!this.cityType || this.cityType === 'abroad') return;
                if (this.nightlyLimit === null) return;
                this.accommodation = this.nightlyLimit * this.days;
            },

            formatMoney(val) {
                return new Intl.NumberFormat('ru-RU').format(Math.round(val));
            }
        };
    }
    </script>
    @endpush
</x-app-layout>
