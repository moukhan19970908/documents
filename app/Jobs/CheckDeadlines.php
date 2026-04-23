<?php

namespace App\Jobs;

use App\Models\DocumentApprovalStage;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDeadlines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        // Mark overdue stages
        DocumentApprovalStage::query()
            ->where('status', 'in_progress')
            ->where('is_overdue', false)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now())
            ->update(['is_overdue' => true]);

        // Notify about stages that are about to expire (within 2 hours)
        $soonExpiring = DocumentApprovalStage::query()
            ->where('status', 'in_progress')
            ->whereNotNull('deadline_at')
            ->whereBetween('deadline_at', [now(), now()->addHours(2)])
            ->with(['documentApproval.document', 'workflowStage.approvers'])
            ->get();

        foreach ($soonExpiring as $stage) {
            $document = $stage->documentApproval->document;
            $approverIds = $stage->workflowStage->approvers()->pluck('approver_id');
            $approvers = \App\Models\User::whereIn('id', $approverIds)->get();

            foreach ($approvers as $approver) {
                $notificationService->notify($approver, 'deadline_soon', [
                    'title'       => $document->title,
                    'document_id' => $document->id,
                ]);
            }
        }
    }
}
