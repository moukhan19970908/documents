<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DocumentCounter;
use App\Models\DocumentType;
use App\Models\Numerator;
use App\Models\NumeratorBinding;
use App\Models\Order;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NumberingController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index(Request $request)
    {
        $numerators = Numerator::whereIn('key', array_keys(Numerator::KEYS))
            ->orderByRaw("FIELD(`key`, 'document', 'order', 'assignment', 'credit_committee')")
            ->get()
            ->map(function (Numerator $n) {
                $current = DocumentCounter::where('numerator_id', $n->id)
                    ->where('scope_key', $n->periodKey())
                    ->value('current_value') ?? $n->start_value;

                $n->current_value = (int) $current;
                $n->preview = $n->format((int) $current + 1);

                return $n;
            });

        // Пользовательские нумераторы, привязанные к классификаторам.
        $custom = Numerator::whereNull('key')
            ->with('bindings')
            ->orderBy('name')
            ->get()
            ->map(function (Numerator $n) {
                $n->preview = $n->format((int) $n->start_value + 1);
                return $n;
            });

        // Фильтр направление → отдел (применяется к пользовательским нумераторам).
        $directions   = Department::whereNull('parent_id')->orderBy('name')->get();
        $directionId  = $request->integer('direction') ?: null;
        $departmentId = $request->integer('department') ?: null;

        $departments = collect();
        if ($directionId) {
            $childIds = array_values(array_diff(Department::getDescendantIds($directionId), [$directionId]));
            $departments = Department::whereIn('id', $childIds)->orderBy('name')->get();

            if ($departmentId && ! in_array($departmentId, $childIds, true)) {
                $departmentId = null;
            }

            $scopeIds = array_map('intval', $departmentId
                ? Department::getDescendantIds($departmentId)
                : Department::getDescendantIds($directionId));

            $custom = $custom->filter(fn (Numerator $n) => ! empty($n->allowed_departments)
                && array_intersect($scopeIds, array_map('intval', (array) $n->allowed_departments)) !== [])
                ->values();
        } else {
            $departmentId = null;
        }

        // Отделы, сгруппированные по направлению — для чекбоксов в форме нумерации.
        $allDepts = Department::orderBy('name')->get();
        $deptGroups = $directions->map(fn ($dir) => [
            'direction'   => $dir,
            'departments' => $allDepts->whereIn(
                'id',
                array_diff(Department::getDescendantIds($dir->id), [$dir->id])
            )->values(),
        ])->filter(fn ($g) => $g['departments']->isNotEmpty())->values();

        $types      = DocumentType::with('subtypes')->orderBy('name')->get();
        $orderKinds = Order::KINDS;

        // Человекочитаемые подписи привязок для чипов.
        $classifierLabels = [];
        foreach ($types as $type) {
            $classifierLabels['document_type:' . $type->id] = $type->name;
            foreach ($type->subtypes as $subtype) {
                $classifierLabels['document_subtype:' . $subtype->id] = $type->name . ' → ' . $subtype->name;
            }
        }
        foreach ($orderKinds as $code => $label) {
            $classifierLabels['order_kind:' . $code] = 'Приказ: ' . $label;
        }

        // Уже занятые классификаторы: «тип:id» => имя нумератора (подсказка в форме).
        $boundTokens = [];
        foreach ($custom as $n) {
            foreach ($n->bindings as $b) {
                $boundTokens[$b->classifier_type . ':' . $b->classifier_id] = $n->name;
            }
        }

        return view('admin.numbering.index', compact(
            'numerators', 'custom', 'types', 'orderKinds', 'classifierLabels', 'boundTokens',
            'directions', 'directionId', 'departments', 'departmentId', 'deptGroups'
        ));
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

    /** Создать пользовательский нумератор и привязать к выбранным классификаторам. */
    public function storeCustom(Request $request)
    {
        $data = $this->validateCustom($request);

        $numerator = Numerator::create([
            'key'                 => null,
            'name'                => $data['name'],
            'mask'                => $data['mask'],
            'scope'               => [],
            'allowed_departments' => $data['allowed_departments'] ?? [],
            'shared_counter' => $data['shared_counter'] ?? false,
            'reset_period'   => $data['reset_period'],
            'padding'        => $data['padding'],
            'start_value'    => $data['start_value'],
            'assign_moment'  => $data['assign_moment'],
        ]);

        $this->syncBindings($numerator, $data['classifiers']);
        $this->auditService->log('numerator_created', $numerator);

        return redirect()->route('admin.numbering.index')
            ->with('success', 'Нумерация «' . $numerator->name . '» создана.');
    }

    /** Изменить пользовательский нумератор и его привязки. */
    public function updateCustom(Request $request, Numerator $numerator)
    {
        abort_unless($numerator->isCustom(), 404);

        $data = $this->validateCustom($request);

        $numerator->update([
            'name'                => $data['name'],
            'mask'                => $data['mask'],
            'allowed_departments' => $data['allowed_departments'] ?? [],
            'shared_counter' => $data['shared_counter'] ?? false,
            'reset_period'   => $data['reset_period'],
            'padding'        => $data['padding'],
            'start_value'    => $data['start_value'],
            'assign_moment'  => $data['assign_moment'],
        ]);

        $this->syncBindings($numerator, $data['classifiers']);
        $this->auditService->log('numerator_updated', $numerator);

        return redirect()->route('admin.numbering.index')
            ->with('success', 'Нумерация «' . $numerator->name . '» сохранена.');
    }

    /** Удалить пользовательский нумератор (привязки и счётчики удаляются каскадом). */
    public function destroyCustom(Numerator $numerator)
    {
        abort_unless($numerator->isCustom(), 404);

        $name = $numerator->name;
        $this->auditService->log('numerator_deleted', $numerator);
        $numerator->delete();

        return redirect()->route('admin.numbering.index')
            ->with('success', 'Нумерация «' . $name . '» удалена.');
    }

    private function validateCustom(Request $request): array
    {
        return $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'mask'            => ['required', 'string', 'max:100'],
            'reset_period'    => ['required', Rule::in(['none', 'monthly', 'yearly'])],
            'padding'         => ['required', 'integer', 'min:1', 'max:10'],
            'start_value'     => ['required', 'integer', 'min:0'],
            'assign_moment'   => ['required', Rule::in(['on_launch', 'on_registration', 'on_approval'])],
            'shared_counter'        => ['nullable', 'boolean'],
            'classifiers'           => ['required', 'array', 'min:1'],
            'classifiers.*'         => ['string', Rule::in($this->allowedClassifierTokens())],
            'allowed_departments'   => ['nullable', 'array'],
            'allowed_departments.*' => ['integer', 'exists:departments,id'],
        ]);
    }

    /**
     * Перезаписать привязки нумератора: снять его прежние привязки и назначить новые.
     * updateOrCreate переносит классификатор с другого нумератора (unique гарантирует 1 привязку).
     */
    private function syncBindings(Numerator $numerator, array $classifiers): void
    {
        NumeratorBinding::where('numerator_id', $numerator->id)->delete();

        foreach ($classifiers as $token) {
            [$type, $id] = explode(':', $token, 2);

            NumeratorBinding::updateOrCreate(
                ['classifier_type' => $type, 'classifier_id' => $id],
                ['numerator_id' => $numerator->id],
            );
        }
    }

    /** Список допустимых «тип:id» классификаторов для валидации. */
    private function allowedClassifierTokens(): array
    {
        $tokens = [];

        DocumentType::with('subtypes')->get()->each(function (DocumentType $type) use (&$tokens) {
            $tokens[] = 'document_type:' . $type->id;
            foreach ($type->subtypes as $subtype) {
                $tokens[] = 'document_subtype:' . $subtype->id;
            }
        });

        foreach (array_keys(Order::KINDS) as $code) {
            $tokens[] = 'order_kind:' . $code;
        }

        return $tokens;
    }
}
