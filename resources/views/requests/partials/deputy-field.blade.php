{{-- Поле «Замещение на период отсутствия» для форм отпуска/командировки. Требует $deputies. --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <label class="text-xs font-semibold text-gray-600 uppercase tracking-widest block mb-1">Замещение на период отсутствия</label>
    <p class="text-xs text-gray-400 mb-3">Кто выполняет ваши задачи и согласования, пока вас нет. На период заявки его входящие получаете не вы, а замещающий.</p>
    <select name="deputy_id"
            class="w-full text-sm border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
        <option value="">— Не назначать —</option>
        @foreach($deputies as $d)
            <option value="{{ $d->id }}" @selected(old('deputy_id') == $d->id)>
                {{ $d->name }}@if($d->position) — {{ $d->position }}@endif
            </option>
        @endforeach
    </select>
</div>
