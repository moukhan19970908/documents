<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\AuditService;
use App\Services\DocumentVersionService;
use App\Services\FileWatermarkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentFileController extends Controller
{
    public function __construct(
        private DocumentVersionService $versionService,
        private AuditService $auditService,
        private FileWatermarkService $watermark,
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

        // Согласованный документ скачивается со штампом-удостоверением, впечатанным в файл
        // (PDF через qpdf, растровые картинки через GD). Остальное отдаём потоком как есть.
        if ($this->watermark->canStamp($file, $document)) {
            $disk = Storage::disk('s3');

            if ($disk->exists($file->file_path)) {
                $bytes = $this->watermark->stamp($disk->get($file->file_path), $file, $document);

                return response($bytes, 200, [
                    'Content-Type'        => $file->mime_type,
                    'Content-Disposition' => 'attachment; filename="' . rawurlencode($file->file_name) . '"',
                    'Content-Length'      => strlen($bytes),
                ]);
            }
        }

        return $this->versionService->download($file);
    }

    public function preview(Document $document, DocumentFile $file)
    {
        $this->authorize('view', $document);

        try {
            $disk = Storage::disk('s3');

            if (!$disk->exists($file->file_path)) {
                abort(404, 'Файл не найден в хранилище.');
            }

            return response($disk->get($file->file_path), 200, [
                'Content-Type'        => $file->mime_type,
                'Content-Disposition' => 'inline; filename="' . rawurlencode($file->file_name) . '"',
                'Content-Length'      => $disk->size($file->file_path),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Не удалось получить файл документа из хранилища', [
                'document_id' => $document->id,
                'file_id'     => $file->id,
                'file_path'   => $file->file_path,
                'error'       => $e->getMessage(),
            ]);
            abort(404);
        }
    }
}
