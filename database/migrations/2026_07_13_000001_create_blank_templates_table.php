<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blank_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();

            // Бланк принадлежит типу; подтип сужает его до конкретной разновидности.
            // Без подтипа бланк предлагается на любом документе своего типа.
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->foreignId('document_subtype_id')->nullable()->constrained('document_subtypes')->nullOnDelete();

            // Тело бланка — HTML из редактора, с токенами {номер}, {дата} и полями типа.
            $table->longText('content')->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blank_templates');
    }
};
