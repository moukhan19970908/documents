<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentRelatedFile;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentRelatedFileController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function store(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        $request->validate([
            'file'        => ['required', 'file', 'max:51200'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store("documents/{$document->id}/related", 'local');

        $document->relatedFiles()->create([
            'uploaded_by' => auth()->id(),
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),
            'file_size'   => $file->getSize(),
            'mime_type'   => $file->getMimeType(),
            'description' => $request->input('description'),
        ]);

        $this->auditService->log('загрузил связанный файл: ' . $file->getClientOriginalName(), $document);

        return back()->with('success', 'Файл загружен.');
    }

    public function download(Document $document, DocumentRelatedFile $file)
    {
        $this->authorize('view', $document);

        if (!Storage::exists($file->file_path)) {
            abort(404);
        }

        return Storage::download($file->file_path, $file->file_name);
    }

    public function preview(Document $document, DocumentRelatedFile $file)
    {
        $this->authorize('view', $document);

        if (!Storage::exists($file->file_path)) {
            abort(404);
        }

        return response(Storage::get($file->file_path), 200, [
            'Content-Type'        => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . rawurlencode($file->file_name) . '"',
            'Content-Length'      => Storage::size($file->file_path),
        ]);
    }

    public function destroy(Document $document, DocumentRelatedFile $file)
    {
        $this->authorize('view', $document);

        $user = auth()->user();
        if ($file->uploaded_by !== $user->id && $user->role !== 'admin') {
            abort(403);
        }

        Storage::delete($file->file_path);
        $file->delete();

        return back()->with('success', 'Файл удалён.');
    }
}
