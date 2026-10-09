# Нагрузочный тест (k6)

HTTP-нагрузка на основные разделы: рабочий стол, документы, задачи, архив, аналитика, чаты, прочее.
Вебсокеты (Reverb) не тестируются.

## Подготовка стенда

Только тестовый стенд, не прод. Перед прогоном:

```
# .env: APP_ENV=staging, APP_DEBUG=false, TELESCOPE_ENABLED=false, MAIL_MAILER=log
php artisan config:cache && php artisan route:cache && php artisan view:cache
LOAD_TEST_USERS=200 php artisan db:seed --class=LoadTestUserSeeder
```

`config:cache` обязателен, а не для скорости. Без него Laravel парсит `.env` на каждом запросе, и под конкуренцией чтение срывается: `APP_KEY` оказывается пустым → 500, cookie сессии не расшифровывается → 419 на логине. В логе это видно как `production.ERROR: No application encryption key` (канал `production`, а не `local` — признак, что `.env` не прочитан).

Сидер создаёт `load1..loadN@loadtest.local` с паролем `password` (или `LOAD_TEST_PASSWORD`), принятым соглашением
и смесью ролей linear / director / archiver. Чтобы списки и карточки были «тяжёлыми», в базе должно быть
реалистичное количество документов, архива и чатов.

## Запуск

Установка k6: `winget install k6`. Если winget падает (`0x8a15000f`), скачайте `k6-v*-windows-amd64.zip`
с https://github.com/grafana/k6/releases: там один `k6.exe`, установка не нужна.

```
k6 run -e BASE_URL=http://localhost:8000/documents/public -e PROFILE=smoke tests/load/k6/load-test.js
k6 run -e BASE_URL=... -e PROFILE=load   -e VUS=50  tests/load/k6/load-test.js
k6 run -e BASE_URL=... -e PROFILE=stress -e VUS=50  tests/load/k6/load-test.js
k6 run -e BASE_URL=... -e PROFILE=spike  -e VUS=50  tests/load/k6/load-test.js
k6 run -e BASE_URL=... -e PROFILE=soak   -e VUS=30 -e SOAK_DURATION=4h tests/load/k6/load-test.js
```

HTML-отчёт с графиками: `K6_WEB_DASHBOARD=true K6_WEB_DASHBOARD_EXPORT=report.html k6 run ...`
(в PowerShell: `$env:K6_WEB_DASHBOARD="true"; $env:K6_WEB_DASHBOARD_EXPORT="report.html"`).

| Параметр   | По умолчанию                            | Смысл |
|------------|-----------------------------------------|-------|
| `BASE_URL` | `http://localhost:8000/documents/public` | адрес приложения |
| `PROFILE`  | `smoke`                                  | smoke / load / stress / spike / soak |
| `VUS`      | `50`                                     | целевое число одновременных пользователей (stress идёт до ×4, spike до ×3) |
| `USERS`    | `200`                                    | сколько учёток создал сидер; VU берёт `load{(VU-1) % USERS + 1}` |
| `PASSWORD` | `password`                               | пароль тестовых учёток |
| `THINK`    | `1`                                      | множитель пауз между действиями (0 = без пауз, чистый RPS) |
| `WRITE`    | —                                        | `1` = создавать черновики документов и комментарии (пишет в БД) |

## Профили

- **smoke**: 2 VU, 1 мин. Проверить, что логин и сценарии работают, ошибок 0.
- **load**: разгон 5 мин → 30 мин на `VUS` → спад. Держит ли система целевую нагрузку.
- **stress**: ступени ×1..×4 по 5 мин. Где начинается деградация.
- **spike**: резкий скачок до ×3 («все пришли в 9:00») и обратно.
- **soak**: `VUS` на 4 ч. Утечки и рост таблиц `sessions`, `cache`, `jobs`, `audit_logs`.

Пороги (thresholds): ошибок < 1%, p95 < 1 с (логин < 1.5 с, аналитика < 3 с, создание документа < 2 с).
На stress и spike пороги должны сломаться: смотрите, на какой ступени это произошло.

## Что смотреть во время прогона

- `http_req_duration` в разрезе тега `name`: какая страница тормозит.
- MySQL: slow query log, `SHOW PROCESSLIST`, блокировки. Сессии, кэш и очередь лежат в той же БД.
- CPU и RAM веб-сервера и БД, число PHP-воркеров.
- Рост таблиц `sessions` и `audit_logs` (каждый POST пишет аудит).

## Уборка

Учётки: `DELETE FROM users WHERE email LIKE '%@loadtest.local'`. При `WRITE=1` остаются черновики
«Нагрузочный тест VU…». Проще всего пересоздать базу стенда из дампа.
