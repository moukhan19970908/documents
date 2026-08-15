<?php

namespace App\Services;

use App\Models\ArchivedDocument;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Document;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Помещает завершённый процесс в архив ровно одной неизменяемой копией
 * (файл + тело + лист согласования) со снимком метаданных. См. ТЗ 26.
 * Источники: документы, приказы (дело = приказ + ознакомления),
 * поручения (дело = поручение + подпоручения + файлы).
 */
class ArchiveService
{
    public function __construct(
        private PdfGeneratorService $pdf,
        private AuditService $audit,
    ) {}

    /** Заархивировать документ. Идемпотентно. */
    public function archiveDocument(Document $document): ArchivedDocument
    {
        if ($existing = $this->existing($document)) {
            return $existing;
        }

        $document->loadMissing(['type', 'subtype', 'initiator.department', 'currentFile']);
        [$directionId, $departmentId] = $this->dirDept($document->initiator?->department);

        $file = $document->currentFile;
        $archiveFilePath = null;
        if ($file && $file->file_path && Storage::exists($file->file_path)) {
            $archiveFilePath = $this->copy($file->file_path, "archive/documents/{$document->id}/document", $file->file_name);
        }

        $sheetPath = $this->snapshotApprovalSheet($document, "archive/documents/{$document->id}/approval_sheet.pdf");
        $ackSheetPath = $this->snapshotPhaseSheet($document, 'ack', "archive/documents/{$document->id}/acknowledgment_sheet.pdf");
        $intakeSheetPath = $this->snapshotPhaseSheet($document, 'intake', "archive/documents/{$document->id}/acceptance_sheet.pdf");

        return $this->store($document, [
            'title'               => $document->title,
            'number'              => $document->number,
            'document_type_id'    => $document->document_type_id,
            'document_subtype_id' => $document->document_subtype_id,
            'direction_id'        => $directionId,
            'department_id'       => $departmentId,
            'initiator_id'        => $document->initiator_id,
            'counterparty'        => $this->extractCounterparty($document),
            'metadata'            => $this->documentMetadata($document, $directionId),
            'body_html'           => $document->body_html,
            'file_path'           => $archiveFilePath,
            'file_name'           => $file?->file_name,
            'file_size'           => $file?->file_size,
            'approval_sheet_path' => $sheetPath,
            'acknowledgment_sheet_path' => $ackSheetPath,
            'acceptance_sheet_path'     => $intakeSheetPath,
        ]);
    }

    /** Заархивировать приказ (дело = приказ + записи ознакомления). Идемпотентно. */
    public function archiveOrder(Order $order): ArchivedDocument
    {
        if ($existing = $this->existing($order)) {
            return $existing;
        }

        $order->loadMissing(['initiator.department', 'acknowledgments.user', 'approvals.approver']);
        [$directionId, $departmentId] = $this->dirDept($order->initiator?->department);

        // Неизменяемая копия: загруженный файл либо отрендеренный бланк приказа.
        $archiveFilePath = null;
        $fileName = null;
        $fileSize = null;
        if ($order->file_path && Storage::exists($order->file_path)) {
            $archiveFilePath = $this->copy($order->file_path, "archive/orders/{$order->id}/document", $order->file_name);
            $fileName = $order->file_name;
            $fileSize = Storage::size($archiveFilePath);
        } else {
            [$archiveFilePath, $fileName] = $this->renderOrderPdf($order);
            $fileSize = $archiveFilePath ? Storage::size($archiveFilePath) : null;
        }

        return $this->store($order, [
            'title'         => $order->title,
            'number'        => $order->number,
            'direction_id'  => $directionId,
            'department_id' => $departmentId,
            'initiator_id'  => $order->initiator_id,
            'metadata'      => $this->orderMetadata($order, $directionId, $departmentId),
            'body_html'     => method_exists($order, 'renderedBody') ? $order->renderedBody() : $order->body_html,
            'file_path'     => $archiveFilePath,
            'file_name'     => $fileName,
            'file_size'     => $fileSize,
        ]);
    }

