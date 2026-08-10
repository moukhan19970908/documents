<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Признак «одноразового» маршрута: маршрут, материализованный из граф-процесса заявки под
 * конкретную заявку. Такие маршруты не выбираются автоподбором (findRoute) и не показываются
 * в админ-списках — они принадлежат своей заявке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_routes', function (Blueprint $table) {
            $table->boolean('is_ephemeral')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('approval_routes', function (Blueprint $table) {
            $table->dropColumn('is_ephemeral');
        });
    }
};
