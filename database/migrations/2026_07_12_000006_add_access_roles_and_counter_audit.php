<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->json('allowed_roles')->nullable()->after('allowed_departments');
        });

        // Editing a live counter is sensitive — remember who moved it.
        Schema::table('document_counters', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('current_value')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_counters', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });

        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('allowed_roles');
        });
    }
};
