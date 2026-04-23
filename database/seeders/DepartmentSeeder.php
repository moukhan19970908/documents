<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $depts = [
            ['name' => 'Продажи'],
            ['name' => 'Юридический'],
            ['name' => 'Бухгалтерия'],
            ['name' => 'Служба безопасности'],
            ['name' => 'Руководство'],
        ];

        foreach ($depts as $d) {
            Department::firstOrCreate(['name' => $d['name']], $d);
        }
    }
}
