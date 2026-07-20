<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Задача, порождённая веером из чек-листа (ЭТАП 6). Процедура завершается, когда все задачи done.
        Schema::create('procedure_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained('procedures')->cascadeOnDelete();
            $table->foreignId('procedure_checklist_entry_id')->nullable()->constrained('procedure_checklist_entries')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->string('status', 12)->default('pending');        // pending | in_progress | done
            $table->text('result_comment')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['assignee_id', 'status']);
        });

        // Вложения к результату задачи.
        Schema::create('procedure_task_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_task_id')->constrained('procedure_tasks')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_task_files');
        Schema::dropIfExists('procedure_tasks');
    }
};
