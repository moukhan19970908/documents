<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Флаг «бухгалтерия» на отделе (ТЗ «Раздел заявки»): реестры, переданные в бухгалтерию,
 * видят и принимают сотрудники отделов с этим флагом (и их поддерева). Общий пул —
 * без разделения по холдингу.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('is_accounting')->default(false)->after('cross_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('is_accounting');
        });
    }
};
