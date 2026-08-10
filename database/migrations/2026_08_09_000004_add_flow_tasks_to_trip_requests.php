<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Задания, порождаемые граф-процессом командировки (узлы «Задание»/«Параллель»).
 * Замораживаются при подаче (из плана исполнителя) и порождаются при полном согласовании.
 * null — заявка шла не по графу (задания порождает прежний TripTaskService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->json('flow_tasks')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropColumn('flow_tasks');
        });
    }
};
