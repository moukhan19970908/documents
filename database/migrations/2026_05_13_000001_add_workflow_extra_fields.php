<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('approval_type')->default('sequential')->after('description');
            $table->json('allowed_departments')->nullable()->after('approval_type');
            $table->json('process_fields')->nullable()->after('allowed_departments');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['approval_type', 'allowed_departments', 'process_fields']);
        });
    }
};
