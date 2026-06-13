<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `document_approval_decisions` MODIFY `action` ENUM('approve','reject','delegate','request_changes','process_approve','process_reject') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `document_approval_decisions` MODIFY `action` ENUM('approve','reject','delegate','request_changes') NOT NULL");
    }
};