    /** Заархивировать поручение-дело (корень + подпоручения + файлы). Идемпотентно. */
    public function archiveAssignment(Assignment $assignment): ArchivedDocument
    {
        if ($existing = $this->existing($assignment)) {
            return $existing;
        }

        $assignment->loadMissing(['initiator.department', 'executor', 'controller', 'children.executor', 'files']);
        [$directionId, $departmentId] = $this->dirDept($assignment->initiator?->department);

        // Копируем все файлы дела; первый становится основным для скачивания.
        $files = [];
        $primaryPath = null;
        $primaryName = null;
        $primarySize = null;
        foreach ($assignment->files as $af) {
            if (! $af->path || ! Storage::exists($af->path)) {
                continue;
            }
            $dest = $this->copy($af->path, "archive/assignments/{$assignment->id}/" . pathinfo($af->path, PATHINFO_FILENAME), $af->original_name);
            $files[] = ['name' => $af->original_name, 'path' => $dest, 'size' => $af->size];
            if ($primaryPath === null) {
                [$primaryPath, $primaryName, $primarySize] = [$dest, $af->original_name, $af->size];
            }
        }

        return $this->store($assignment, [
            'title'         => $assignment->title,
            'number'        => $assignment->number,
            'direction_id'  => $directionId,
            'department_id' => $departmentId,
            'initiator_id'  => $assignment->initiator_id,
            'metadata'      => $this->assignmentMetadata($assignment, $directionId, $departmentId, $files),
            'body_html'     => $assignment->body_html,
            'file_path'     => $primaryPath,
            'file_name'     => $primaryName,
            'file_size'     => $primarySize,
        ]);
    }

    // ── общие помощники ──────────────────────────────────────────────────

    private function existing(Model $source): ?ArchivedDocument
    {
        return ArchivedDocument::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->first();
    }

    /** Создать архивную запись + аудит. */
    private function store(Model $source, array $attrs): ArchivedDocument
    {
        $archived = ArchivedDocument::create($attrs + [
            'source_type'  => $source->getMorphClass(),
            'source_id'    => $source->getKey(),
            'content_hash' => hash('sha256', ($attrs['body_html'] ?? '') . '|' . ($attrs['file_path'] ?? '')),
            'archived_at'  => now(),
            'archived_by'  => auth()->id(),
        ]);

        $this->audit->log('archived', $source);

        return $archived;
    }

    /** Скопировать файл в архив, сохранив расширение оригинала. Возвращает путь назначения. */
    private function copy(string $from, string $destWithoutExt, ?string $originalName): string
    {
        $ext = pathinfo($originalName ?: $from, PATHINFO_EXTENSION);
        $dest = $destWithoutExt . ($ext ? ".{$ext}" : '');
        Storage::put($dest, Storage::get($from));

        return $dest;
    }

    /** @return array{0: int|null, 1: int|null} [directionId, departmentId] */
    private function dirDept(?Department $dept): array
    {
        $departmentId = $dept?->id;
        $directionId = $departmentId ? Department::directionRootId($departmentId) : null;

        return [$directionId, $departmentId];
    }

