<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlankTemplate;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentNamingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Mews\Purifier\Facades\Purifier;

class BlankTemplateController extends Controller
{
    public function __construct(
        private AuditService $auditService,
        private DocumentNamingService $namingService,
    ) {}

    public function index(Request $request)
    {
        $search    = trim((string) $request->get('search'));
        $typeId    = $request->integer('type') ?: null;
        $subtypeId = $request->integer('subtype') ?: null;
        $authorId  = $request->integer('author') ?: null;
        $status    = $request->get('status'); // '', 'active', 'inactive'

        // Подтипы выбранного типа — для зависимого фильтра. Без типа фильтр по подтипу не имеет смысла.
        $subtypes = collect();
        if ($typeId) {
            $type = DocumentType::find($typeId);
            $subtypes = $type ? $type->subtypes()->orderBy('name')->get(['id', 'name']) : collect();
            if ($subtypeId && ! $subtypes->contains('id', $subtypeId)) {
                $subtypeId = null;
            }
        } else {
            $subtypeId = null;
        }

        $query = BlankTemplate::with(['type', 'subtype', 'author'])
            ->orderBy('document_type_id')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }
        if ($typeId) {
            $query->where('document_type_id', $typeId);
        }
        if ($subtypeId) {
            $query->where('document_subtype_id', $subtypeId);
        }
        if ($authorId) {
            $query->where('created_by', $authorId);
        }
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $templates = $query->get();

        $types   = DocumentType::orderBy('name')->get(['id', 'name']);
        $authors = User::whereIn('id', BlankTemplate::query()->select('created_by')->whereNotNull('created_by'))
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.blank-templates.index', compact(
            'templates', 'types', 'subtypes', 'authors',
            'search', 'typeId', 'subtypeId', 'authorId', 'status'
        ));
    }

    public function create()
    {
        return view('admin.blank-templates.form', $this->formData(new BlankTemplate(['is_active' => true])));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $template = BlankTemplate::create($validated + ['created_by' => auth()->id()]);

        $this->auditService->log('blank_template_created', $template);

        return redirect()->route('admin.blank-templates.edit', $template)->with('success', 'Шаблон бланка создан.');
    }

    public function edit(BlankTemplate $blankTemplate)
    {
        return view('admin.blank-templates.form', $this->formData($blankTemplate));
    }

    public function update(Request $request, BlankTemplate $blankTemplate)
    {
        $blankTemplate->update($this->validated($request));

        $this->auditService->log('blank_template_updated', $blankTemplate);

        return redirect()->route('admin.blank-templates.edit', $blankTemplate)->with('success', 'Шаблон бланка сохранён.');
    }

    public function destroy(BlankTemplate $blankTemplate)
    {
        $blankTemplate->delete();

        $this->auditService->log('blank_template_deleted', $blankTemplate);

        return redirect()->route('admin.blank-templates.index')->with('success', 'Шаблон бланка удалён.');
    }

    public function toggle(BlankTemplate $blankTemplate)
    {
        $blankTemplate->update(['is_active' => !$blankTemplate->is_active]);

        return back();
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:255'],
            'document_type_id'    => ['required', 'exists:document_types,id'],
            'document_subtype_id' => [
                'nullable',
                Rule::exists('document_subtypes', 'id')
                    ->where('document_type_id', $request->input('document_type_id')),
            ],
            'content'             => ['nullable', 'string'],
            'is_active'           => ['nullable', 'boolean'],
        ]);

        // Редактор отдаёт HTML — в базу он попадает только через белый список тегов.
        $validated['content']   = filled($validated['content'] ?? null)
            ? Purifier::clean($validated['content'], 'blank')
            : null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    /** Типы, их подтипы и токены, доступные бланку каждого типа. */
    private function formData(BlankTemplate $template): array
    {
        $types = DocumentType::with(['fields', 'subtypes.fields'])->orderBy('name')->get();

        $typesForForm = $types->map(fn (DocumentType $type) => [
            'id'       => $type->id,
            'name'     => $type->name,
            'subtypes' => $type->subtypes->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            // Токены те же, что у маски названия: служебные + атрибуты типа и его подтипов.
            'tokens'   => collect($this->namingService->availableTokens(
                $type->fields->concat($type->subtypes->flatMap->fields)
            ))->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ])->values();

        return ['template' => $template, 'typesForForm' => $typesForForm];
    }
}
