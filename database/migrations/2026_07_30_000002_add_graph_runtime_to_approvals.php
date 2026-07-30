<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Исполнение маршрута-графа. Согласование помнит узел, на котором стоит, а каждое
 * материализованное звено — узел, который его породил: по нему движок понимает,
 * куда идти после решения (выход «Да» или «Нет»).
 *
 * Колонки добавочные и обнуляемые — маршруты старых версий продолжают идти как шли.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_approvals', function (Blueprint $table) {
            $table->foreignId('current_node_id')->nullable()->after('workflow_id')
                ->constrained('workflow_nodes')->nullOnDelete();
            // Данные запуска, нужные графу при материализации узлов:
            // участники, добавленные инициатором, выбор исполнителя для роли,
            // и сценарий-граф, по которому идёт этот документ.
            $table->json('runtime_data')->nullable()->after('parameter_values');
        });

        Schema::table('document_approval_stages', function (Blueprint $table) {
            $table->foreignId('workflow_node_id')->nullable()->after('workflow_stage_id')
                ->constrained('workflow_nodes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_approval_stages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_node_id');
        });

        Schema::table('document_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_node_id');
            $table->dropColumn('runtime_data');
        });
    }
};
