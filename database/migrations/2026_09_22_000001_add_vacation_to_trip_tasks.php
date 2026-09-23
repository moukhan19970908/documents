<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Порождаемые задания теперь принадлежат не только командировке, но и отпуску
 * (узел «Задание» в граф-процессе есть у обоих видов заявок). Ровно одна из
 * двух ссылок заполнена — какая именно, решает TripTask::request().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_tasks', function (Blueprint $table) {
            $table->dropForeign(['trip_request_id']);
            $table->unsignedBigInteger('trip_request_id')->nullable()->change();
            $table->foreign('trip_request_id')->references('id')->on('trip_requests')->cascadeOnDelete();

            $table->foreignId('vacation_request_id')->nullable()->after('trip_request_id')
                  ->constrained('vacation_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vacation_request_id');

            $table->dropForeign(['trip_request_id']);
            $table->unsignedBigInteger('trip_request_id')->nullable(false)->change();
            $table->foreign('trip_request_id')->references('id')->on('trip_requests')->cascadeOnDelete();
        });
    }
};
