// Нагрузочный тест СЭД (HTTP, без вебсокетов). Запуск и параметры — tests/load/README.md.
import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE     = (__ENV.BASE_URL || 'http://localhost:8000/documents/public').replace(/\/$/, '');
const USERS    = parseInt(__ENV.USERS || '200', 10);  // сколько учёток создал LoadTestUserSeeder
const PASSWORD = __ENV.PASSWORD || 'password';
const PROFILE  = __ENV.PROFILE || 'smoke';
const VUS      = parseInt(__ENV.VUS || '50', 10);     // целевое число одновременных пользователей
const THINK    = parseFloat(__ENV.THINK || '1');      // множитель пауз «пользователь читает страницу»
const WRITE    = __ENV.WRITE === '1';                 // создавать черновики документов (только тестовый стенд!)

const PROFILES = {
    smoke:  { executor: 'constant-vus', vus: 2, duration: '1m' },
    load:   { executor: 'ramping-vus', startVUs: 0, stages: [
        { duration: '5m', target: VUS },
        { duration: '30m', target: VUS },
        { duration: '5m', target: 0 },
    ] },
    stress: { executor: 'ramping-vus', startVUs: 0, stages: [
        { duration: '3m', target: VUS },
        { duration: '3m', target: VUS * 2 },
        { duration: '3m', target: VUS * 3 },
        { duration: '3m', target: VUS * 4 },
        { duration: '3m', target: 0 },
    ] },
    spike:  { executor: 'ramping-vus', startVUs: 0, stages: [
        { duration: '2m', target: Math.ceil(VUS / 5) },
        { duration: '30s', target: VUS * 3 },   // «все пришли в 9:00»
        { duration: '3m', target: VUS * 3 },
        { duration: '30s', target: Math.ceil(VUS / 5) },
        { duration: '3m', target: Math.ceil(VUS / 5) },
    ] },
    soak:   { executor: 'ramping-vus', startVUs: 0, stages: [
        { duration: '10m', target: VUS },
        { duration: __ENV.SOAK_DURATION || '4h', target: VUS },
        { duration: '5m', target: 0 },
    ] },
};

if (!PROFILES[PROFILE]) {
    throw new Error(`Неизвестный PROFILE=${PROFILE}. Есть: ${Object.keys(PROFILES).join(', ')}`);
}

export const options = {
    scenarios: { [PROFILE]: PROFILES[PROFILE] },
    thresholds: {
        http_req_failed:                     ['rate<0.01'],
        http_req_duration:                   ['p(95)<1000'],
        'http_req_duration{name:login}':     ['p(95)<1500'],
        'http_req_duration{name:analytics}': ['p(95)<3000'],
        'http_req_duration{name:doc_store}': ['p(95)<2000'],
        checks:                              ['rate>0.99'],
    },
    summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

// Раздел может быть закрыт ролью (аналитика, архив, заявки) — 403 здесь штатный ответ, не ошибка.
const ALLOW_403 = http.expectedStatuses({ min: 200, max: 399 }, 403);

// Состояние VU: cookie-jar k6 живёт весь прогон, поэтому логинимся один раз и держим сессию.
const session = { loggedIn: false, csrf: null };

function think(min, max) {
    sleep((min + Math.random() * (max - min)) * THINK);
}

function extract(body, re) {
    const m = re.exec(body || '');
    return m ? m[1] : null;
}

// Случайный id из ссылок вида /documents/123" на странице — как будто пользователь кликнул по строке списка.
function randomId(body, section) {
    const re = new RegExp(`/${section}/(\\d+)["']`, 'g');
    const ids = [];
    let m;
    while ((m = re.exec(body || '')) !== null) ids.push(m[1]);
    return ids.length ? ids[Math.floor(Math.random() * ids.length)] : null;
}

function login() {
    const email = `load${((__VU - 1) % USERS) + 1}@loadtest.local`;

    const form  = http.get(`${BASE}/login`, { tags: { name: 'login_page' } });
    const token = extract(form.body, /name="_token" value="([^"]+)"/);

    const res = http.post(`${BASE}/login`, { _token: token, login: email, password: PASSWORD }, { tags: { name: 'login' } });

    const ok = check(res, { 'login → dashboard': (r) => r.status === 200 && r.url.includes('/dashboard') });
    if (!ok) {
        console.error(`Логин не удался: ${email} → ${res.status} ${res.url}`);
        return false;
    }

    session.loggedIn = true;
    session.csrf = extract(res.body, /<meta name="csrf-token" content="([^"]+)"/);
    return true;
}

function page(path, name, { allow403 = false, params = {} } = {}) {
    // responseCallback задаём только при allow403: явный undefined/null в k6 отключает учёт http_req_failed.
    const res = http.get(`${BASE}${path}`, Object.assign(
        { tags: { name } },
        allow403 ? { responseCallback: ALLOW_403 } : {},
        params,
    ));

    // Сессия истекла (SESSION_LIFETIME) — перелогинимся на следующей итерации.
    if (/\/login(\?|$)/.test(res.url)) {
        session.loggedIn = false;
    }

    check(res, { [`${name} ok`]: (r) => r.status === 200 || (allow403 && r.status === 403) });

    const csrf = extract(res.body, /<meta name="csrf-token" content="([^"]+)"/);
    if (csrf) session.csrf = csrf;

    return res;
}

