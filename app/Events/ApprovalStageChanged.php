<?php

namespace App\Events;

use App\Models\DocumentApprovalStage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalStageChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public DocumentApprovalStage $stage) {}
}
