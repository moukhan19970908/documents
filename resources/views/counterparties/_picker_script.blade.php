{{-- Компонент поля «Контрагент» (см. counterparties/_picker). Подключается один раз, вне <template>. --}}
<script>
    // Значение поля — сокращённое название организации: его видят маска названия,
    // бланк и архив. Свободный ввод не принимается — только выбор из справочника.
    window.counterpartyPicker = function (initial = '') {
        return {
            value: initial ?? '',
            query: initial ?? '',
            inn: '',
            open: false,
            loading: false,
            creating: false,
            results: [],
            error: '',
            manual: false,
            manualForm: { short_name: '', inn: '', legal_address: '' },

            init() {
                this.$nextTick(() => { this.query = this.value ?? ''; });
                // Значение пришло снаружи (old() после ошибки валидации) — показываем его.
                this.$watch('value', v => { if (v && v !== this.query) this.query = v; });
            },

            isInn() {
                return /^(\d{10}|\d{12})$/.test(this.query.trim());
            },

            async search() {
                this.value = '';
                this.inn = '';
                this.error = '';
                this.manual = false;
                const q = this.query.trim();
                if (q.length < 2) {
                    this.results = [];
                    this.open = false;
                    return;
                }
                this.open = true;
                this.loading = true;
                try {
                    const response = await fetch(@js(route('counterparties.search')) + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json' },
                    });
                    // Пока шёл запрос, пользователь мог допечатать — старый ответ не нужен.
                    if (q !== this.query.trim()) return;
                    this.results = response.ok ? await response.json() : [];
                } finally {
                    this.loading = false;
                }
            },

            pick(item) {
                this.value = item.short_name || item.inn;
                this.query = this.value;
                this.inn = item.inn;
                this.open = false;
            },

            async create() {
                this.creating = true;
                this.error = '';
                try {
                    const response = await fetch(@js(route('counterparties.quick')), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                        },
                        body: JSON.stringify({ inn: this.query.trim() }),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        this.error = data.message || 'Не удалось добавить организацию.';
                        return;
                    }
                    this.pick(data);
                } finally {
                    this.creating = false;
                }
            },

            // Иностранную компанию Checko не знает — её вносят вручную; набранный текст — название или ИНН.
            startManual() {
                const q = this.query.trim();
                this.manualForm = { short_name: this.isInn() ? '' : q, inn: this.isInn() ? q : '', legal_address: '' };
                this.error = '';
                this.manual = true;
            },

            async createManual() {
                if (this.creating || !this.manualForm.short_name.trim()) return;
                this.creating = true;
                this.error = '';
                try {
                    const response = await fetch(@js(route('counterparties.manual')), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                        },
                        body: JSON.stringify(this.manualForm),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        this.error = Object.values(data.errors ?? {})[0]?.[0] || data.message || 'Не удалось добавить контрагента.';
                        return;
                    }
                    this.manual = false;
                    this.pick(data);
                } finally {
                    this.creating = false;
                }
            },
        };
    };
</script>
