<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Транспорт командировки (ТЗ 18.2): свой / организации — определяет порождаемое задание.
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->enum('transport_type', ['own', 'company'])->nullable()->after('transport_total');
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropColumn('transport_type');
        });
    }
};
