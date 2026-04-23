<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `document_fields` MODIFY `field_type` ENUM('text','date','number','select','user','textarea') NOT NULL DEFAULT 'text'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `document_fields` MODIFY `field_type` ENUM('text','date','number','select','user') NOT NULL DEFAULT 'text'");
    }
};
