<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Глобальный поток нумерации поручений: ПОР-2026-0001.
        DB::table('numerators')->updateOrInsert(['key' => 'assignment'], [
            'name'          => 'Поручения',
            'mask'          => 'ПОР-{YYYY}-{N}',
            'scope'         => json_encode([]),
            'reset_period'  => 'yearly',
            'padding'       => 4,
            'start_value'   => 0,
            'assign_moment' => 'on_launch',
            'allow_manual'  => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('numerators')->where('key', 'assignment')->delete();
    }
};
