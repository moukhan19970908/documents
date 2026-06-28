<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE approval_routes MODIFY COLUMN request_type ENUM('trip', 'vacation', 'vacation_registry') NOT NULL");

        Schema::table('approval_routes', function (Blueprint $table) {
            $table->unsignedTinyInteger('applies_to_role_level')->nullable()->after('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('approval_routes', function (Blueprint $table) {
            $table->dropColumn('applies_to_role_level');
        });

        DB::statement("ALTER TABLE approval_routes MODIFY COLUMN request_type ENUM('trip', 'vacation') NOT NULL");
    }
};
