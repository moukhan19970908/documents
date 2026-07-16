<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numerators', function (Blueprint $table) {
            // Ключ глобального потока нумерации: document / order / credit_committee.
            $table->string('key', 40)->nullable()->unique()->after('name');
        });

        $defaults = [
            'document'         => ['name' => 'Документы',        'mask' => 'ДОК-{YYYY}-{N}'],
            'order'            => ['name' => 'Приказы',          'mask' => 'ПРК-{YYYY}-{N}'],
            'credit_committee' => ['name' => 'Кредитный комитет', 'mask' => 'КК-{YYYY}-{N}'],
        ];

        foreach ($defaults as $key => $d) {
            DB::table('numerators')->updateOrInsert(['key' => $key], [
                'name'          => $d['name'],
                'mask'          => $d['mask'],
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
    }

    public function down(): void
    {
        DB::table('numerators')->whereIn('key', ['document', 'order', 'credit_committee'])->delete();

        Schema::table('numerators', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }
};
