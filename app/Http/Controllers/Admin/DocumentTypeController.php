<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Models\DocumentField;
use App\Models\Workflow;
use App\Services\AuditService;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index()
    {
        $documentTypes = DocumentType::with(['defaultWorkflow', 'fields'])->get();
        return view('admin.document-types.index', compact('documentTypes'));
    }

    public function create()
    {
        $workflows = Workflow::where('is_active', true)->get();
        return view('admin.document-types.form', compact('workflows'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'slug'                => ['nullable', 'string', 'alpha_dash', 'unique:document_types,slug'],
            'icon'                => ['nullable', 'string'],
            'default_workflow_id' => ['nullable', 'exists:workflows,id'],
            'fields'              => ['nullable', 'array'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }

        $type = DocumentType::create($validated);

        foreach ($request->input('fields', []) as $i => $field) {
            DocumentField::create(array_merge($field, [
                'document_type_id' => $type->id,
                'sort_order'       => $i,
            ]));
        }

        $this->auditService->log('document_type_created', $type);

        return redirect()->route('admin.document-types.index')->with('success', 'Тип документа создан.');
    }

    public function edit(DocumentType $documentType)
    {
        $workflows = Workflow::where('is_active', true)->get();
        $documentType->load('fields');
        return view('admin.document-types.form', compact('documentType', 'workflows'));
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'icon'                => ['nullable', 'string'],
            'default_workflow_id' => ['nullable', 'exists:workflows,id'],
        ]);

        $documentType->update($validated);
        $this->auditService->log('document_type_updated', $documentType);

        return redirect()->route('admin.document-types.index')->with('success', 'Тип документа обновлён.');
    }

    public function destroy(DocumentType $documentType)
    {
        $this->auditService->log('document_type_deleted', $documentType);
        $documentType->delete();
        return redirect()->route('admin.document-types.index')->with('success', 'Тип документа удалён.');
    }
}
