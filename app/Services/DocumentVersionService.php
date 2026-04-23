<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentVersionService
{
    public function __construct(private AuditService $auditService) {}

    public function storeFile(Document $document, UploadedFile $file): DocumentFile
    {
        $document->files()->update(['is_current' => false]);

        $version = ($document->files()->max('version') ?? 0) + 1;

        $path = $file->store("documents/{$document->id}", 'local');

        $documentFile = $document->files()->create([
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),
            'file_size'   => $file->getSize(),
            'mime_type'   => $file->getMimeType(),
            'version'     => $version,
            'uploaded_by' => auth()->id(),
            'is_current'  => true,
        ]);

        $this->auditService->log('file_uploaded', $document, null, [
            'file_name' => $file->getClientOriginalName(),
            'version'   => $version,
        ]);

        return $documentFile;
    }

    public function download(DocumentFile $file): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::download($file->file_path, $file->file_name);
    }
}
