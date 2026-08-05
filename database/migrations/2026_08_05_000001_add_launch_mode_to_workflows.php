<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            // fixed — маршрут задан сценарием заранее; composed — инициатор собирает
            // маршрут из фаз при запуске (один универсальный процесс на отдел).
            $table->string('launch_mode')->default('fixed')->after('process_type');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('launch_mode');
        });
    }
};
