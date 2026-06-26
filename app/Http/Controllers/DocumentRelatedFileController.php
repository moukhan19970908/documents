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
        $path = $file->store("documents/{$document->id}/related", 's3');

        if ($path === false) {
            return back()->withErrors(['file' => 'Не удалось загрузить файл на сервер. Попробуйте снова.']);
        }

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

        $disk = Storage::disk('s3');

        try {
            if (!$disk->exists($file->file_path)) {
                abort(404);
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            abort(404);
        }

        return $disk->download($file->file_path, $file->file_name);
    }

    public function preview(Document $document, DocumentRelatedFile $file)
    {
        $this->authorize('view', $document);

        $disk = Storage::disk('s3');

        try {
            if (!$disk->exists($file->file_path)) {
                abort(404);
            }

            return response($disk->get($file->file_path), 200, [
                'Content-Type'        => $file->mime_type,
                'Content-Disposition' => 'inline; filename="' . rawurlencode($file->file_name) . '"',
                'Content-Length'      => $disk->size($file->file_path),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            abort(404);
        }
    }

    public function destroy(Document $document, DocumentRelatedFile $file)
    {
        $this->authorize('view', $document);

        $user = auth()->user();
        if ($file->uploaded_by !== $user->id && $user->role !== 'admin') {
            abort(403);
        }

        Storage::disk('s3')->delete($file->file_path);
        $file->delete();

        return back()->with('success', 'Файл удалён.');
    }
}
