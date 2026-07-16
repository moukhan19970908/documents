<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable();          // ПРК-2026-0198, присваивается при публикации
            $table->unsignedInteger('seq')->nullable();     // годовой порядковый (198)
            $table->string('kind', 20)->default('operational'); // вид приказа (Order::KINDS)
            $table->string('title');                        // краткое название
            $table->longText('body_html')->nullable();      // тело из бланка
            $table->foreignId('blank_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path')->nullable();        // загруженный готовый файл
            $table->string('file_name')->nullable();
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->date('effective_at')->nullable();       // дата вступления в силу
            $table->date('ack_deadline')->nullable();       // срок ознакомления
            $table->json('audience')->nullable();           // снапшот выбора для показа (отделы/сотрудники)
            $table->boolean('requires_approval')->default(false);
            $table->string('status', 20)->default('draft'); // draft | on_approval | published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
