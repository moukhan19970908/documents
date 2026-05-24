<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tasks_access_level')->nullable()->after('workflow_access_level');
            $table->string('archive_access_level')->nullable()->after('tasks_access_level');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('tasks_access_level')->nullable()->after('workflow_access_level');
            $table->string('archive_access_level')->nullable()->after('tasks_access_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tasks_access_level', 'archive_access_level']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['tasks_access_level', 'archive_access_level']);
        });
    }
};
