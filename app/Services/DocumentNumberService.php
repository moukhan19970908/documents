<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentCounter;
use App\Models\DocumentRegistration;
use App\Models\Numerator;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Assigns registration numbers.
 *
 * Rules that must not be broken:
 *   1. The counter is incremented under a row lock — two simultaneous launches never collide.
 *   2. Drafts get no number (assignment happens at launch/registration/approval, per numerator).
 *   3. Cancelling a document does not free its number — counters only ever move forward.
 */
class DocumentNumberService
{
    public function __construct(private AuditService $auditService) {}

    /** Number masks understand a different token set than name masks. */
    private const NUMBER_TOKENS = ['{N}', '{YYYY}', '{YY}', '{MM}', '{DD}', '{код_типа}', '{код_подтипа}', '{отдел}'];

    public function numeratorFor(Document $document): ?Numerator
    {
        $document->loadMissing(['type.numerator', 'subtype.numerator', 'subtype.type.numerator']);

        return $document->subtype?->effectiveNumerator() ?? $document->type?->numerator;
    }

    /**
     * Registers the document if its numerator fires at $moment. Idempotent: an already
     * numbered document keeps its number.
     */
    public function registerIfDue(Document $document, string $moment, ?User $user = null): ?string
    {
        if ($document->isRegistered()) {
            return $document->number;
        }

        $numerator = $this->numeratorFor($document);

        if (!$numerator) {
            return null;
        }

        // on_launch and on_registration are the same event here: pressing «Запустить».
        $fires = $numerator->assign_moment === $moment
            || ($moment === 'on_launch' && $numerator->assign_moment === 'on_registration');

        if (!$fires) {
            return null;
        }

        return $this->register($document, $numerator, $user);
    }

    public function register(Document $document, Numerator $numerator, ?User $user = null, ?string $manualNumber = null): string
    {
        $scopeKey = $this->scopeKey($numerator, $document);

        // A manual number is taken as given (back-dated paper registration) and does not
        // move the counter, so the automatic sequence stays intact.
        $number = $manualNumber
            ?: $this->renderMask($numerator, $document, $this->nextValue($numerator, $scopeKey));

        $registeredAt = now();

        $document->update([
            'number'        => $number,
            'registered_at' => $registeredAt,
        ]);

        DocumentRegistration::create([
            'document_id'   => $document->id,
            'numerator_id'  => $numerator->id,
            'scope_key'     => $scopeKey,
            'number'        => $number,
            'registered_at' => $registeredAt,
            'registered_by' => $user?->id,
            'is_manual'     => (bool) $manualNumber,
        ]);

        $this->auditService->log('document_registered', $document, null, [
            'number'    => $number,
            'scope_key' => $scopeKey,
            'manual'    => (bool) $manualNumber,
        ]);

        return $number;
    }

    /** Atomic increment: the counter row is locked for the duration of the transaction. */
    private function nextValue(Numerator $numerator, string $scopeKey): int
    {
        return DB::transaction(function () use ($numerator, $scopeKey) {
            $counter = $this->lockCounter($numerator, $scopeKey);

            if (!$counter) {
                try {
                    DocumentCounter::create([
                        'numerator_id'  => $numerator->id,
                        'scope_key'     => $scopeKey,
                        'current_value' => $numerator->start_value,
                    ]);
                } catch (QueryException $e) {
                    // Another request created the row first — fall through and lock it.
                }

                $counter = $this->lockCounter($numerator, $scopeKey);
            }

            $counter->current_value++;
            $counter->save();

            return (int) $counter->current_value;
        });
    }

    private function lockCounter(Numerator $numerator, string $scopeKey): ?DocumentCounter
    {
        return DocumentCounter::where('numerator_id', $numerator->id)
            ->where('scope_key', $scopeKey)
            ->lockForUpdate()
            ->first();
    }

    /**
     * One counter per scope combination. `reset_period` adds the period to the key, which is
     * what makes the sequence restart on 1 January.
     */
    public function scopeKey(Numerator $numerator, Document $document): string
    {
        $parts = [];

        foreach ($numerator->scope ?? [] as $dimension) {
            $parts[] = match ($dimension) {
                'type'       => $document->type?->code ?? ('type:' . $document->document_type_id),
                'subtype'    => $document->subtype?->code ?? ('subtype:' . $document->document_subtype_id),
                'department' => (string) $document->initiator?->department_id,
                default      => '',
            };
        }

        $parts[] = match ($numerator->reset_period) {
            'yearly'  => now()->format('Y'),
            'monthly' => now()->format('Y-m'),
            default   => 'all',
        };

        return implode('|', $parts);
    }

    private function renderMask(Numerator $numerator, Document $document, int $value): string
    {
        $document->loadMissing(['type', 'subtype', 'initiator.department']);

        return str_replace(self::NUMBER_TOKENS, [
            str_pad((string) $value, $numerator->padding, '0', STR_PAD_LEFT),
            now()->format('Y'),
            now()->format('y'),
            now()->format('m'),
            now()->format('d'),
            $document->type?->code ?? '',
            $document->subtype?->code ?? '',
            $document->initiator?->department?->name ?? '',
        ], $numerator->mask);
    }
}
