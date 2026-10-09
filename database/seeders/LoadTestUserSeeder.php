<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Учётки для нагрузочного теста k6 (tests/load): load1..loadN@loadtest.local.
 * Соглашение сразу принято — иначе middleware agreement уводит каждый запрос на /agreement.
 *
 *   LOAD_TEST_USERS=200 php artisan db:seed --class=LoadTestUserSeeder
 */
class LoadTestUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Не запускать на проде: сидер создаёт тестовые учётки.');
            return;
        }

        $count         = (int) env('LOAD_TEST_USERS', 200);
        $password      = Hash::make(env('LOAD_TEST_PASSWORD', 'password'));
        $departmentIds = Department::pluck('id')->all();

        for ($i = 1; $i <= $count; $i++) {
            // Смесь ролей как в жизни: в основном исполнители, часть руководителей и архивариусов.
            $role = match (true) {
                $i % 20 === 0 => 'archiver',
                $i % 6 === 0  => 'director',
                default       => 'linear',
            };

            User::updateOrCreate(['email' => "load{$i}@loadtest.local"], [
                'name'                  => "Нагрузка {$i}",
                'password'              => $password,
                'role'                  => $role,
                'department_id'         => $departmentIds ? $departmentIds[$i % count($departmentIds)] : null,
                'is_active'             => true,
                'notification_email'    => false,
                'agreement_accepted_at' => now(),
            ]);
        }

        $this->command->info("Готово: {$count} учёток load*@loadtest.local");
    }
}
