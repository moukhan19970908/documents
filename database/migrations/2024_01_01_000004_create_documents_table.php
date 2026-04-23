<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('document_type_id');
            $table->unsignedBigInteger('initiator_id');
            $table->unsignedBigInteger('current_stage_id')->nullable();
            $table->enum('status', ['draft', 'in_review', 'requires_changes', 'approved', 'signed', 'archived'])
                  ->default('draft');
            $table->json('data')->nullable();
            $table->string('bitrix24_task_id')->nullable();
            $table->timestamps();

            $table->foreign('document_type_id')->references('id')->on('document_types');
            $table->foreign('initiator_id')->references('id')->on('users');
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->integer('version')->default(1);
            $table->unsignedBigInteger('uploaded_by');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users');
        });

        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('workflow_id');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['in_progress', 'approved', 'rejected', 'cancelled'])->default('in_progress');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('workflow_id')->references('id')->on('workflows');
        });

        Schema::create('document_approval_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_approval_id');
            $table->unsignedBigInteger('workflow_stage_id');
            $table->enum('status', ['pending', 'in_progress', 'approved', 'rejected', 'delegated'])
                  ->default('pending');
            $table->boolean('is_overdue')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();

            $table->foreign('document_approval_id')->references('id')->on('document_approvals')->cascadeOnDelete();
            $table->foreign('workflow_stage_id')->references('id')->on('workflow_stages');
        });

        Schema::create('document_approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_approval_stage_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('action', ['approve', 'reject', 'delegate', 'request_changes']);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('delegated_to')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->foreign('document_approval_stage_id')->references('id')->on('document_approval_stages')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('delegated_to')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('document_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_approval_stage_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('document_approval_stage_id')->references('id')->on('document_approval_stages')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_notes');
        Schema::dropIfExists('document_approval_decisions');
        Schema::dropIfExists('document_approval_stages');
        Schema::dropIfExists('document_approvals');
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('documents');
    }
};
