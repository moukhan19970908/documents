<x-app-layout>
    <x-slot name="title">Нумерация — Vamin</x-slot>

    @php
        $resetLabels  = ['none' => 'Без сброса', 'monthly' => 'Ежемесячно', 'yearly' => 'Ежегодно (с 1 января)'];
        $momentLabels = ['on_launch' => 'При запуске', 'on_registration' => 'При регистрации', 'on_approval' => 'После согласования'];
        $icons = [
            'document'         => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'order'            => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
            'credit_committee' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        ];
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Нумерация</h1>
        <p class="text-sm text-gray-500 mt-1">Как формируется регистрационный номер для документа, приказа и кредитного комитета</p>
    </div>

    @if(session('success'))
        <div class="mb-4 max-w-3xl rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 max-w-3xl rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="space-y-4 max-w-3xl">
        @foreach($numerators as $n)
            <form method="POST" action="{{ route('admin.numbering.update', $n) }}"
                  x-data="{
                      mask: @js($n->mask), reset: @js($n->reset_period), padding: {{ $n->padding }},
                      preview() {
                          const now = new Date();
                          const seq = String({{ $n->current_value }} + 1).padStart(this.padding, '0');
                          return this.mask
                              .replaceAll('{N}', seq)
                              .replaceAll('{YYYY}', now.getFullYear())
                              .replaceAll('{YY}', String(now.getFullYear()).slice(-2))
                              .replaceAll('{MM}', String(now.getMonth()+1).padStart(2,'0'))
                              .replaceAll('{DD}', String(now.getDate()).padStart(2,'0'));
                      }
                  }"
                  class="bg-white border border-gray-200 rounded-xl p-5">
                @csrf
                @method('PUT')

                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5B4FE8] flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$n->key] ?? $icons['document'] }}"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-semibold text-gray-900">{{ \App\Models\Numerator::KEYS[$n->key] }}</h2>
                        <p class="text-xs text-gray-400">
                            Текущий счётчик периода: <span class="font-medium text-gray-600">{{ $n->current_value }}</span>
                            · следующий номер: <span class="font-mono font-medium text-[#5B4FE8]" x-text="preview()"></span>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="md:col-span-2">
                        <label class="text-xs text-gray-500 block mb-1">Маска номера</label>
                        <input type="text" name="mask" x-model="mask"
                               class="w-full font-mono text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <p class="text-xs text-gray-400 mt-1">
                            Токены: <code>{N}</code> — порядковый, <code>{YYYY}</code>/<code>{YY}</code> — год, <code>{MM}</code> — месяц, <code>{DD}</code> — день.
                        </p>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Сброс счётчика</label>
                        <select name="reset_period" x-model="reset" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                            @foreach($resetLabels as $v => $label)
                                <option value="{{ $v }}" @selected($n->reset_period === $v)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Заполнение нулями (разрядность)</label>
                        <input type="number" name="padding" x-model.number="padding" min="1" max="10"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Начальное значение</label>
                        <input type="number" name="start_value" value="{{ $n->start_value }}" min="0"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                        <p class="text-xs text-gray-400 mt-1">Последний использованный; первый номер = значение + 1.</p>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Момент присвоения</label>
                        @if($n->key === 'order')
                            <input type="hidden" name="assign_moment" value="{{ $n->assign_moment }}">
                            <input type="text" value="При публикации приказа" readonly
                                   class="w-full text-sm bg-gray-50 text-gray-500 border border-gray-200 rounded-lg px-3 py-2.5">
                        @else
                            <select name="assign_moment" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                                @foreach($momentLabels as $v => $label)
                                    <option value="{{ $v }}" @selected($n->assign_moment === $v)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end mt-4">
                    <button type="submit" class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Сохранить</button>
                </div>
            </form>
        @endforeach
    </div>
</x-app-layout>
