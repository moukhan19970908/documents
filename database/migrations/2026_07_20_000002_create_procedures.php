<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Экземпляр процедуры (ТЗ 19). Заводится инициатором по шаблону, идёт по этапам движка.
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->index();          // [ПРЦ] регистрационный номер
            $table->unsignedInteger('seq')->nullable();

            $table->foreignId('procedure_template_id')->nullable()->constrained('procedure_templates')->nullOnDelete();
            $table->string('title');
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();

            // draft → in_progress → awaiting_tasks → completed (или stopped на негативной развилке).
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('current_position')->default(0); // указатель на текущий этап шаблона
            $table->json('data')->nullable();                        // поля формы (данные кандидата и т.п.)
            $table->text('stopped_reason')->nullable();              // причина остановки на развилке
            $table->timestamps();

            $table->index(['initiator_id', 'status']);
        });

        // Прохождение конкретного этапа экземпляром — таймлайн исполнения на карточке.
        Schema::create('procedure_stage_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained('procedures')->cascadeOnDelete();
            $table->foreignId('procedure_stage_id')->nullable()->constrained('procedure_stages')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 20);
            $table->string('title');
            $table->foreignId('executor_id')->nullable()->constrained('users')->nullOnDelete();
            // pending | active | done | rejected | returned | skipped
            $table->string('status', 20)->default('pending');
            $table->string('verdict', 20)->nullable();               // positive | negative (для развилки)
            $table->text('comment')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['procedure_id', 'position']);
        });

        // Вложения этапа (обязательные вложения ЭТАПА 1 и результаты этапов).
        Schema::create('procedure_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained('procedures')->cascadeOnDelete();
            $table->foreignId('procedure_stage_run_id')->nullable()->constrained('procedure_stage_runs')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
        });

        // Заполненный чек-лист экземпляра: source=preset (ЧАСТЬ 1) | custom (ЧАСТЬ 2, добавил инициатор).
        Schema::create('procedure_checklist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained('procedures')->cascadeOnDelete();
            $table->foreignId('procedure_checklist_item_id')->nullable()->constrained('procedure_checklist_items')->nullOnDelete();
            $table->string('source', 10)->default('preset');         // preset | custom
            $table->unsignedInteger('position')->default(0);
            $table->string('department')->nullable();
            $table->string('title');
            $table->string('field_type', 20)->default('boolean');
            $table->json('options')->nullable();
            $table->json('value')->nullable();                       // ответ: bool | текст | выбранный вариант
            $table->foreignId('executor_id')->nullable()->constrained('users')->nullOnDelete(); // кому уйдёт задача
            $table->boolean('spawns_task')->default(true);
            $table->timestamps();

            $table->index('procedure_id');
        });

        // Лента событий процедуры.
        Schema::create('procedure_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained('procedures')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_events');
        Schema::dropIfExists('procedure_checklist_entries');
        Schema::dropIfExists('procedure_files');
        Schema::dropIfExists('procedure_stage_runs');
        Schema::dropIfExists('procedures');
    }
};
