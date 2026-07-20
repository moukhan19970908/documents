<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Шаблон процедуры (ТЗ 19) — сценарное дерево этапов, настраивается админом.
        Schema::create('procedure_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Этап сценария. Типы: form | approval | branch | return_to_initiator | checklist | fanout | completion.
        Schema::create('procedure_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_template_id')->constrained('procedure_templates')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 20);                              // тип этапа (см. Procedure engine)
            $table->string('title');
            $table->string('executor_mode', 20)->default('initiator'); // initiator | user | role
            $table->string('executor_role')->nullable();               // код роли, если executor_mode=role
            $table->foreignId('executor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('require_attachments')->default(false);  // обязательные вложения перед отправкой этапа
            $table->json('config')->nullable();                      // доп. настройки (метки вердикта развилки и т.п.)
            $table->timestamps();

            $table->index(['procedure_template_id', 'position']);
        });

        // ЧАСТЬ 1 гибридного чек-листа — пресетные пункты с типизированными полями и привязанным исполнителем.
        Schema::create('procedure_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_template_id')->constrained('procedure_templates')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('department')->nullable();                // отдел-владелец пункта (метка)
            $table->string('title');
            $table->string('field_type', 20)->default('boolean');    // checkbox | boolean | boolean_text | select | text
            $table->json('options')->nullable();                     // варианты для field_type=select
            $table->string('executor_mode', 20)->default('user');    // user | department_head | initiator
            $table->foreignId('executor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('spawns_task')->default(true);           // порождает задачу при активном ответе
            $table->timestamps();

            $table->index(['procedure_template_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_checklist_items');
        Schema::dropIfExists('procedure_stages');
        Schema::dropIfExists('procedure_templates');
    }
};
