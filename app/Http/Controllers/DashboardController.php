<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentApprovalDecision;
use App\Models\DocumentApprovalStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $pendingApprovals = DocumentApprovalStage::query()
            ->where('status', 'in_progress')
            ->where(fn($q) => $q
                ->whereHas('workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id))
                ->orWhereHas('decisions', fn($q2) => $q2->where('action', 'delegate')->where('delegated_to', $user->id))
            )
            ->whereDoesntHave('decisions', fn($q) => $q->where('user_id', $user->id)->whereIn('action', ['approve', 'reject', 'delegate']))
            ->with([
                'documentApproval.document.type',
                'documentApproval.document.initiator',
                'documentApproval.document.activeApproval.stages.workflowStage.approvers.user',
                'documentApproval.document.activeApproval.stages.decisions',
                'documentApproval.document.latestApproval.stages.workflowStage.approvers.user',
                'workflowStage',
            ])
            ->orderBy('deadline_at')
            ->get()
            ->map(fn($stage) => [
                'stage'      => $stage,
                'document'   => $stage->documentApproval->document,
                'deadline'   => $stage->deadline_at,
                'is_overdue' => $stage->is_overdue,
                'status'     => $stage->status,
            ]);

        // Average approval time (started → completed) across finished approvals, in days.
        $completedApprovals = DocumentApproval::whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get(['started_at', 'completed_at']);
        $avgApprovalDays = $completedApprovals->isEmpty()
            ? null
            : round($completedApprovals->avg(fn($a) => $a->started_at->diffInHours($a->completed_at)) / 24, 1);

        $stats = [
            'pending_count'  => $pendingApprovals->count(),
            'processed_week' => DocumentApprovalDecision::where('user_id', $user->id)
                ->where('decided_at', '>=', now()->subWeek())->count(),
            'overdue_count'  => DocumentApprovalStage::where('status', 'in_progress')
                ->where(fn($q) => $q
                    ->whereHas('workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id))
                    ->orWhereHas('decisions', fn($q2) => $q2->where('action', 'delegate')->where('delegated_to', $user->id))
                )
                ->where('deadline_at', '<', now())
                ->count(),
            // Documents still moving through a process (not finalized/archived).
            // Админ считает по всей системе, остальные — только по своим документам.
            'in_work_count'    => Document::whereNotIn('status', ['signed', 'archived', 'rejected'])
                ->when(!$user->isAdmin(), fn ($q) => $q->whereIn('id', $this->involvedDocumentIds($user)))
                ->count(),
            'avg_approval_days' => $avgApprovalDays,
        ];

        // Documents grouped by status for the "Документы по статусам" panel.
        // Всю картину видит только администратор — остальным считаем лишь их документы.
        $statusCounts = Document::query()
            ->when(!$user->isAdmin(), fn ($q) => $q->whereIn('id', $this->involvedDocumentIds($user)))
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $statusBreakdown = [
            ['label' => 'На согласовании',    'count' => (int) $statusCounts->get('in_review', 0),                                         'color' => '#3B82F6'],
            ['label' => 'Утверждён',          'count' => (int) $statusCounts->get('approved', 0) + (int) $statusCounts->get('signed', 0), 'color' => '#22C55E'],
            ['label' => 'Требует изменений',  'count' => (int) $statusCounts->get('requires_changes', 0),                                  'color' => '#F97316'],
            ['label' => 'Отклонён / просрочен', 'count' => (int) $statusCounts->get('rejected', 0),                                        'color' => '#EF4444'],
            ['label' => 'Черновики',          'count' => (int) $statusCounts->get('draft', 0),                                            'color' => '#8B5CF6'],
        ];

        $approvalKeywords = ['начал процесс', 'согласовал', 'отказал', 'отправил на доработку', 'делегировал'];
        $activity = AuditLog::with('user')
            ->where(function ($q) use ($approvalKeywords) {
                foreach ($approvalKeywords as $keyword) {
                    $q->orWhere('action', 'LIKE', '%' . $keyword . '%');
                }
            })
            // Всю ленту видит только администратор — остальным события лишь их документов.
            ->when(!$user->isAdmin(), fn ($q) => $q
                ->where('model_type', Document::class)
                ->whereIn('model_id', $this->involvedDocumentIds($user))
            )
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.index', compact('pendingApprovals', 'stats', 'activity', 'statusBreakdown'));
    }

    /**
     * Документы, в которых пользователь участвует: инициатор, согласующий на любом звене
     * или принимал решение (в том числе получил делегирование). То же правило, что в реестре
     * документов, — дашборд не должен показывать больше, чем реестр.
     */
    private function involvedDocumentIds(User $user): Builder
    {
        return Document::select('id')->where(fn ($q) => $q
            ->where('initiator_id', $user->id)
            ->orWhereHas('approvals.stages.workflowStage.approvers', fn ($a) => $a->where('approver_id', $user->id))
            ->orWhereHas('approvals.stages.decisions', fn ($a) => $a
                ->where('delegated_to', $user->id)
                ->orWhere('user_id', $user->id)
            )
        );
    }

    public function myTasks()
    {
        $user = auth()->user();

        $pendingStages = DocumentApprovalStage::query()
            ->where('status', 'in_progress')
            ->where(fn($q) => $q
                ->whereHas('workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id))
                ->orWhereHas('decisions', fn($q2) => $q2->where('action', 'delegate')->where('delegated_to', $user->id))
            )
            ->whereDoesntHave('decisions', fn($q) => $q->where('user_id', $user->id)->whereIn('action', ['approve', 'reject', 'delegate']))
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
