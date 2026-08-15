<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Неизменяемые копии листа ознакомления и листа приёма — рядом с листом согласования.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->string('acknowledgment_sheet_path')->nullable()->after('approval_sheet_path');
            $table->string('acceptance_sheet_path')->nullable()->after('acknowledgment_sheet_path');
        });
    }

    public function down(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->dropColumn(['acknowledgment_sheet_path', 'acceptance_sheet_path']);
        });
    }
};
