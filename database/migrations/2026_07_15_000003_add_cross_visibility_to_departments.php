<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // На корневом департаменте (направлении): сотрудники всех его отделов
            // видят документы друг друга.
            $table->boolean('cross_visibility')->default(false)->after('archive_access_level');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('cross_visibility');
        });
    }
};
