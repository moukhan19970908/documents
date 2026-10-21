<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Кому попадают обращения тех поддержки: список обработчиков задаётся в админке. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_handlers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_handlers');
    }
};
