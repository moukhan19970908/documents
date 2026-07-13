<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Roles whose code is referenced from application code (policies, middleware,
     * numerators) are marked is_system and cannot be renamed by code or deleted.
     */
    public function run(): void
    {
        $roles = [
            ['code' => 'linear',          'name' => 'Линейный сотрудник',              'level' => 1, 'icon' => 'user',      'color' => 'indigo',  'is_system' => true,  'description' => 'Создаёт документы и участвует в согласовании.'],
            ['code' => 'head_unit',       'name' => 'Руководитель отдела',             'level' => 2, 'icon' => 'users',     'color' => 'blue',    'is_system' => false, 'description' => 'Согласует документы своего отдела.'],
            ['code' => 'head_department', 'name' => 'Руководитель департамента',       'level' => 3, 'icon' => 'building',  'color' => 'emerald', 'is_system' => false, 'description' => 'Согласует документы департамента.'],
            ['code' => 'director',        'name' => 'Директор направления',            'level' => 4, 'icon' => 'flag',      'color' => 'indigo',  'is_system' => true,  'description' => 'Согласует документы направления, ведёт реестры.'],
            ['code' => 'ceo',             'name' => 'Генеральный директор',            'level' => 5, 'icon' => 'crown',     'color' => 'amber',   'is_system' => false, 'description' => 'Финальное утверждение документов.'],
            ['code' => 'chief_of_staff',  'name' => 'Руководитель аппарата управления','level' => 4, 'icon' => 'briefcase', 'color' => 'slate',   'is_system' => false, 'description' => 'Контролирует прохождение документов.'],
            ['code' => 'admin',           'name' => 'Администратор',                   'level' => 5, 'icon' => 'gear',      'color' => 'rose',    'is_system' => true,  'description' => 'Полный доступ ко всем разделам и настройкам.'],
            ['code' => 'process_owner',   'name' => 'Владелец процесса',               'level' => 3, 'icon' => 'process',   'color' => 'amber',   'is_system' => false, 'description' => 'Отвечает за сценарий и маршрут процесса.'],
            ['code' => 'registrar',       'name' => 'Регистратор',                     'level' => 2, 'icon' => 'clipboard', 'color' => 'blue',    'is_system' => false, 'description' => 'Присваивает номера и регистрирует документы.'],
            ['code' => 'observer',        'name' => 'Наблюдатель',                     'level' => 1, 'icon' => 'eye',       'color' => 'slate',   'is_system' => false, 'description' => 'Только просмотр, без права действий.'],
            ['code' => 'external',        'name' => 'Внешний участник',                'level' => 1, 'icon' => 'external',  'color' => 'slate',   'is_system' => true,  'description' => 'Контрагент: видит только свои документы.'],
            ['code' => 'archiver',        'name' => 'Архивариус',                      'level' => 2, 'icon' => 'clipboard', 'color' => 'emerald', 'is_system' => true,  'description' => 'Ведёт архив и бухгалтерские реестры.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['code' => $role['code']], $role);
        }
    }
}
