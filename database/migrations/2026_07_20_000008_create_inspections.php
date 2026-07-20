<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Проверка — узел свободного дерева (ТЗ 20). Механика как у поручений, но отдельный
        // раздел [ПРВ] с доменными полями (объект/период/вид) и структурированным актом.
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->index();          // [ПРВ] регистрационный номер
            $table->unsignedInteger('seq')->nullable();

            $table->foreignId('parent_id')->nullable()->constrained('inspections')->nullOnDelete();
            $table->foreignId('root_id')->nullable()->constrained('inspections')->nullOnDelete();
            $table->unsignedTinyInteger('depth')->default(0);

            $table->string('title');
            $table->longText('body_html')->nullable();

            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete(); // постановщик
            $table->foreignId('executor_id')->constrained('users')->cascadeOnDelete();  // проверяющий/исполнитель узла

            // Параметры корневой проверки (ТЗ 20.1).
            $table->string('object_type', 20)->nullable();          // department | direction | employee
            $table->unsignedBigInteger('object_id')->nullable();    // id отдела/направления/сотрудника
            $table->string('object_label')->nullable();             // денормализованное имя объекта для отображения
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('kind', 20)->nullable();                 // planned | unplanned

            $table->date('due_at')->nullable();
            // assigned → in_progress → submitted → done (+ returned).
            $table->string('status', 20)->default('assigned');
            $table->boolean('is_mandatory')->default(true);         // обязательная подпроверка для закрытия родителя

            $table->text('result_comment')->nullable();             // комментарий к акту
            $table->text('return_comment')->nullable();             // причина возврата на доработку

            // Структурированный итоговый акт (ТЗ 20.2).
            $table->string('act_verdict', 20)->nullable();          // found | not_found
            $table->text('act_violations')->nullable();             // перечень нарушений

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['executor_id', 'status']);
            $table->index(['initiator_id', 'status']);
        });

        // Файлы-результаты узла (служебные записки). source_inspection_id — подтянуто снизу при приёмке.
        Schema::create('inspection_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_inspection_id')->nullable()->constrained('inspections')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
        });

        // Лента событий узла для таймлайна.
        Schema::create('inspection_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_events');
        Schema::dropIfExists('inspection_files');
        Schema::dropIfExists('inspections');
    }
};
