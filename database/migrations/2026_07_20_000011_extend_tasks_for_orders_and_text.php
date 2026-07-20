<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');            // текст задачи (по фазе)
            $table->foreignId('order_id')->nullable()->after('document_id')     // задача по приказу
                ->constrained('orders')->cascadeOnDelete();
        });

        // Задача может относиться к приказу, а не к документу — снимаем NOT NULL.
        DB::statement('ALTER TABLE tasks MODIFY document_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('description');
        });

        DB::statement('DELETE FROM tasks WHERE document_id IS NULL');
        DB::statement('ALTER TABLE tasks MODIFY document_id BIGINT UNSIGNED NOT NULL');
    }
};
