<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_presets', function (Blueprint $table) {
            $table->id();
            // Личная заготовка маршрута инициатора для composed-сценариев: без неё
            // типовой набор фаз пришлось бы собирать заново на каждый документ.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Упорядоченный список фаз с участниками: [{phase, participants:[id,...]}].
            $table->json('config');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_presets');
    }
};
