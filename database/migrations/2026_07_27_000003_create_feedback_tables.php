<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Обратная связь (ТЗ 29): обращения пользователей + тред ответов. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category');   // bug | wish | question
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('new'); // new | in_progress | answered | closed
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('category');
        });

        Schema::create('feedback_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_messages');
        Schema::dropIfExists('feedback');
    }
};
