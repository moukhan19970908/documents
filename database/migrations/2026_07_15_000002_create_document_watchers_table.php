<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watcher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            // participant — везде, где цель участвует; initiator — только её документы; approver — где она согласующий
            $table->string('scope', 20)->default('participant');
            $table->timestamps();

            $table->unique(['watcher_id', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_watchers');
    }
};
