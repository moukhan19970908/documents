<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Опциональная фаза согласования приказа (юрист / кадры / финансы).
        Schema::create('order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_label', 40);              // Юрист / Кадры / Финансы
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->string('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_approvals');
    }
};
