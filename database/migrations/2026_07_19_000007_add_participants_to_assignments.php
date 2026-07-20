<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Контролёр узла (надзор) — опционально, если включено настройками.
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('controller_id')->nullable()->after('executor_id')->constrained('users')->nullOnDelete();
        });

        // Соисполнители — участники исполнения помимо основного исполнителя.
        Schema::create('assignment_co_executors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['assignment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_co_executors');
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('controller_id');
        });
    }
};
