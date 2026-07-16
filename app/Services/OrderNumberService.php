<?php

namespace App\Services;

use App\Models\DocumentCounter;
use App\Models\Numerator;
use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Нумерация приказов по настраиваемому нумератору «order» (вкладка «Нумерация»).
 * Маска, сброс, паддинг и старт берутся из Numerator; счётчик — DocumentCounter
 * с атомарным инкрементом под блокировкой строки.
 */
class OrderNumberService
{
    public function assign(Order $order): string
    {
        $numerator = Numerator::where('key', 'order')->firstOrFail();
        $scopeKey  = $numerator->periodKey();

        $seq    = $this->nextValue($numerator, $scopeKey);
        $number = $numerator->format($seq);

        $order->forceFill(['seq' => $seq, 'number' => $number])->save();

        return $number;
    }

    /** Атомарный инкремент счётчика под блокировкой (без коллизий). */
    private function nextValue(Numerator $numerator, string $scopeKey): int
    {
        return DB::transaction(function () use ($numerator, $scopeKey) {
            $counter = $this->lock($numerator, $scopeKey);

            if (!$counter) {
                try {
                    DocumentCounter::create([
                        'numerator_id'  => $numerator->id,
                        'scope_key'     => $scopeKey,
                        'current_value' => $numerator->start_value,
                    ]);
                } catch (QueryException $e) {
                    // Параллельный запрос успел создать строку — просто заблокируем её.
                }
                $counter = $this->lock($numerator, $scopeKey);
            }

            $counter->current_value++;
            $counter->save();

            return (int) $counter->current_value;
        });
    }

    private function lock(Numerator $numerator, string $scopeKey): ?DocumentCounter
    {
        return DocumentCounter::where('numerator_id', $numerator->id)
            ->where('scope_key', $scopeKey)
            ->lockForUpdate()
            ->first();
    }
}
