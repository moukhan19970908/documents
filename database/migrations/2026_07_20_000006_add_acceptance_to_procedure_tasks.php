<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Приёмка задач инициатором (ТЗ 19.1, ЭТАП 7): исполнитель сдаёт → инициатор принимает/возвращает.
        Schema::table('procedure_tasks', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('due_at'); // момент сдачи на приёмку
            $table->text('return_comment')->nullable()->after('result_comment'); // причина возврата на доработку
        });
    }

    public function down(): void
    {
        Schema::table('procedure_tasks', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'return_comment']);
        });
    }
};
