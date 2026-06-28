<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vacation_requests MODIFY COLUMN status ENUM('draft', 'pending', 'approved', 'rejected', 'revision', 'in_registry') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vacation_requests MODIFY COLUMN status ENUM('draft', 'pending', 'approved', 'rejected', 'revision') NOT NULL DEFAULT 'draft'");
    }
};
