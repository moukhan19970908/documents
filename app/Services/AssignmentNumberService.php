<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\DocumentCounter;
use App\Models\Numerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Нумерация поручений по глобальному потоку «assignment» (ПОР-2026-0001).
 * Каждый узел дерева получает собственный регистрационный номер.
 */
class AssignmentNumberService
{
    public function assign(Assignment $assignment): string
    {
        $numerator = Numerator::where('key', 'assignment')->firstOrFail();
        $scopeKey  = $numerator->periodKey();

        $seq    = $this->nextValue($numerator, $scopeKey);
        $number = $numerator->format($seq);

        $assignment->forceFill(['seq' => $seq, 'number' => $number])->save();

        return $number;
    }

    /** Атомарный инкремент счётчика под блокировкой строки (без коллизий). */
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
