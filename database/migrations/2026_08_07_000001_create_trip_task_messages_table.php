<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тред «вопрос инициатору» на задании командировки (ТЗ «Раздел заявки»): лёгкая
 * переписка исполнителя и инициатора по конкретному заданию. Отдельно от глобального
 * модуля «Чаты», который завязан на документы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_task_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_task_messages');
    }
};
