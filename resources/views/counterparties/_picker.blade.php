{{--
    Поле «Контрагент» (ключ counterparty): поиск по справочнику по ИНН и названию,
    а если организации нет — добавление в справочник по ИНН из Checko.
    Обёртка задаёт x-data="counterpartyPicker(...)" и то, куда уходит значение (value).
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
                <span class="block text-xs text-gray-400 truncate" x-text="'ИНН ' + item.inn + (item.legal_address ? ' · ' + item.legal_address : '')"></span>
            </button>
        </template>

        <p x-show="loading" class="px-3 py-3 text-sm text-gray-400 text-center">Поиск…</p>

        <div x-show="!loading && results.length === 0" class="px-3 py-3 text-sm text-center">
            <p class="text-gray-400">В справочнике не найдено.</p>
            <button type="button" x-show="isInn()" @click="create()" :disabled="creating"
                    class="mt-2 text-sm font-medium text-[#5B4FE8] hover:underline disabled:opacity-40">
                <span x-text="creating ? 'Загружаем из Checko…' : 'Создать в справочнике по ИНН ' + query.trim()"></span>
            </button>
            <p x-show="!isInn()" class="text-xs text-gray-400 mt-1">Чтобы добавить организацию, введите её ИНН (10 цифр).</p>
        </div>

        <p x-show="error" class="px-3 pb-3 text-xs text-red-600 text-center" x-text="error"></p>
    </div>
</div>
