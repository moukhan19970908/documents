<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Виды приказа переезжают из константы Order::KINDS в классификатор: это подтипы типа
 * «Приказ» (slug order), их ведёт администратор в «Классификаторах и типах».
 * Привязки нумерации и счётчики видов переводятся на подтипы, чтобы номера продолжились.
 * На чистой установке конвертировать нечего — виды по умолчанию создаёт OrderSeeder.
 */
return new class extends Migration
{
    /** Бывшие Order::KINDS. */
    private const LEGACY_KINDS = [
        'operational' => 'По основной деятельности',
        'personnel'   => 'Кадровый',
        'approval'    => 'Об утверждении',
    ];

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('document_subtype_id')->nullable()->after('seq')
                ->constrained('document_subtypes')->nullOnDelete();
        });

        $typeId = DB::table('document_types')->where('slug', 'order')->value('id');

        // Приказы есть, а типа нет (сидер не запускался) — без типа их виды некуда перенести.
        if ($typeId === null && DB::table('orders')->exists()) {
            $typeId = DB::table('document_types')->insertGetId([
                'name' => 'Приказ', 'code' => 'ПРК', 'slug' => 'order', 'icon' => 'order', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if ($typeId !== null) {
            foreach (array_keys(self::LEGACY_KINDS) as $i => $code) {
                $subtypeId = $this->legacySubtypeId($typeId, $code)
                    ?? DB::table('document_subtypes')->insertGetId([
                        'document_type_id' => $typeId, 'name' => self::LEGACY_KINDS[$code],
                        'is_active' => true, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now(),
                    ]);

                DB::table('orders')->where('kind', $code)->update(['document_subtype_id' => $subtypeId]);

                DB::table('numerator_bindings')
                    ->where('classifier_type', 'order_kind')
                    ->where('classifier_id', $code)
                    ->update(['classifier_type' => 'document_subtype', 'classifier_id' => (string) $subtypeId]);

                $this->renameCounterScopes("order_kind:{$code}|", "document_subtype:{$subtypeId}|");
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('kind', 20)->default('operational')->after('seq');
        });

        $typeId = DB::table('document_types')->where('slug', 'order')->value('id');

        foreach (array_keys(self::LEGACY_KINDS) as $code) {
            $subtypeId = $typeId === null ? null : $this->legacySubtypeId($typeId, $code);
            if ($subtypeId === null) {
                continue;
            }

            DB::table('orders')->where('document_subtype_id', $subtypeId)->update(['kind' => $code]);

            DB::table('numerator_bindings')
                ->where('classifier_type', 'document_subtype')
                ->where('classifier_id', (string) $subtypeId)
                ->update(['classifier_type' => 'order_kind', 'classifier_id' => $code]);

            $this->renameCounterScopes("document_subtype:{$subtypeId}|", "order_kind:{$code}|");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_subtype_id');
        });
    }

    private function legacySubtypeId(int $typeId, string $code): ?int
    {
        return DB::table('document_subtypes')
            ->where('document_type_id', $typeId)
            ->where('name', self::LEGACY_KINDS[$code])
            ->value('id');
    }

    private function renameCounterScopes(string $fromPrefix, string $toPrefix): void
    {
        // «_» в префиксе — подстановочный знак LIKE, поэтому точное совпадение проверяем ещё раз.
        DB::table('document_counters')->where('scope_key', 'like', $fromPrefix . '%')->get(['id', 'scope_key'])
            ->filter(fn ($counter) => str_starts_with($counter->scope_key, $fromPrefix))
            ->each(fn ($counter) => DB::table('document_counters')->where('id', $counter->id)->update([
                'scope_key' => $toPrefix . substr($counter->scope_key, strlen($fromPrefix)),
            ]));
    }
};
