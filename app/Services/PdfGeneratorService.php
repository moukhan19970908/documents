<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class PdfGeneratorService
{
    public function generateApprovalSheet(Document $document): string
    {
        $approval = $document->approvals()
            ->with(['stages.decisions.user', 'stages.workflowStage'])
            ->latest()
            ->first();

        // Use simple HTML if barryvdh/laravel-dompdf is not installed
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.approval_sheet', compact('document', 'approval'));
            $content = $pdf->output();
        } else {
            $html = view('pdf.approval_sheet', compact('document', 'approval'))->render();
            $content = $html;
        }

        $version = $document->files()->max('version') ?? 1;
        $path = "approvals/{$document->id}/approval_sheet_v{$version}.pdf";

        Storage::put($path, $content);

        return $path;
    }
}
