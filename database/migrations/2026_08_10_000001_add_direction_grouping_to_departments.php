<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Направление как отдельный слой поверх оргструктуры.
 *
 * Раньше добавление отдела в направление переписывало его parent_id — отдел
 * физически вырывался из дерева Битрикса и структура «Сотрудники → Схема»
 * ломалась. Теперь членство в направлении хранится в departments.direction_id
 * и parent_id (дерево Б24) не трогается.
 *
 * Миграция также ПЕРЕНОСИТ уже существующее (сломанное) членство: отделы,
 * чей parent сейчас указывает на рукотворное направление (контейнер без
 * bitrix24_department_id), получают direction_id, а контейнеры помечаются
 * is_direction. Сам parent_id восстанавливается повторным запуском
 * `php artisan bitrix24:sync --departments-only` (он пересобирает parent_id
 * из поля PARENT Битрикса). Поэтому мигрируем ДО ресинка — пока «сломанные»
 * parent_id ещё хранят информацию о том, кто в каком направлении был.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('direction_id')->nullable()->after('parent_id')
                ->constrained('departments')->nullOnDelete();
            $table->boolean('is_direction')->default(false)->after('direction_id');
        });

        // Контейнеры направлений: рукотворные отделы верхнего уровня без Б24-id
        // (реальные корни Битрикса имеют bitrix24_department_id и сюда не попадают).
        $containerIds = DB::table('departments')
            ->whereNull('parent_id')
            ->whereNull('bitrix24_department_id')
            ->pluck('id')
            ->all();

        if (! empty($containerIds)) {
            DB::table('departments')->whereIn('id', $containerIds)
                ->update(['is_direction' => true]);

            // Снимаем членство из сломанных parent_id: кто лежал под контейнером —
            // тот член направления (direction_id). parent_id обнуляем, чтобы отделы
            // не оказались сиротами под скрытым контейнером до ресинка Битрикса,
            // который вернёт им настоящего родителя (по полю PARENT из Б24).
            DB::table('departments')
                ->whereIn('parent_id', $containerIds)
                ->update(['direction_id' => DB::raw('parent_id'), 'parent_id' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['direction_id']);
            $table->dropColumn(['direction_id', 'is_direction']);
        });
    }
};
