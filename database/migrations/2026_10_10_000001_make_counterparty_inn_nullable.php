<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Контрагента можно внести вручную — иностранную компанию Checko по ИНН не найдёт, а российского
     * ИНН у неё нет. Уникальность ИНН остаётся: в MySQL она не мешает нескольким NULL.
     */
    public function up(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->string('inn', 12)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Карточки без ИНН в прежнюю схему не помещаются — откат их удаляет.
        DB::table('counterparties')->whereNull('inn')->delete();

        Schema::table('counterparties', function (Blueprint $table) {
            $table->string('inn', 12)->nullable(false)->change();
        });
    }
};
