<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Граф-процесс заявки (ТЗ «Конструктор заявок»): визуальный процесс для отпуска/командировки
 * на бесшовном стеке. Узлы хранятся деревом; у ветвящегося узла — N именованных веток
 * (branch), а определения веток лежат в config родителя. Отдельно от документного движка.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_flows', function (Blueprint $table) {
            $table->id();
            $table->string('request_type');            // trip | vacation
            $table->string('name');
            $table->string('status')->default('draft'); // draft | published
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index('request_type');
        });

        Schema::create('request_flow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('request_flows')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();  // узел-родитель (ветвление)
            $table->string('branch')->default('main');            // main | ключ ветки родителя
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type');
            $table->string('name');
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['flow_id', 'parent_id', 'branch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_flow_nodes');
        Schema::dropIfExists('request_flows');
    }
};
