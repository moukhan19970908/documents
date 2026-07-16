<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentCounter;
use App\Models\Numerator;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NumberingController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index()
    {
        $numerators = Numerator::whereIn('key', array_keys(Numerator::KEYS))
            ->orderByRaw("FIELD(`key`, 'document', 'order', 'credit_committee')")
            ->get()
            ->map(function (Numerator $n) {
                $current = DocumentCounter::where('numerator_id', $n->id)
                    ->where('scope_key', $n->periodKey())
                    ->value('current_value') ?? $n->start_value;

                $n->current_value = (int) $current;
                $n->preview = $n->format((int) $current + 1);

                return $n;
            });

        return view('admin.numbering.index', compact('numerators'));
    }

    public function update(Request $request, Numerator $numerator)
    {
        abort_unless(in_array($numerator->key, array_keys(Numerator::KEYS), true), 404);

        $data = $request->validate([
            'mask'          => ['required', 'string', 'max:100'],
            'reset_period'  => ['required', Rule::in(['none', 'monthly', 'yearly'])],
            'padding'       => ['required', 'integer', 'min:1', 'max:10'],
            'start_value'   => ['required', 'integer', 'min:0'],
            'assign_moment' => ['required', Rule::in(['on_launch', 'on_registration', 'on_approval'])],
        ]);

        $numerator->update($data);
        $this->auditService->log('numerator_updated', $numerator);

        return redirect()->route('admin.numbering.index')
            ->with('success', 'Настройки нумерации «' . Numerator::KEYS[$numerator->key] . '» сохранены.');
    }
}
