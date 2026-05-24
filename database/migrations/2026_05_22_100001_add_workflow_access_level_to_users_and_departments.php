<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('workflow_access_level', ['full', 'department', 'none'])
                  ->nullable()
                  ->after('permissions');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->enum('workflow_access_level', ['full', 'department', 'none'])
                  ->nullable()
                  ->after('head_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('workflow_access_level');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('workflow_access_level');
        });
    }
};
