<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['article', 'video', 'instruction', 'regulation'])->default('article');
            $table->unsignedSmallInteger('study_minutes')->nullable();      // «Время на изучение», мин
            $table->longText('body')->nullable();                           // HTML тела материала

            // Размещение в дереве (редактор): Направление → Отдел → Уровень.
            $table->foreignId('direction_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('level', ['employees', 'managers', 'directors'])->nullable();

            // Доступ (страница «Управление доступом»).
            $table->boolean('is_general')->default(false);                  // «Общее для всех»
            $table->enum('access_level', ['employees', 'managers', 'directors'])->default('employees');

            $table->boolean('is_published')->default(true);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Доступ по структуре: отделы, которым виден материал.
        Schema::create('material_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->unique(['material_id', 'department_id']);
        });

        // Точечный доступ: конкретные сотрудники сверх правила по структуре.
        Schema::create('material_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['material_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_user');
        Schema::dropIfExists('material_department');
        Schema::dropIfExists('materials');
    }
};
