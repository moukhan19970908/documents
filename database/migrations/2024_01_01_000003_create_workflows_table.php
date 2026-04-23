<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('document_type_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('document_type_id')->references('id')->on('document_types')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->string('name');
            $table->enum('stage_type', ['sequential', 'parallel', 'condition'])->default('sequential');
            $table->integer('sort_order')->default(0);
            $table->integer('deadline_hours')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
        });

        Schema::create('workflow_stage_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_stage_id');
            $table->enum('approver_type', ['user', 'department', 'role'])->default('user');
            $table->unsignedBigInteger('approver_id');
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->foreign('workflow_stage_id')->references('id')->on('workflow_stages')->cascadeOnDelete();
        });

        // Now add the foreign key to document_types for default_workflow_id
        Schema::table('document_types', function (Blueprint $table) {
            $table->foreign('default_workflow_id')->references('id')->on('workflows')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropForeign(['default_workflow_id']);
        });
        Schema::dropIfExists('workflow_stage_approvers');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflows');
    }
};