    private function snapshotApprovalSheet(Document $document, string $dest): ?string
    {
        try {
            $generated = $this->pdf->generateApprovalSheet($document);
            if ($generated && Storage::exists($generated)) {
                Storage::put($dest, Storage::get($generated));
                return $dest;
            }
        } catch (\Throwable $e) {
            Log::warning("Archive: лист согласования документа {$document->id} не создан: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Снимок листа фазы (ознакомление / приём) — только если эта фаза была в маршруте.
     * Ознакомление не держит маршрут, поэтому к моменту архивации часть отметок может ещё
     * отсутствовать; лист фиксирует состояние как есть.
     */
    private function snapshotPhaseSheet(Document $document, string $phase, string $dest): ?string
    {
        try {
            $approval = $document->approvals()
                ->with(['stages.workflowStage.approvers'])
                ->latest()
                ->first();

            if (! $approval || $approval->phaseStages($phase)->isEmpty()) {
                return null; // такой фазы в маршруте не было — листа нет
            }

            $generated = $this->pdf->generatePhaseSheet($document, $phase);
            if ($generated && Storage::exists($generated)) {
                Storage::put($dest, Storage::get($generated));
                return $dest;
            }
        } catch (\Throwable $e) {
            Log::warning("Archive: лист фазы «{$phase}» документа {$document->id} не создан: {$e->getMessage()}");
        }

        return null;
    }

    /** @return array{0: string|null, 1: string|null} [archivePath, fileName] */
    private function renderOrderPdf(Order $order): array
    {
        try {
            $order->loadMissing('initiator');
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $content = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.order', ['order' => $order])->output();
                $dest = "archive/orders/{$order->id}/document.pdf";
                $name = ($order->number ?? 'order') . '.pdf';
            } else {
                $content = view('pdf.order', ['order' => $order])->render();
                $dest = "archive/orders/{$order->id}/document.html";
                $name = ($order->number ?? 'order') . '.html';
            }
            Storage::put($dest, $content);

            return [$dest, $name];
        } catch (\Throwable $e) {
            Log::warning("Archive: PDF приказа {$order->id} не создан: {$e->getMessage()}");
            return [null, null];
        }
    }

    private function extractCounterparty(Document $document): ?string
    {
        $data = $document->data ?? [];
        foreach (['contractor', 'counterparty', 'контрагент'] as $key) {
            if (! empty($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }

        return null;
    }

    private function documentMetadata(Document $document, ?int $directionId): array
    {
        $participants = $document->approvals()
            ->with('stages.decisions.user')
            ->get()
            ->flatMap(fn ($a) => $a->stages->flatMap(
                fn ($s) => $s->decisions->map(fn ($d) => $d->user?->name)
            ))
            ->filter()->unique()->values()->all();

        return [
            'kind'          => 'document',
            'type'          => $document->type?->name,
            'subtype'       => $document->subtype?->name,
            'department'    => $document->initiator?->department?->name,
            'direction'     => $this->directionName($directionId),
            'initiator'     => $document->initiator?->name,
            'registered_at' => optional($document->registered_at)->toDateString(),
            'approved_at'   => now()->toDateTimeString(),
            'participants'  => $participants,
        ];
    }

    private function orderMetadata(Order $order, ?int $directionId, ?int $departmentId): array
    {
        return [
            'kind'            => 'order',
            'order_kind'      => method_exists($order, 'kindLabel') ? $order->kindLabel() : $order->kind,
            'department'      => $order->initiator?->department?->name,
            'direction'       => $this->directionName($directionId),
            'initiator'       => $order->initiator?->name,
            'published_at'    => optional($order->published_at)->toDateTimeString(),
            'effective_at'    => optional($order->effective_at)->toDateString(),
            'acknowledgments' => $order->acknowledgments->map(fn ($a) => [
                'user'            => $a->user?->name,
                'acknowledged_at' => optional($a->acknowledged_at)->toDateTimeString(),
            ])->values()->all(),
            'approvers'       => $order->approvals->map(fn ($a) => [
                'user'   => $a->approver?->name,
                'role'   => $a->role_label,
                'status' => $a->status,
            ])->values()->all(),
        ];
    }

    private function assignmentMetadata(Assignment $assignment, ?int $directionId, ?int $departmentId, array $files): array
    {
        return [
            'kind'          => 'assignment',
            'status'        => $assignment->statusLabel(),
            'department'    => $assignment->initiator?->department?->name,
            'direction'     => $this->directionName($directionId),
            'initiator'     => $assignment->initiator?->name,
            'executor'      => $assignment->executor?->name,
            'controller'    => $assignment->controller?->name,
            'due_at'        => optional($assignment->due_at)->toDateString(),
            'accepted_at'   => optional($assignment->accepted_at)->toDateTimeString(),
            'result'        => $assignment->result_comment,
            'sub_assignments' => $assignment->children->map(fn ($c) => [
                'title'    => $c->title,
                'status'   => $c->statusLabel(),
                'executor' => $c->executor?->name,
            ])->values()->all(),
            'files'         => $files,
        ];
    }

    private function directionName(?int $directionId): ?string
    {
        return $directionId ? Department::whereKey($directionId)->value('name') : null;
    }
}
