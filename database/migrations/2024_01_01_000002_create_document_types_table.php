<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->unsignedBigInteger('default_workflow_id')->nullable();
            $table->timestamps();
        });

        Schema::create('document_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_type_id');
            $table->string('label');
            $table->string('field_key');
            $table->enum('field_type', ['text', 'date', 'number', 'select', 'user'])->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('document_type_id')->references('id')->on('document_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_fields');
        Schema::dropIfExists('document_types');
    }
};
