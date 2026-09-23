<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Задания, порождаемые граф-процессом отпуска. Замораживаются при подаче (из плана
 * исполнителя) и порождаются при полном согласовании — как у командировок.
 * null — заявка шла не по графу, заданий у неё нет.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->json('flow_tasks')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->dropColumn('flow_tasks');
        });
    }
};
