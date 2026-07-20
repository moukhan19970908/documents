<?php

namespace App\Services;

use App\Models\DocumentCounter;
use App\Models\Inspection;
use App\Models\Numerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Нумерация проверок по глобальному потоку «inspection» (ПРВ-2026-0001).
 * Каждый узел дерева получает собственный регистрационный номер.
 */
class InspectionNumberService
{
    public function assign(Inspection $inspection): string
    {
        $numerator = Numerator::where('key', 'inspection')->firstOrFail();
        $scopeKey  = $numerator->periodKey();

        $seq    = $this->nextValue($numerator, $scopeKey);
        $number = $numerator->format($seq);

        $inspection->forceFill(['seq' => $seq, 'number' => $number])->save();

        return $number;
    }

    private function nextValue(Numerator $numerator, string $scopeKey): int
    {
        return DB::transaction(function () use ($numerator, $scopeKey) {
            $counter = $this->lock($numerator, $scopeKey);

            if (! $counter) {
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