// ── Сценарии пользователей ──────────────────────────────────────────────────

function dashboardFlow() {
    group('Рабочий стол', () => {
        page('/dashboard', 'dashboard');
        think(2, 5);
        page('/notifications', 'notifications');
    });
}

const SEARCH_WORDS = ['договор', 'приказ', 'служебная', 'акт', 'заявка', 'счёт'];

function documentsFlow() {
    group('Документы', () => {
        let list = page('/documents', 'documents_index');
        think(2, 4);

        if (Math.random() < 0.4) {
            const q = SEARCH_WORDS[Math.floor(Math.random() * SEARCH_WORDS.length)];
            list = page(`/documents?search=${encodeURIComponent(q)}`, 'documents_search');
            think(1, 3);
        }

        const id = randomId(list.body, 'documents');
        if (!id) return;

        // 403 здесь штатный ответ, а не сбой: список не фильтруется уровнем доступа
        // (DocumentController::index не ограничивает выборку при resolveWorkflowAccess()==='full'),
        // а карточку закрывает DocumentPolicy::view. Поэтому строка в списке есть, а карточка
        // не открывается. Расхождение не устранено — помечено как ожидаемое, чтобы прогон
        // измерял производительность, а не эту особенность прав.
        page(`/documents/${id}`, 'document_show', { allow403: true });
        think(3, 8);

        if (Math.random() < 0.15) {
            // approvalSheet вызывает тот же authorize('view') — отказывает вместе с карточкой.
            page(`/documents/${id}/approval-sheet`, 'approval_sheet', { allow403: true });
        }
    });
}

function tasksFlow() {
    group('Задачи', () => {
        page('/tasks', 'tasks');
        think(2, 5);
        page('/requests/approvals', 'requests_approvals', { allow403: true });
    });
}

function archiveFlow() {
    group('Архив', () => {
        const list = page('/archive', 'archive_index', { allow403: true });
        if (list.status !== 200) return;
        think(2, 4);

        const id = randomId(list.body, 'archive');
        if (id) page(`/archive/${id}`, 'archive_show');
    });
}

function analyticsFlow() {
    group('Аналитика', () => {
        page('/analytics', 'analytics', { allow403: true });
    });
}

function chatsFlow() {
    group('Чаты', () => {
        const list = page('/chats', 'chats_index');
        const id = randomId(list.body, 'chats');
        if (!id) return;
        think(1, 3);

        page(`/chats/${id}`, 'chat_show');
        page(`/chats/${id}/messages`, 'chat_messages', { params: { headers: { Accept: 'application/json' } } });
    });
}

function miscFlow() {
    const pages = [
        ['/employees', 'employees'],
        ['/knowledge', 'knowledge'],
        ['/orders', 'orders'],
        ['/requests', 'requests_index'],
    ];
    const [path, name] = pages[Math.floor(Math.random() * pages.length)];
    group('Прочее', () => page(path, name, { allow403: true }));
}

// Пишущий сценарий: черновик документа без маршрута (+ иногда комментарий). Включается WRITE=1.
function createDocumentFlow() {
    group('Создание документа', () => {
        // Архивариусу создание документов не положено (DocumentPolicy::create) — 403, сценарий пропускаем.
        const form = page('/documents/create', 'document_create', { allow403: true });
        if (form.status !== 200) return;
        think(5, 15);  // пользователь заполняет форму

        const res = http.post(`${BASE}/documents`, {
            _token: session.csrf,
            title:  `Нагрузочный тест VU${__VU} #${__ITER}`,
            action: 'draft',
        }, { tags: { name: 'doc_store' } });

        const id = extract(res.url, /\/documents\/(\d+)$/);
        check(res, { 'doc_store → карточка': () => res.status === 200 && id !== null });
        if (!id || Math.random() > 0.3) return;

        think(2, 5);
        const note = http.post(`${BASE}/documents/${id}/notes`, {
            _token: session.csrf,
            body:   'Комментарий нагрузочного теста',
        }, { tags: { name: 'doc_note' } });
        check(note, { 'doc_note ok': (r) => r.status === 200 });
    });
}

// Веса = доля итераций. Подгоните под реальный трафик (логи веб-сервера / audit_logs).
const FLOWS = [
    [30, dashboardFlow],
    [30, documentsFlow],
    [15, tasksFlow],
    [8,  archiveFlow],
    [5,  analyticsFlow],
    [5,  chatsFlow],
    [7,  miscFlow],
].concat(WRITE ? [[5, createDocumentFlow]] : []);

const TOTAL_WEIGHT = FLOWS.reduce((sum, [w]) => sum + w, 0);

function pickFlow() {
    let r = Math.random() * TOTAL_WEIGHT;
    for (const [w, fn] of FLOWS) {
        if ((r -= w) < 0) return fn;
    }
    return FLOWS[0][1];
}

export default function () {
    if (!session.loggedIn && !login()) {
        sleep(10);  // не долбим /login, если учётки нет или стенд лежит
        return;
    }

    pickFlow()();
    think(3, 8);
}
