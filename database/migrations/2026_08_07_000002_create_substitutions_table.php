<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Замещение на период отсутствия (ТЗ «Раздел заявки»): инициатор при подаче отпуска/
 * командировки назначает замещающего. Пока инициатор отсутствует, его входящие
 * согласования и задания показываются замещающему.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('deputy_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->foreignId('trip_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vacation_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['deputy_user_id', 'date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitutions');
    }
};
