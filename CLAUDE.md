# Documents — СЭД

Электронный документооборот: согласование по маршрутам, приказы, поручения, проверки, заявки, ECM-архив. Сотрудники синхронизируются из Bitrix24.

## Commands

```bash
composer dev                    # serve + queue:listen + pail + vite
composer test                   # config:clear + artisan test
php artisan test --filter=Имя   # один тест
npm run build
vendor/bin/pint                 # стиль PHP; целиком ещё не прогонялся
```

## Architecture

- Контроллер → сервис. Вся логика согласования в `ApprovalEngineService`, где сосуществуют два движка: линейный (`activateStage`/`moveToNextStage`) и графовый (`enterNode`/`advanceGraph`).
- Единого слоя авторизации нет: 5 политик, `abort_unless($user->hasMatrixPermission(...))` в теле экшенов, middleware `role` и `ExternalRestriction`.
- Номера выдают `*NumberService` через `lockForUpdate` в транзакции — не обходить.

## Key Decisions

- Пользовательский HTML санируется `Purifier::clean(..., 'blank')` **на записи**, поэтому `{!! !!}` в blade намеренно.
- Роли лежат в двух местах: legacy-колонка `users.role` и pivot `roles`; `User::roleCodes()` их сливает. Проверять через `hasRole`/`hasAnyRole`, никогда через `$user->role`.
- `ExternalRestriction` — deny-by-default allowlist по **именам** маршрутов: новый маршрут для внешнего участника надо явно добавить в `ALLOWED_PREFIXES`.

## Domain Knowledge

Звено — шаг маршрута согласования. Направление (`direction_id`) — слой над деревом отделов из Bitrix24. Composed-сценарий (`launch_mode=composed`) — инициатор собирает маршрут сам при запуске. Матрица прав — `role_permissions`, отдельно от ролей. Бланк — HTML-шаблон документа с токенами.

## Don'ts

- Аналитика использует MySQL-специфичный `DATE_FORMAT`, а тесты идут на sqlite `:memory:` — такие места проверять на MySQL.
- Загрузки расползлись по дискам: `chats`/`related`/`avatars` → s3, `assignments`/`inspections`/`orders` → локальный. Известный долг, не править попутно.
