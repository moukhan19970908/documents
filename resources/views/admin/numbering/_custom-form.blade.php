@php
    /** @var \App\Models\Numerator|null $numerator */
    $n = $numerator ?? null;
    $selected = $n ? $n->bindings->map(fn ($b) => $b->classifier_type . ':' . $b->classifier_id)->all() : [];
    $selectedDepts = $n && $n->allowed_departments ? array_map('intval', $n->allowed_departments) : [];
    $curMask  = $n->mask ?? '{код_типа}-{YYYY}-{N}';
    $curReset = $n->reset_period ?? 'yearly';
    $curPad   = $n->padding ?? 4;
    $curStart = $n->start_value ?? 0;
@endphp

<form method="POST" action="{{ $action }}"
      x-data="{
          mask: @js($curMask), reset: @js($curReset), padding: {{ $curPad }},
          preview() {
              const now = new Date();
              const seq = String({{ $curStart }} + 1).padStart(this.padding, '0');
              return this.mask
                  .replaceAll('{N}', seq)
                  .replaceAll('{YYYY}', now.getFullYear())
                  .replaceAll('{YY}', String(now.getFullYear()).slice(-2))
                  .replaceAll('{MM}', String(now.getMonth()+1).padStart(2,'0'))
                  .replaceAll('{DD}', String(now.getDate()).padStart(2,'0'))
                  .replaceAll('{код_типа}', 'СЗ')
                  .replaceAll('{код_подтипа}', 'СЗ-1')
                  .replaceAll('{вид}', 'Кадровый')
                  .replaceAll('{отдел}', 'Бухгалтерия');
          }
      }">
    @csrf
    @if(($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">Название нумерации</label>
            <input type="text" name="name" value="{{ old('name', $n->name ?? '') }}" required
                   placeholder="Напр. «Служебные записки»"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>

        <div class="md:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">Маска номера</label>
            <input type="text" name="mask" x-model="mask" required
                   class="w-full font-mono text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <p class="text-xs text-gray-400 mt-1">
                Токены: <code>{N}</code> — порядковый, <code>{YYYY}</code>/<code>{YY}</code> — год, <code>{MM}</code> — месяц, <code>{DD}</code> — день,
                <code>{код_типа}</code>, <code>{код_подтипа}</code>, <code>{отдел}</code>, <code>{вид}</code> — вид приказа.
            </p>
            <p class="text-xs text-gray-400 mt-1">Пример: <span class="font-mono font-medium text-[#5B4FE8]" x-text="preview()"></span></p>
        </div>

        <div>
            <label class="text-xs text-gray-500 block mb-1">Сброс счётчика</label>
            <select name="reset_period" x-model="reset" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="none"    @selected($curReset === 'none')>Без сброса</option>
                <option value="monthly" @selected($curReset === 'monthly')>Ежемесячно</option>
                <option value="yearly"  @selected($curReset === 'yearly')>Ежегодно (с 1 января)</option>
            </select>
        </div>

        <div>
            <label class="text-xs text-gray-500 block mb-1">Заполнение нулями (разрядность)</label>
            <input type="number" name="padding" x-model.number="padding" min="1" max="10"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        </div>

        <div>
            <label class="text-xs text-gray-500 block mb-1">Начальное значение</label>
            <input type="number" name="start_value" value="{{ old('start_value', $curStart) }}" min="0"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <p class="text-xs text-gray-400 mt-1">Последний использованный; первый номер = значение + 1.</p>
        </div>

        <div>
            <label class="text-xs text-gray-500 block mb-1">Момент присвоения (для документов)</label>
            <select name="assign_moment" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
                <option value="on_launch"       @selected(($n->assign_moment ?? 'on_launch') === 'on_launch')>При запуске</option>
                <option value="on_registration" @selected(($n->assign_moment ?? '') === 'on_registration')>При регистрации</option>
                <option value="on_approval"     @selected(($n->assign_moment ?? '') === 'on_approval')>После согласования</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Приказы нумеруются при публикации — для них поле игнорируется.</p>
        </div>

        <div class="md:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">Счётчик</label>
            <div class="flex flex-col gap-1.5">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="shared_counter" value="0" @checked(! ($n->shared_counter ?? false)) class="text-[#5B4FE8] focus:ring-[#5B4FE8]">
                    Отдельная последовательность для каждого классификатора
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="shared_counter" value="1" @checked($n->shared_counter ?? false) class="text-[#5B4FE8] focus:ring-[#5B4FE8]">
                    Общий сквозной счётчик на все привязанные классификаторы
                </label>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <label class="text-xs text-gray-500 block mb-2">Привязать к классификаторам</label>

        <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-72 overflow-y-auto">
            @foreach($types as $type)
                <div class="px-3 py-2">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                        @php $tok = 'document_type:' . $type->id; @endphp
                        <input type="checkbox" name="classifiers[]" value="{{ $tok }}" @checked(in_array($tok, $selected)) class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                        {{ $type->name }}
                        @if(($boundTokens[$tok] ?? null) && ! in_array($tok, $selected))
                            <span class="text-xs text-amber-600 font-normal">— занят «{{ $boundTokens[$tok] }}»</span>
                        @endif
                    </label>
                    @if($type->subtypes->count())
                        <div class="mt-1.5 ml-6 flex flex-col gap-1">
                            @foreach($type->subtypes as $subtype)
                                @php $stok = 'document_subtype:' . $subtype->id; @endphp
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="classifiers[]" value="{{ $stok }}" @checked(in_array($stok, $selected)) class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    {{ $subtype->name }}
                                    @if(($boundTokens[$stok] ?? null) && ! in_array($stok, $selected))
                                        <span class="text-xs text-amber-600">— занят «{{ $boundTokens[$stok] }}»</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="px-3 py-2">
                <p class="text-sm font-medium text-gray-800 mb-1.5">Виды приказов</p>
                <div class="ml-0 flex flex-col gap-1">
                    @foreach($orderKinds as $code => $label)
                        @php $ktok = 'order_kind:' . $code; @endphp
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="classifiers[]" value="{{ $ktok }}" @checked(in_array($ktok, $selected)) class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                            {{ $label }}
                            @if(($boundTokens[$ktok] ?? null) && ! in_array($ktok, $selected))
                                <span class="text-xs text-amber-600">— занят «{{ $boundTokens[$ktok] }}»</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-1">Классификатор можно привязать только к одной нумерации — выбор перенесёт его сюда.</p>
    </div>

    @if(($deptGroups ?? collect())->isNotEmpty())
        <div class="mt-4">
            <label class="text-xs text-gray-500 block mb-2">Отделы (для фильтра по направлениям)</label>
            <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-72 overflow-y-auto">
                @foreach($deptGroups as $group)
                    <div class="px-3 py-2">
                        <p class="text-sm font-medium text-gray-800 mb-1.5">{{ $group['direction']->name }}</p>
                        <div class="ml-0 grid grid-cols-1 sm:grid-cols-2 gap-1">
                            @foreach($group['departments'] as $dept)
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="allowed_departments[]" value="{{ $dept->id }}"
                                           @checked(in_array($dept->id, $selectedDepts)) class="rounded text-[#5B4FE8] focus:ring-[#5B4FE8]">
                                    {{ $dept->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-1">Необязательно. Влияет только на фильтр «Направление → Отдел» на этой странице, не на присвоение номера.</p>
        </div>
    @endif

    <div class="flex justify-end mt-4">
        <button type="submit" class="px-5 py-2 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700">{{ $submitLabel ?? 'Сохранить' }}</button>
    </div>
</form>
