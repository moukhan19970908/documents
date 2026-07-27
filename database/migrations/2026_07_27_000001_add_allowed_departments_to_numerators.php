<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numerators', function (Blueprint $table) {
            // Отделы, к которым относится нумерация (для фильтра направление → отдел).
            $table->json('allowed_departments')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('numerators', function (Blueprint $table) {
            $table->dropColumn('allowed_departments');
        });
    }
};
