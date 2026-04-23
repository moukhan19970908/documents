<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Bitrix24Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWithBitrix24 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function handle(Bitrix24Service $bitrix24): void
    {
        try {
            if ($this->document->activeApproval) {
                $stage = $this->document->activeApproval->activeStage();
                if ($stage) {
                    $approverIds = $stage->workflowStage->approvers()->pluck('approver_id');
                    foreach ($approverIds as $approverId) {
                        $approver = \App\Models\User::find($approverId);
                        if ($approver) {
                            $taskId = $bitrix24->createTask($this->document, $approver);
                            if ($taskId && !$this->document->bitrix24_task_id) {
                                $this->document->update(['bitrix24_task_id' => $taskId]);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('SyncWithBitrix24 failed: ' . $e->getMessage());
        }
    }
}
