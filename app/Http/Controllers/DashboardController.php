<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentApproval;
use App\Models\DocumentApprovalDecision;
use App\Models\DocumentApprovalStage;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $pendingApprovals = DocumentApprovalStage::query()
            ->where('status', 'in_progress')
            ->whereHas('workflowStage.approvers', fn($q) => $q->where('approver_id', $user->id))
            ->with(['documentApproval.document', 'workflowStage'])
            ->orderBy('deadline_at')
            ->get()
            ->map(fn($stage) => [
                'stage'      => $stage,
                'document'   => $stage->documentApproval->document,
                'deadline'   => $stage->deadline_at,
                'is_overdue' => $stage->is_overdue,
                'status'     => $stage->status,
            ]);

        $stats = [
            'pending_count'  => $pendingApprovals->count(),
            'processed_week' => DocumentApprovalDecision::where('user_id', $user->id)
                ->where('decided_at', '>=', now()->subWeek())->count(),
            'active_phases'  => DocumentApproval::where('status', 'in_progress')->count(),
        ];

        $activity = AuditLog::with('user')
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.index', compact('pendingApprovals', 'stats', 'activity'));
    }

    public function myTasks()
    {
        $user = auth()->user();

        $pendingStages = DocumentApprovalStage::query()
            ->where('status', 'in_progress')
            ->whereHas('workflowStage.approvers', fn($q) => $q->where('approver_id', $user->id))
            ->with(['documentApproval.document.type', 'documentApproval.document.currentFile', 'workflowStage'])
            ->orderBy('deadline_at')
            ->get();

        $urgentStages = $pendingStages->filter(fn($s) => $s->is_overdue || ($s->deadline_at && $s->deadline_at->lt(now()->addHours(24))));

        $archivedDecisions = DocumentApprovalDecision::where('user_id', $user->id)
            ->whereIn('action', ['approve', 'reject'])
            ->with(['stage.documentApproval.document'])
            ->latest('decided_at')
            ->limit(20)
            ->get();

        return view('tasks.index', compact('pendingStages', 'urgentStages', 'archivedDecisions'));
    }
}
