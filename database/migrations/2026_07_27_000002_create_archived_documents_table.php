<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Архив (ECM). Единое неизменяемое хранилище: один физический документ хранится
 * ровно один раз со снимком метаданных; «папки» и «дела» — срезы по этим полям.
 * Полиморфная связь source_* позволит позже класть сюда приказы и поручения.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_documents', function (Blueprint $table) {
            $table->id();

            // Источник (Document сейчас; Order/Assignment — следующими инкрементами).
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->unique(['source_type', 'source_id']);

            // Снимок метаданных (денормализованы — переживают удаление источника).
            $table->string('title');
            $table->string('number')->nullable();
            $table->unsignedBigInteger('document_type_id')->nullable()->index();
            $table->unsignedBigInteger('document_subtype_id')->nullable();
            $table->unsignedBigInteger('direction_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('initiator_id')->nullable()->index();
            $table->string('counterparty')->nullable()->index();
            $table->json('metadata')->nullable();

            // Неизменяемые копии.
            $table->longText('body_html')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('approval_sheet_path')->nullable();
            $table->string('content_hash', 64)->nullable();

            $table->timestamp('archived_at');
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_documents');
    }
};
