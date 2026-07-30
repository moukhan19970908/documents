<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Возврат на доработку — самостоятельный исход круга согласования, а не отклонение.
 * Движок писал этот статус и раньше, но в enum его не было: на строгом MySQL
 * решение «Отправить на доработку» падало и откатывалось целиком.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE document_approvals MODIFY COLUMN status ENUM('in_progress', 'approved', 'rejected', 'requires_changes', 'cancelled') NOT NULL DEFAULT 'in_progress'");
    }

    public function down(): void
    {
        // Значение исчезает из enum — круги, закрытые доработкой, становятся отклонёнными.
        DB::table('document_approvals')->where('status', 'requires_changes')->update(['status' => 'rejected']);

        DB::statement("ALTER TABLE document_approvals MODIFY COLUMN status ENUM('in_progress', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'in_progress'");
    }
};
