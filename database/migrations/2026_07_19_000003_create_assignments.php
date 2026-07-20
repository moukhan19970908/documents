<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Поручение — узел свободного дерева (ТЗ 17). Шаблона маршрута нет:
        // постановщик выбирает исполнителя в моменте, исполнитель может породить подпоручения.
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->index();          // [ПОР] регистрационный номер
            $table->unsignedInteger('seq')->nullable();

            $table->foreignId('parent_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->foreignId('root_id')->nullable()->constrained('assignments')->nullOnDelete(); // корень дерева
            $table->unsignedTinyInteger('depth')->default(0);        // уровень вложенности (защита глубины)

            $table->string('title');
            $table->longText('body_html')->nullable();

            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete(); // постановщик узла
            $table->foreignId('executor_id')->constrained('users')->cascadeOnDelete();  // исполнитель узла

            $table->date('due_at')->nullable();
            // Назначено → В работе → На приёмке → Выполнено (+ Возвращено на доработку). Просрочка — производная.
            $table->string('status', 20)->default('assigned');
            $table->boolean('is_mandatory')->default(true);         // обязательный дочерний узел для закрытия родителя

            $table->text('result_comment')->nullable();             // отчёт исполнителя
            $table->text('return_comment')->nullable();             // причина возврата на доработку

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['executor_id', 'status']);
            $table->index(['initiator_id', 'status']);
        });

        // Файлы-результаты узла. source_assignment_id — если файл подтянут снизу при приёмке (агрегация вверх).
        Schema::create('assignment_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
        });

        // Лента событий узла для таймлайна на карточке поручения.
        Schema::create('assignment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);   // created/started/submitted/accepted/returned/deadline_changed/subassignment_created
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_events');
        Schema::dropIfExists('assignment_files');
        Schema::dropIfExists('assignments');
    }
};
