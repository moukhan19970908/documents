<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numerators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mask');                                  // "ПРК-{YYYY}-{N}", "{N}/{YY}"
            $table->json('scope')->nullable();                       // ['type', 'subtype', 'department']
            $table->enum('reset_period', ['none', 'monthly', 'yearly'])->default('none');
            $table->unsignedTinyInteger('padding')->default(1);
            $table->unsignedBigInteger('start_value')->default(0);   // last used number; next = start_value + 1
            $table->enum('assign_moment', ['on_launch', 'on_registration', 'on_approval'])->default('on_launch');
            $table->boolean('allow_manual')->default(false);
            $table->json('manual_roles')->nullable();                // user roles allowed to type a number by hand
            $table->timestamps();
        });

        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('numerator_id')->constrained('numerators')->cascadeOnDelete();
            $table->string('scope_key');                             // "ПРКЗ|2026"
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['numerator_id', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
        Schema::dropIfExists('numerators');
    }
};
