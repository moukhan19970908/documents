<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Поимённый снапшот адресатов: раскрывается в момент публикации.
        Schema::create('order_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_acknowledgments');
    }
};
