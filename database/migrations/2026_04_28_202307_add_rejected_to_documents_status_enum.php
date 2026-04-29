<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `documents` MODIFY `status` ENUM('draft','in_review','requires_changes','approved','rejected','signed','archived') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `documents` MODIFY `status` ENUM('draft','in_review','requires_changes','approved','signed','archived') NOT NULL DEFAULT 'draft'");
    }
};
