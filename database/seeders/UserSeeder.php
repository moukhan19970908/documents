<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Department;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $management = Department::where('name', 'Руководство')->first();
        $sales      = Department::where('name', 'Продажи')->first();
        $legal      = Department::where('name', 'Юридический')->first();

        $users = [
            [
                'name'      => 'Мингазов Азат',
                'email'     => 'admin@archmanuscript.local',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'department_id' => $management?->id,
                'is_active' => true,
            ],
            [
                'name'      => 'Иванова Елена',
                'email'     => 'director1@archmanuscript.local',
                'password'  => Hash::make('password'),
                'role'      => 'director',
                'department_id' => $management?->id,
                'is_active' => true,
            ],
            [
                'name'      => 'Петров Сергей',
                'email'     => 'director2@archmanuscript.local',
                'password'  => Hash::make('password'),
                'role'      => 'director',
                'department_id' => $sales?->id,
                'is_active' => true,
            ],
            [
                'name'      => 'Сидоров Дмитрий',
                'email'     => 'linear1@archmanuscript.local',
                'password'  => Hash::make('password'),
                'role'      => 'linear',
                'department_id' => $sales?->id,
                'is_active' => true,
            ],
            [
                'name'      => 'Козлова Анна',
                'email'     => 'linear2@archmanuscript.local',
                'password'  => Hash::make('password'),
                'role'      => 'linear',
                'department_id' => $legal?->id,
                'is_active' => true,
            ],
            [
                'name'      => 'Архивариус Олег',
                'email'     => 'archiver@archmanuscript.local',
                'password'  => Hash::make('password'),
                'role'      => 'archiver',
                'department_id' => $management?->id,
                'is_active' => true,
            ],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(['email' => $u['email']], $u);
        }
    }
}
