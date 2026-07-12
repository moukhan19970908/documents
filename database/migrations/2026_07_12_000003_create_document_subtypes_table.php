<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_subtypes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->string('code', 16)->nullable();
            $table->string('name');
            $table->string('name_template')->nullable();              // overrides the type mask
            $table->foreignId('numerator_id')->nullable()->constrained('numerators')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Scenarios serving the subtype. One → substituted silently, several → user picks.
        Schema::create('document_subtype_workflow', function (Blueprint $table) {
            $table->foreignId('document_subtype_id')->constrained('document_subtypes')->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();

            $table->primary(['document_subtype_id', 'workflow_id'], 'subtype_workflow_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_subtype_workflow');
        Schema::dropIfExists('document_subtypes');
    }
};
