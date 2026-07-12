<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->string('code', 16)->nullable()->unique()->after('name');   // "СЗ", "ДГ", "ПРКЗ"
            $table->text('description')->nullable()->after('icon');
            $table->boolean('is_active')->default(true)->after('description');
            $table->string('name_template')->nullable()->after('is_active');
            $table->foreignId('numerator_id')->nullable()->after('name_template')
                ->constrained('numerators')->nullOnDelete();
            $table->json('allowed_departments')->nullable()->after('numerator_id');
            $table->json('allowed_users')->nullable()->after('allowed_departments');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropForeign(['numerator_id']);
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'description', 'is_active', 'name_template',
                'numerator_id', 'allowed_departments', 'allowed_users',
            ]);
        });
    }
};
