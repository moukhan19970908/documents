<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Годовой счётчик номеров приказов (сброс с 1 января).
        Schema::create('order_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_counters');
    }
};
