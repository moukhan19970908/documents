<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Постановщик задачи. Раньше задача дублировалась в Битрикс24, и постановщиком там
 * значился владелец вебхука; теперь вместо задачи уходит письмо, и в нём должен быть
 * виден настоящий постановщик — инициатор документа, приказа или заявки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('assignee_id')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
