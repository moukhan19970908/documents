<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\AuditService;
use App\Services\DocumentVersionService;
use Illuminate\Http\Request;

class DocumentFileController extends Controller
{
    public function __construct(
        private DocumentVersionService $versionService,
        private AuditService $auditService,
    ) {}

    public function store(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $this->versionService->storeFile($document, $request->file('file'));

        return back()->with('success', 'Файл загружен.');
    }

    public function download(Document $document, DocumentFile $file)
    {
        $this->authorize('view', $document);

        $this->auditService->log('file_downloaded', $document, null, ['file_id' => $file->id]);

        return $this->versionService->download($file);
    }
}
