{{--
    Выбор замещающего согласующим: у линейного сотрудника его назначает первый согласующий, а не сам инициатор.
    Селект привязан к форме «Согласовать» атрибутом form, поэтому стоит где угодно в разметке.
    Требует $item (TripRequest|VacationRequest) и $form (id формы согласования); $class — отступы/раскладка.
--}}
@if($item->awaitsDeputyFromApprover())
    <div class="flex items-center gap-2 {{ $class ?? '' }}">
        <label for="{{ $form }}-deputy" class="text-xs text-gray-500 shrink-0">Замещающий на период отсутствия</label>
        <select id="{{ $form }}-deputy" name="deputy_id" form="{{ $form }}"
                class="min-w-0 max-w-xs text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <option value="">— Не назначать —</option>
            @foreach($item->user->deputyCandidates() as $d)
                <option value="{{ $d->id }}">{{ $d->name }}@if($d->position) — {{ $d->position }}@endif</option>
            @endforeach
        </select>
    </div>
@endif
