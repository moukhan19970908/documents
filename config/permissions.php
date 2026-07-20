<?php

/**
 * Каталог прав для матрицы (Роли и доступы → Матрица прав).
 *
 * Пока используется только для отрисовки матрицы: сохранение состояния
 * галочек ещё не реализовано, отметки строятся по ключу `except`.
 *
 * Поля пункта:
 *   key    — уникальный код права
 *   label  — название строки
 *   depth  — 1 для вложенных пунктов (визуальный отступ)
 *   narrow — узкое право: выделяется в матрице янтарным цветом
 *   except — коды ролей, у которых права по умолчанию нет (у остальных — есть)
 *   only   — белый список: право есть ТОЛЬКО у этих ролей. Имеет приоритет над except.
 *            Для узких прав используйте только его: иначе новая роль, созданная админом
 *            в интерфейсе, автоматически получит право, раз её нет в списке исключений.
 */
return [
    [
        'key'   => 'menu',
        'label' => 'Видимость меню',
        'items' => [
            ['key' => 'menu.dashboard', 'label' => 'Дашборд',   'except' => []],
            ['key' => 'menu.tasks',     'label' => 'Мои задачи', 'except' => []],
            ['key' => 'menu.chats',     'label' => 'Чаты',       'except' => ['external']],

            ['key' => 'menu.processes', 'label' => 'Процессы', 'except' => ['external', 'observer']],
            ['key' => 'menu.processes.documents',        'label' => 'Документооборот',   'depth' => 1, 'except' => ['external', 'observer']],
            ['key' => 'menu.processes.orders',           'label' => 'Приказы',           'depth' => 1, 'except' => ['external', 'observer', 'process_owner', 'registrar']],
            ['key' => 'menu.processes.assignments',      'label' => 'Поручения',         'depth' => 1, 'except' => ['external', 'observer', 'process_owner', 'registrar']],
            ['key' => 'menu.processes.procedures',       'label' => 'Процедуры',         'depth' => 1, 'except' => ['external', 'observer', 'process_owner', 'registrar']],
            ['key' => 'menu.processes.procedure_tasks',  'label' => 'Задачи процедур',   'depth' => 1, 'except' => ['external', 'observer', 'process_owner', 'registrar']],
            ['key' => 'menu.processes.requests',         'label' => 'Заявки',            'depth' => 1, 'except' => ['external', 'observer', 'process_owner']],
            ['key' => 'menu.processes.jobs',             'label' => 'Задания',           'depth' => 1, 'except' => ['external', 'observer', 'registrar']],
            ['key' => 'menu.processes.audits',           'label' => 'Проверки',          'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'registrar']],
            ['key' => 'menu.processes.credit_committee', 'label' => 'Кредитный комитет', 'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'head_unit', 'registrar', 'process_owner']],

            ['key' => 'menu.documents', 'label' => 'Документы', 'except' => ['external']],
            ['key' => 'menu.archive',   'label' => 'Архив',     'except' => ['external', 'process_owner']],
            ['key' => 'menu.employees', 'label' => 'Сотрудники', 'except' => ['external', 'observer', 'linear', 'process_owner', 'registrar']],
            ['key' => 'menu.analytics', 'label' => 'Аналитика', 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'registrar']],

            ['key' => 'menu.trips',            'label' => 'Командировки',    'except' => ['external', 'observer']],
            ['key' => 'menu.trips.my',         'label' => 'Мои заявки',      'depth' => 1, 'except' => ['external', 'observer']],
            ['key' => 'menu.trips.approvals',  'label' => 'На согласование', 'depth' => 1, 'except' => ['external', 'observer', 'linear', 'registrar']],
            ['key' => 'menu.trips.registries', 'label' => 'Реестры',         'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'process_owner']],

            ['key' => 'menu.vacations',            'label' => 'Отпуска',         'except' => ['external', 'observer']],
            ['key' => 'menu.vacations.my',         'label' => 'Мои заявки',      'depth' => 1, 'except' => ['external', 'observer']],
            ['key' => 'menu.vacations.approvals',  'label' => 'На согласование', 'depth' => 1, 'except' => ['external', 'observer', 'linear', 'registrar']],
            ['key' => 'menu.vacations.registries', 'label' => 'Реестры',         'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'process_owner']],

            ['key' => 'menu.admin',                  'label' => 'Администрирование',        'narrow' => true, 'except' => ['external', 'observer', 'linear', 'head_unit', 'head_department', 'director', 'ceo', 'chief_of_staff', 'process_owner', 'registrar', 'archiver']],
            ['key' => 'menu.admin.scenarios',        'label' => 'Конструктор процессов',    'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'head_unit', 'head_department', 'director', 'ceo', 'chief_of_staff', 'registrar', 'archiver']],
            ['key' => 'menu.admin.document_types',   'label' => 'Классификаторы и типы',    'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'head_unit', 'head_department', 'director', 'ceo', 'chief_of_staff', 'archiver']],
            ['key' => 'menu.admin.roles',            'label' => 'Роли и доступы',           'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'head_unit', 'head_department', 'director', 'ceo', 'chief_of_staff', 'process_owner', 'registrar', 'archiver']],
            ['key' => 'menu.admin.blank_templates',  'label' => 'Шаблоны бланков',          'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'head_unit', 'head_department', 'director', 'ceo', 'chief_of_staff', 'archiver']],
            ['key' => 'menu.admin.org_structure',    'label' => 'Оргструктура',             'depth' => 1, 'narrow' => true, 'except' => ['external', 'observer', 'linear', 'head_unit', 'process_owner', 'registrar', 'archiver']],
        ],
    ],

    [
        'key'   => 'orders',
        'label' => 'Приказы',
        'items' => [
            // Издание приказа — узкое право: только высшее руководство и администратор.
            ['key' => 'orders.issue',    'label' => 'Издать приказ',      'narrow' => true, 'only' => ['ceo', 'chief_of_staff', 'admin']],
            ['key' => 'orders.view_all', 'label' => 'Видеть все приказы', 'only' => ['head_department', 'director', 'ceo', 'chief_of_staff', 'admin']],
        ],
    ],

    [
        'key'   => 'assignments',
        'label' => 'Поручения',
        'items' => [
            // Ставить корневые поручения — руководители и выше.
            ['key' => 'assignments.issue',    'label' => 'Ставить поручения',   'narrow' => true, 'only' => ['head_unit', 'head_department', 'director', 'ceo', 'chief_of_staff', 'admin']],
            ['key' => 'assignments.view_all', 'label' => 'Видеть все поручения', 'only' => ['ceo', 'chief_of_staff', 'admin']],
        ],
    ],

    [
        'key'   => 'procedures',
        'label' => 'Процедуры',
        'items' => [
            // Запускать процедуры по шаблону — руководители и выше.
            ['key' => 'procedures.start',    'label' => 'Запускать процедуры',   'narrow' => true, 'only' => ['head_unit', 'head_department', 'director', 'ceo', 'chief_of_staff', 'admin']],
            ['key' => 'procedures.view_all', 'label' => 'Видеть все процедуры',    'only' => ['ceo', 'chief_of_staff', 'admin']],
            ['key' => 'procedures.manage',   'label' => 'Настраивать шаблоны процедур', 'narrow' => true, 'only' => ['admin']],
        ],
    ],

    [
        'key'   => 'inspections',
        'label' => 'Проверки',
        'items' => [
            // Инициировать проверки — узкий круг: аппарат управления, ГД, контрольные органы.
            ['key' => 'inspections.issue',    'label' => 'Инициировать проверки',  'narrow' => true, 'only' => ['chief_of_staff', 'ceo', 'admin']],
            ['key' => 'inspections.view_all', 'label' => 'Видеть все проверки',     'only' => ['ceo', 'chief_of_staff', 'admin']],
        ],
    ],
];
