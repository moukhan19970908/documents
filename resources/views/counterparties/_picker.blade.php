{{--
    Поле «Контрагент» (ключ counterparty): поиск по справочнику по ИНН и названию,
    а если контрагента нет — добавление в справочник по ИНН из Checko или вручную
    (иностранная компания). Обёртка задаёт x-data="counterpartyPicker(...)" и то, куда уходит значение (value).
    У полей ручного добавления нет name — они не должны уйти вместе с формой документа.
--}}
<input type="text" x-model="query" @input.debounce.300ms="search()" @focus="if (!value && query.trim().length >= 2) search()"
       @keydown.escape="open = false" placeholder="ИНН или название организации" autocomplete="off"
       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">

<p x-show="query.trim() && !value && !open" class="text-xs text-amber-600 mt-1">Выберите организацию из справочника.</p>
<p x-show="value && inn" class="text-xs text-gray-400 mt-1" x-text="'ИНН ' + inn"></p>

<div x-show="open" style="display:none"
     class="absolute left-0 right-0 top-full mt-1 z-30 bg-white border border-gray-200 rounded-lg shadow-lg">
    <div class="max-h-56 overflow-y-auto py-1">
        <template x-for="item in results" :key="item.id">
            <button type="button" @click="pick(item)" class="w-full px-3 py-1.5 text-left hover:bg-gray-50">
                <span class="block text-sm text-gray-900 truncate" x-text="item.short_name || item.inn"></span>
                <span class="block text-xs text-gray-400 truncate" x-text="(item.inn ? 'ИНН ' + item.inn : 'без ИНН') + (item.legal_address ? ' · ' + item.legal_address : '')"></span>
            </button>
        </template>

        <p x-show="loading" class="px-3 py-3 text-sm text-gray-400 text-center">Поиск…</p>

        <div x-show="!loading && results.length === 0 && !manual" class="px-3 py-3 text-sm text-center">
            <p class="text-gray-400">В справочнике не найдено.</p>
            <button type="button" x-show="isInn()" @click="create()" :disabled="creating"
                    class="mt-2 text-sm font-medium text-[#5B4FE8] hover:underline disabled:opacity-40">
                <span x-text="creating ? 'Загружаем из Checko…' : 'Создать в справочнике по ИНН ' + query.trim()"></span>
            </button>
            <p x-show="!isInn()" class="text-xs text-gray-400 mt-1">Введите ИНН (10 цифр у организации, 12 у ИП) — или добавьте вручную.</p>
            <button type="button" @click="startManual()" class="mt-2 block mx-auto text-sm font-medium text-gray-600 hover:text-[#5B4FE8] hover:underline">
                Добавить вручную (иностранная компания)
            </button>
        </div>

        {{-- Ручное добавление --}}
        <div x-show="manual" class="px-3 py-3 space-y-2" @keydown.enter.prevent="createManual()">
            <p class="text-xs font-semibold text-gray-600">Новый контрагент вручную</p>
            <input type="text" x-model="manualForm.short_name" placeholder="Наименование *" maxlength="255" aria-label="Наименование контрагента"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <input type="text" x-model="manualForm.inn" placeholder="ИНН, если есть" inputmode="numeric" maxlength="12" aria-label="ИНН контрагента"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <input type="text" x-model="manualForm.legal_address" placeholder="Страна, город, адрес" aria-label="Адрес контрагента"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#5B4FE8]">
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" @click="manual = false; error = ''" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-800">Отмена</button>
                <button type="button" @click="createManual()" :disabled="creating || !manualForm.short_name.trim()"
                        class="px-3 py-1.5 bg-[#5B4FE8] text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-40">
                    <span x-text="creating ? 'Сохраняем…' : 'Добавить'"></span>
                </button>
            </div>
        </div>

        <p x-show="error" class="px-3 pb-3 text-xs text-red-600 text-center" x-text="error"></p>
    </div>
</div>
