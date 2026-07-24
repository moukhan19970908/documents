<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DocumentCounter;
use App\Models\DocumentField;
use App\Models\DocumentSubtype;
use App\Models\DocumentType;
use App\Models\Numerator;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentTypeController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index(Request $request)
    {
        $directions  = Department::whereNull('parent_id')->orderBy('name')->get();
        $directionId = $request->integer('direction') ?: null;

        $query = DocumentType::with(['numerator', 'fields', 'subtypes'])
            ->withCount('documents')
            ->orderBy('name');

        // Фильтр по направлению: типы, доступные отделам выбранного направления.
        if ($directionId) {
            $deptIds = array_map('intval', Department::getDescendantIds($directionId));

            $matchingIds = DocumentType::whereNotNull('allowed_departments')
                ->pluck('allowed_departments', 'id')
                ->filter(fn ($depts) => array_intersect($deptIds, array_map('intval', (array) $depts)) !== [])
                ->keys()
                ->all();

            $query->whereIn('id', $matchingIds ?: [0]);
        }

        $documentTypes = $query->paginate(9)->withQueryString();

        return view('admin.document-types.index', compact('documentTypes', 'directions', 'directionId'));
    }

    public function create()
    {
        return view('admin.document-types.form', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validateType($request);

        $type = DB::transaction(function () use ($request, $validated) {
            $type = DocumentType::create($this->typeAttributes($validated));

            $this->syncNumerator($type, $validated['numbering'] ?? []);
            $this->syncFields($type, null, $request->input('fields', []));
            $this->syncSubtypes($type, $request->input('subtypes', []));

            return $type;
        });

        $this->auditService->log('document_type_created', $type);

        return redirect()->route('admin.document-types.edit', $type)->with('success', 'Тип документа создан.');
    }

    public function edit(DocumentType $documentType)
    {
        $documentType->load(['fields', 'numerator.counters.editor', 'subtypes.workflows', 'subtypes.fields', 'subtypes.numerator.counters.editor']);

        return view('admin.document-types.form', $this->formData($documentType));
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $validated = $this->validateType($request, $documentType);

        // The code lives inside the numbers and names of registered documents — history would
        // start referring to a code that no longer exists.
        if ($validated['code'] !== $documentType->code && $documentType->documents()->whereNotNull('number')->exists()) {
            throw ValidationException::withMessages([
                'code' => 'По этому типу уже есть зарегистрированные документы — код менять нельзя.',
            ]);
        }

        DB::transaction(function () use ($request, $validated, $documentType) {
            $documentType->update($this->typeAttributes($validated));

            $this->syncNumerator($documentType, $validated['numbering'] ?? []);
            $this->syncFields($documentType, null, $request->input('fields', []));
            $this->syncSubtypes($documentType, $request->input('subtypes', []));
        });

        $this->auditService->log('document_type_updated', $documentType);

        return redirect()->route('admin.document-types.edit', $documentType)->with('success', 'Тип документа сохранён.');
    }

    public function destroy(DocumentType $documentType)
    {
        if ($documentType->documents()->exists()) {
            return back()->with('error', 'По типу есть документы — его нельзя удалить, только деактивировать.');
        }

        $this->auditService->log('document_type_deleted', $documentType);
        $documentType->delete();

        return redirect()->route('admin.document-types.index')->with('success', 'Тип документа удалён.');
    }

    public function toggle(DocumentType $documentType)
    {
        $documentType->update(['is_active' => !$documentType->is_active]);

        $this->auditService->log($documentType->is_active ? 'document_type_activated' : 'document_type_deactivated', $documentType);

        return back()->with('success', $documentType->is_active
            ? 'Тип активирован.'
            : 'Тип деактивирован — новые документы по нему создать нельзя, существующие работают.');
    }

    /** A copy is born inactive and without a code: the code must be unique and is load-bearing. */
    public function duplicate(DocumentType $documentType)
    {
        $documentType->load(['fields', 'numerator', 'subtypes.workflows', 'subtypes.fields', 'subtypes.numerator']);

        $copy = DB::transaction(function () use ($documentType) {
            $copy = $documentType->replicate(['code', 'slug', 'numerator_id']);
            $copy->name = $documentType->name . ' (копия)';
            $copy->code = $this->uniqueCode($documentType->code);
            $copy->slug = Str::slug($copy->name);
            $copy->is_active = false;
            $copy->save();

            if ($documentType->numerator) {
                $copy->update(['numerator_id' => $this->replicateNumerator($documentType->numerator, $copy)->id]);
            }

            foreach ($documentType->fields as $field) {
                $new = $field->replicate();
                $new->document_type_id = $copy->id;
                $new->save();
            }

            foreach ($documentType->subtypes as $subtype) {
                $newSubtype = $subtype->replicate(['numerator_id']);
                $newSubtype->document_type_id = $copy->id;
                $newSubtype->save();

                if ($subtype->numerator) {
                    $newSubtype->update(['numerator_id' => $this->replicateNumerator($subtype->numerator, $newSubtype)->id]);
                }

                $newSubtype->workflows()->sync($subtype->workflows->pluck('id'));

                foreach ($subtype->fields as $field) {
                    $new = $field->replicate();
                    $new->document_type_id = $copy->id;
                    $new->document_subtype_id = $newSubtype->id;
                    $new->save();
                }
            }

            return $copy;
        });

        $this->auditService->log('document_type_duplicated', $copy, ['source_id' => $documentType->id], null);

        return redirect()->route('admin.document-types.edit', $copy)
            ->with('success', 'Тип продублирован. Задайте код и активируйте его.');
    }

    /** Direct counter edit — the paper-journal migration path. Always audited. */
    public function updateCounter(Request $request, DocumentType $documentType, DocumentCounter $counter)
    {
        $validated = $request->validate([
            'current_value' => ['required', 'integer', 'min:0'],
        ]);

        $old = (int) $counter->current_value;

        $counter->update([
            'current_value' => $validated['current_value'],
            'updated_by'    => auth()->id(),
        ]);

        $this->auditService->log('document_counter_changed', $counter,
            ['current_value' => $old],
            ['current_value' => (int) $validated['current_value']],
        );

        return back()->with('success', "Счётчик изменён: {$old} → {$validated['current_value']}. Следующий номер — " . ($validated['current_value'] + 1) . '.');
    }

    private function formData(?DocumentType $documentType = null): array
    {
        return [
            'documentType' => $documentType,
            'workflows'    => Workflow::where('is_active', true)->orderBy('name')->get(),
            'departments'  => Department::orderBy('name')->get(),
            'users'        => User::where('is_active', true)->where('role', '!=', 'external')->orderBy('name')->get(),
        ];
    }

    private function validateType(Request $request, ?DocumentType $documentType = null): array
    {
        $numberingRules = fn (string $prefix) => [
            "{$prefix}.mask"           => ['nullable', 'string', 'max:255'],
            "{$prefix}.reset_period"   => ['nullable', Rule::in(['none', 'monthly', 'yearly'])],
            "{$prefix}.padding"        => ['nullable', 'integer', 'min:1', 'max:12'],
            "{$prefix}.scope"          => ['nullable', 'array'],
            "{$prefix}.scope.*"        => [Rule::in(['type', 'subtype', 'department'])],
            "{$prefix}.assign_moment"  => ['nullable', Rule::in(['on_launch', 'on_approval'])],
            "{$prefix}.allow_manual"   => ['nullable', 'boolean'],
            "{$prefix}.manual_roles"   => ['nullable', 'array'],
            "{$prefix}.manual_roles.*" => [Rule::in(array_keys(DocumentType::CREATOR_ROLES))],
            "{$prefix}.start_value"    => ['nullable', 'integer', 'min:0'],
        ];

        return $request->validate(array_merge([
            'name'                   => ['required', 'string', 'max:255'],
            'code'                   => ['required', 'string', 'max:16', Rule::unique('document_types', 'code')->ignore($documentType)],
            'icon'                   => ['nullable', Rule::in(DocumentType::ICONS)],
            'description'            => ['nullable', 'string'],
            'is_active'              => ['nullable', 'boolean'],
            'name_template'          => ['nullable', 'string', 'max:255'],
            'allowed_departments'    => ['nullable', 'array'],
            'allowed_departments.*'  => ['integer', 'exists:departments,id'],
            'allowed_roles'          => ['nullable', 'array'],
            'allowed_roles.*'        => [Rule::in(array_keys(DocumentType::CREATOR_ROLES))],
            'allowed_users'          => ['nullable', 'array'],
            'allowed_users.*'        => ['integer', 'exists:users,id'],

            'fields'                 => ['nullable', 'array'],
            'fields.*.label'         => ['required', 'string', 'max:255'],
            'fields.*.field_key'     => ['required', 'string', 'max:64'],
            'fields.*.type_spec'     => ['required', 'string'],

            'subtypes'               => ['nullable', 'array'],
            'subtypes.*.name'        => ['required', 'string', 'max:255'],
            'subtypes.*.code'        => ['nullable', 'string', 'max:16'],
            'subtypes.*.workflows'   => ['nullable', 'array'],
            'subtypes.*.workflows.*' => ['integer', 'exists:workflows,id'],
        ], $numberingRules('numbering'), $numberingRules('subtypes.*.numerator')));
    }

    private function typeAttributes(array $validated): array
    {
        return [
            'name'                => $validated['name'],
            'code'                => $validated['code'],
            'slug'                => Str::slug($validated['name']) ?: Str::slug($validated['code']),
            'icon'                => $validated['icon'] ?? 'document',
            'description'         => $validated['description'] ?? null,
            'is_active'           => (bool) ($validated['is_active'] ?? false),
            'name_template'       => $validated['name_template'] ?? null,
            'allowed_departments' => $validated['allowed_departments'] ?? null,
            'allowed_roles'       => $validated['allowed_roles'] ?? null,
            'allowed_users'       => $validated['allowed_users'] ?? null,
        ];
    }

    /**
     * The numerator belongs to the type (or subtype) that owns it. An empty mask means
     * "no numbering": the numerator is dropped, unless counters already handed numbers out.
     */
    private function syncNumerator(Model $owner, array $config): void
    {
        $mask = trim($config['mask'] ?? '');
        $numerator = $owner->numerator_id ? Numerator::find($owner->numerator_id) : null;

        if ($mask === '') {
            if ($numerator) {
                $owner->update(['numerator_id' => null]);

                if (!$numerator->counters()->exists()) {
                    $numerator->delete();
                }
            }
            return;
        }

        $attributes = [
            'name'          => ($owner instanceof DocumentType ? 'Тип: ' : 'Подтип: ') . $owner->name,
            'mask'          => $mask,
            'scope'         => $config['scope'] ?? [],
            'reset_period'  => $config['reset_period'] ?? 'none',
            'padding'       => $config['padding'] ?? 1,
            'start_value'   => $config['start_value'] ?? 0,
            'assign_moment' => $config['assign_moment'] ?? 'on_launch',
            'allow_manual'  => (bool) ($config['allow_manual'] ?? false),
            'manual_roles'  => $config['manual_roles'] ?? [],
        ];

        if ($numerator) {
            $numerator->update($attributes);
            return;
        }

        $owner->update(['numerator_id' => Numerator::create($attributes)->id]);
    }

    private function syncFields(DocumentType $type, ?DocumentSubtype $subtype, array $fields): void
    {
        $keptIds = [];

        foreach (array_values($fields) as $i => $field) {
            [$fieldType, $referenceTo] = array_pad(explode(':', $field['type_spec'], 2), 2, null);

            $attributes = [
                'document_type_id'    => $type->id,
                'document_subtype_id' => $subtype?->id,
                'label'               => $field['label'],
                'field_key'           => $field['field_key'],
                'field_type'          => $fieldType,
                'reference_to'        => $referenceTo,
                'options'             => !empty($field['options'])
                    ? array_values(array_filter(array_map('trim', explode(',', $field['options']))))
                    : null,
                'is_required'         => !empty($field['is_required']),
                'use_in_name'         => !empty($field['use_in_name']),
                'use_in_filter'       => !empty($field['use_in_filter']),
                'use_in_archive'      => !empty($field['use_in_archive']),
                'sort_order'          => $i,
            ];

            $existing = !empty($field['id'])
                ? DocumentField::where('document_type_id', $type->id)->find($field['id'])
                : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = DocumentField::create($attributes)->id;
            }
        }

        $query = $subtype
            ? $subtype->fields()
            : $type->fields();

        $query->whereNotIn('document_fields.id', $keptIds ?: [0])->delete();
    }

    private function syncSubtypes(DocumentType $type, array $subtypes): void
    {
        $keptIds = [];

        foreach (array_values($subtypes) as $i => $data) {
            $attributes = [
                'document_type_id' => $type->id,
                'name'             => $data['name'],
                'code'             => $data['code'] ?? null,
                'is_active'        => !empty($data['is_active']),
                'sort_order'       => $i,
            ];

            $subtype = !empty($data['id']) ? $type->subtypes()->find($data['id']) : null;

            if ($subtype) {
                $subtype->update($attributes);
            } else {
                $subtype = DocumentSubtype::create($attributes);
            }

            $subtype->workflows()->sync($data['workflows'] ?? []);
            $this->syncNumerator($subtype, empty($data['own_numerator']) ? [] : ($data['numerator'] ?? []));

            // Subtype attributes are not on this form — only touch them if the form sent them.
            if (array_key_exists('fields', $data)) {
                $this->syncFields($type, $subtype, $data['fields']);
            }

            $keptIds[] = $subtype->id;
        }

        // A subtype with documents is kept — deleting it would orphan them.
        $type->subtypes()
            ->whereNotIn('id', $keptIds ?: [0])
            ->whereDoesntHave('documents')
            ->get()
            ->each->delete();
    }

    private function replicateNumerator(Numerator $numerator, Model $owner): Numerator
    {
        $copy = $numerator->replicate();
        $copy->name = ($owner instanceof DocumentType ? 'Тип: ' : 'Подтип: ') . $owner->name;
        $copy->save();

        return $copy;
    }

    private function uniqueCode(?string $code): string
    {
        $base = Str::limit((string) ($code ?: 'КОП'), 12, '');

        for ($i = 1; $i < 100; $i++) {
            $candidate = $base . '-' . $i;
            if (!DocumentType::where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return Str::random(8);
    }
}
