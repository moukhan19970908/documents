<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Правила поручений (ТЗ 17.1) — единственная строка настроек.
        Schema::create('assignment_settings', function (Blueprint $table) {
            $table->id();
            // Область постановки корневого поручения локальным руководителем.
            $table->enum('manager_scope', ['subordinates', 'direction', 'organization'])->default('subordinates');
            $table->boolean('allow_subassignments')->default(true);
            // Область подпоручений (обычно шире: кросс-отдел).
            $table->enum('sub_scope', ['subordinates', 'direction', 'organization'])->default('organization');
            $table->unsignedTinyInteger('max_depth')->default(5);
            $table->boolean('aggregate_up')->default(true);            // подтягивать файлы вверх при приёмке
            $table->boolean('coexecutors_enabled')->default(false);    // соисполнители
            $table->boolean('controller_enabled')->default(false);     // контролёр
            // Продление срока: запрещено / сразу / с одобрением постановщика.
            $table->enum('deadline_extension', ['disabled', 'free', 'approval'])->default('free');
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->foreignId('blank_template_id')->nullable()->constrained('blank_templates')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('assignment_settings')->insert([
            'manager_scope' => 'subordinates', 'allow_subassignments' => true,
            'sub_scope' => 'organization', 'max_depth' => 5, 'aggregate_up' => true,
            'coexecutors_enabled' => false, 'controller_enabled' => false,
            'deadline_extension' => 'free', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Заявка на перенос срока (режим «с одобрением»).
        Schema::table('assignments', function (Blueprint $table) {
            $table->date('pending_due_at')->nullable()->after('due_at');
            $table->text('pending_due_comment')->nullable()->after('pending_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['pending_due_at', 'pending_due_comment']);
        });
        Schema::dropIfExists('assignment_settings');
    }
};
