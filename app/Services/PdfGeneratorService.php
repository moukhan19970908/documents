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

    /**
     * Лист по одной фазе маршрута: 'ack' — Лист ознакомления, 'intake' — Лист приёма.
     * Собирается из звеньев этой фазы последнего круга согласования.
     */
    public function generatePhaseSheet(Document $document, string $phase): string
    {
        $title = $phase === 'ack' ? 'ЛИСТ ОЗНАКОМЛЕНИЯ' : 'ЛИСТ ПРИЁМА';

        $approval = $document->approvals()
            ->with(['stages.decisions.user', 'stages.workflowStage.approvers.user.department'])
            ->latest()
            ->first();

        $stages = $approval
            ? $approval->phaseStages($phase)
            : collect();

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.phase_sheet', compact('document', 'stages', 'phase', 'title'));
            $content = $pdf->output();
        } else {
            $content = view('pdf.phase_sheet', compact('document', 'stages', 'phase', 'title'))->render();
        }

        $version = $document->files()->max('version') ?? 1;
        $path = "approvals/{$document->id}/{$phase}_sheet_v{$version}.pdf";

        Storage::put($path, $content);

        return $path;
    }
}
