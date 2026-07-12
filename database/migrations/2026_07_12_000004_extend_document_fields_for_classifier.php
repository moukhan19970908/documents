<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `document_fields` MODIFY `field_type` ENUM('text','date','number','select','user','textarea','boolean','reference') NOT NULL DEFAULT 'text'");

        Schema::table('document_fields', function (Blueprint $table) {
            $table->foreignId('document_subtype_id')->nullable()->after('document_type_id')
                ->constrained('document_subtypes')->cascadeOnDelete();
            $table->string('reference_to')->nullable()->after('options');   // department | user
            $table->boolean('use_in_name')->default(false)->after('is_required');
            $table->boolean('use_in_filter')->default(false)->after('use_in_name');
            $table->boolean('use_in_archive')->default(false)->after('use_in_filter');
        });
    }

    public function down(): void
    {
        Schema::table('document_fields', function (Blueprint $table) {
            $table->dropForeign(['document_subtype_id']);
            $table->dropColumn([
                'document_subtype_id', 'reference_to',
                'use_in_name', 'use_in_filter', 'use_in_archive',
            ]);
        });

        DB::statement("ALTER TABLE `document_fields` MODIFY `field_type` ENUM('text','date','number','select','user','textarea') NOT NULL DEFAULT 'text'");
    }
};
