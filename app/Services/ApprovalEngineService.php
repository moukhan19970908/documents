<?php

namespace App\Services;

use App\Events\ApprovalStageChanged;
use App\Events\DocumentApproved;
use App\Events\DocumentCommentPosted;
use App\Events\DocumentRejected;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentApprovalDecision;
use App\Models\DocumentApprovalStage;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
use App\Models\Task;
use App\Services\Bitrix24Service;
use App\Services\ChatService;
use Illuminate\Support\Facades\DB;

class ApprovalEngineService
{
    public function __construct(
        private NotificationService $notificationService,
        private AuditService $auditService,
        private ChatService $chatService,
        private Bitrix24Service $bitrix24,
        private DocumentNumberService $numberService,
        private DocumentNamingService $namingService,
    ) {}

    public function startAdHocApproval(Document $doc, array $approverIds): DocumentApproval
    {
        return DB::transaction(function () use ($doc, $approverIds) {
            $workflow = Workflow::create([
                'name'       => 'Согласование: ' . $doc->title,
                'created_by' => auth()->id(),
                'is_system'  => false,
                'is_active'  => false,
            ]);

            $stage = WorkflowStage::create([
                'workflow_id' => $workflow->id,
                'name'        => 'Согласование',
                'stage_type'  => 'parallel',
                'sort_order'  => 1,
            ]);

            foreach ($approverIds as $userId) {
                WorkflowStageApprover::create([
                    'workflow_stage_id' => $stage->id,
                    'approver_type'     => 'user',
                    'approver_id'       => $userId,
                    'is_required'       => true,
                ]);
            }

            return $this->startApproval($doc, $workflow);
        });
    }

    /**
     * @param array $parameterValues answers to the scenario's launch parameters (v2 only) —
     *                               they decide which stages join the route and are frozen here.
     * @param array $adhoc           участники, добавленные инициатором при запуске:
     *                               ['ack' => [id, ...], 'intake' => [id, ...]]
     */
    public function startApproval(Document $doc, Workflow $workflow, array $parameterValues = [], array $adhoc = []): DocumentApproval    {
        // v2 runs off an immutable published version, so editing the scenario never rewrites a
        // route that is already in flight. v1 keeps running off its live stages, as before.
        $definition = $workflow->isV2() && !$workflow->is_version
            ? $this->publishedVersionOrFail($workflow)
            : $workflow;

        // Добавленные участники ознакомления и приёма — свойство этого документа, а не сценария.
        // Поэтому маршрут форкается в копию, которую видит только он: общая версия не меняется.
        if ($this->hasAdhoc($adhoc)) {
            $definition = $this->forkForDocument($definition, $doc, $adhoc);
        }

        return DB::transaction(function () use ($doc, $definition, $parameterValues) {
            // Registration happens here — the launch is the moment a draft becomes a numbered
            // document. Idempotent, so a resubmit keeps the original number.
            $this->registerDocument($doc, 'on_launch');

            $approval = DocumentApproval::create([
                'document_id'      => $doc->id,
                'workflow_id'      => $definition->id,
                'parameter_values' => $parameterValues ?: null,
                'started_at'       => now(),
                'status'           => 'in_progress',
            ]);

            $usedBranchGroups = [];

            foreach ($definition->stages as $stage) {
                // A stage with an unmet condition simply never enters this document's route.
                if ($definition->isV2() && !$stage->passesCondition($parameterValues)) {
                    continue;
                }

                // Развилка: из всех подходящих веток одной группы срабатывает первая.
                if ($stage->branch_group) {
                    if (in_array($stage->branch_group, $usedBranchGroups, true)) {
                        continue;
                    }
                    $usedBranchGroups[] = $stage->branch_group;
                }

                $deadline = match (true) {
                    (bool) $stage->sla_days       => now()->addWeekdays($stage->sla_days),
                    (bool) $stage->deadline_hours => now()->addHours($stage->deadline_hours),
                    default                       => null,
                };

                DocumentApprovalStage::create([
                    'document_approval_id' => $approval->id,
                    'workflow_stage_id'    => $stage->id,
                    'status'               => 'pending',
                    'deadline_at'          => $deadline,
                ]);
            }

            $doc->update(['status' => 'in_review']);

            $this->activateNextStage($approval);

            $this->auditService->log('approval_started', $doc, null, [
                'workflow_id'      => $definition->id,
                'parameter_values' => $parameterValues,
            ]);

            $this->chatService->createForProcess($approval);

            return $approval;
        });
    }

    /** @param array<string, int[]> $adhoc */
    private function hasAdhoc(array $adhoc): bool
    {
        return (bool) array_filter(array_map('array_filter', array_values($adhoc)));
    }

    /**
     * Копия маршрута под один документ: те же звенья и те же исполнители, плюс участники,
     * которых инициатор добавил в фазы ознакомления и приёма. Копия помечена как версия и
     * неактивна, поэтому не попадает ни в один список сценариев.
     *
     * @param array<string, int[]> $adhoc
     */
    private function forkForDocument(Workflow $definition, Document $doc, array $adhoc): Workflow
    {
        $definition->loadMissing('stages.approvers');

        return DB::transaction(function () use ($definition, $doc, $adhoc) {
            // Родитель форка — сама версия, а не сценарий, и published_at пуст: иначе форк
            // попал бы в цепочку версий сценария и publishedVersion() отдавал бы его
            // следующим документам как «последнюю публикацию».
            $fork = $definition->replicate(['published_at']);
            $fork->is_version         = true;
            $fork->is_active          = false;
            $fork->parent_workflow_id = $definition->id;
            $fork->version_label      = 'документ #' . $doc->id;
            $fork->published_at       = null;
            $fork->save();

            $order = 0;

            foreach ($definition->stages as $stage) {
                $copy = $stage->replicate();
                $copy->workflow_id = $fork->id;
                $copy->sort_order  = $order++;
                $copy->save();

                foreach ($stage->approvers as $approver) {
                    $approverCopy = $approver->replicate();
                    $approverCopy->workflow_stage_id = $copy->id;
                    $approverCopy->save();
                }
            }

            foreach (['ack', 'intake'] as $phase) {
                $userIds = array_filter($adhoc[$phase] ?? []);

                if (!$userIds) {
                    continue;
                }

                // Есть такое звено в сценарии — дописываем людей в него; нет — заводим звено
                // только для этого документа.
                $stage = $fork->stages()->where('phase', $phase)->orderBy('sort_order')->first()
                    ?? WorkflowStage::create([
                        'workflow_id' => $fork->id,
                        'name'        => $phase === 'ack' ? 'Ознакомление' : 'Приём',
                        'phase'       => $phase,
                        'stage_type'  => 'parallel',
                        'policy'      => 'all',
                        'resolver'    => 'user',
                        'is_blocking' => false,
                        'sort_order'  => $order++,
                    ]);

                $existing = $stage->approvers()->pluck('approver_id')->all();

                foreach (array_diff($userIds, $existing) as $userId) {
                    WorkflowStageApprover::create([
                        'workflow_stage_id' => $stage->id,
                        'approver_type'     => 'user',
                        'approver_id'       => $userId,
                        'is_required'       => true,
                        'participant_type'  => 'signatory',
                    ]);
                }
            }

            return $fork->load('stages.approvers');
        });
    }

    private function publishedVersionOrFail(Workflow $workflow): Workflow
    {
        $version = $workflow->publishedVersion();

        if (!$version) {
            throw new \RuntimeException("Сценарий «{$workflow->name}» не опубликован — запускать нечего.");
        }

        return $version->load('stages.approvers');
    }

    public function processDecision(
        DocumentApprovalStage $stage,
        User $user,
        string $action,
        ?string $comment = null,
        ?int $delegatedTo = null
    ): void {
        DB::transaction(function () use ($stage, $user, $action, $comment, $delegatedTo) {
            DocumentApprovalDecision::create([
                'document_approval_stage_id' => $stage->id,
                'user_id'     => $user->id,
                'action'      => $action,
                'comment'     => $comment,
                'delegated_to' => $delegatedTo,
                'decided_at'  => now(),
            ]);

            $approval = $stage->documentApproval;
            $document = $approval->document;

            // Человек высказался — его задача закрыта, даже если звено ещё ждёт остальных.
            // Отказ и доработка снимают задачи всего звена, поэтому их здесь нет.
            if (in_array($action, ['approve', 'opinion_yes', 'opinion_no', 'acknowledge', 'accept', 'execute', 'delegate'], true)) {
                $this->completeTaskFor($stage, $user);
            }

            match($action) {
                'approve'                    => $this->handleApprove($stage, $approval, $document),
                'reject'                     => $this->handleReject($stage, $approval, $document, $user, $comment),
                'request_changes'            => $this->handleRequestChanges($stage, $approval, $document, $user, $comment),
                'delegate'                   => $this->handleDelegate($stage, $document, $delegatedTo),
                'process_approve', 'process_reject' => $this->handleProcessDecision($stage, $approval),
                // Заключение и ознакомление фиксируются, но судьбу документа не решают.
                'opinion_yes', 'opinion_no', 'acknowledge' => $this->handleAdvisory($stage, $approval),
                'accept'                     => $this->handleAccept($stage),
                'execute'                    => $this->handleExecute($stage, $approval),
            };

            $this->auditService->log("decision_{$action}", $document, null, [
                'stage_id' => $stage->id,
                'user_id'  => $user->id,
                'comment'  => $comment,
            ]);
        });

        // Notify open document viewers of the new comment in real time.
        if (filled($comment)) {
            $document = $stage->documentApproval->document;
            event(new DocumentCommentPosted($document, $user, $comment));
        }
    }

    private function handleApprove(
        DocumentApprovalStage $stage,
        DocumentApproval $approval,
        Document $document
    ): void {
        $workflowStage = $stage->workflowStage;
        $requiredApprovers = $workflowStage->approvers()
            ->where('is_required', true)
            ->where('participant_type', 'signatory')
            ->pluck('approver_id');
        $approvedUserIds = $stage->decisions()->where('action', 'approve')->pluck('user_id');

        $allApproved = $requiredApprovers->diff($approvedUserIds)->isEmpty();

        if ($workflowStage->stage_type === 'parallel' && !$allApproved) {
            return; // Wait for all parallel approvers
        }

        if ($allApproved || $workflowStage->stage_type === 'sequential') {
            $stage->update(['status' => 'approved', 'completed_at' => now()]);
            $this->completeTasksForStage($stage);
            event(new ApprovalStageChanged($stage));
            $this->moveToNextStage($approval);
        }
    }

    /**
     * Заключения и ознакомление: решение остаётся в истории, но не отклоняет документ.
     * Звено закрывается, когда высказались все участники, и маршрут идёт дальше в любом случае.
     */
    private function handleAdvisory(DocumentApprovalStage $stage, DocumentApproval $approval): void
    {
        $participants = $stage->workflowStage->approvers()->pluck('approver_id');
        $responded = $stage->decisions()->pluck('user_id');

        $everyoneResponded = $participants->diff($responded)->isEmpty();

        if (!$everyoneResponded && $stage->workflowStage->policy !== 'any') {
            return;
        }

        $stage->update(['status' => 'approved', 'completed_at' => now()]);
        $this->completeTasksForStage($stage);
        event(new ApprovalStageChanged($stage));
        $this->moveToNextStage($approval);
    }

    /** Приём в два шага: сначала «принять к исполнению», и только потом «исполнено». */
    private function handleAccept(DocumentApprovalStage $stage): void
    {
        $stage->update(['status' => 'accepted']);
        event(new ApprovalStageChanged($stage));
    }

    private function handleExecute(DocumentApprovalStage $stage, DocumentApproval $approval): void
    {
        $stage->update(['status' => 'approved', 'completed_at' => now()]);
        $this->completeTasksForStage($stage);
        event(new ApprovalStageChanged($stage));
        $this->moveToNextStage($approval);
    }

    private function handleReject(
        DocumentApprovalStage $stage,
        DocumentApproval $approval,
        Document $document,
        User $user,
        ?string $comment
    ): void {
        // v2 stages may choose to send the document back for revision instead of killing it.
        // v1 stages default to 'reject', so their behaviour is unchanged.
        if ($stage->workflowStage->on_reject === 'return_initiator') {
            $this->handleRequestChanges($stage, $approval, $document, $user, $comment);
            return;
        }

        $stage->update(['status' => 'rejected', 'completed_at' => now()]);
        $this->cancelTasksForStage($stage);
        $approval->update(['status' => 'rejected', 'completed_at' => now()]);
        $document->update(['status' => 'rejected']);

        $this->notificationService->notify($document->initiator, 'document_rejected', [
            'title'      => $document->title,
            'comment'    => $comment,
            'document_id' => $document->id,
        ]);

        $this->bitrix24->createRejectionTask(
            $document,
            $document->initiator,
            $user,
            $comment,
            $stage->deadline_at
        );

        event(new DocumentRejected($document, $user, $comment));
    }

    private function handleRequestChanges(
        DocumentApprovalStage $stage,
        DocumentApproval $approval,
        Document $document,
        User $user,
        ?string $comment
    ): void {
        $stage->update(['status' => 'requires_changes', 'completed_at' => now()]);
        $this->cancelTasksForStage($stage);
        $approval->update(['status' => 'requires_changes']);
        $document->update(['status' => 'requires_changes']);

        $this->notificationService->notify($document->initiator, 'document_requires_changes', [
            'title'       => $document->title,
            'comment'     => $comment,
            'document_id' => $document->id,
            'reviewer'    => $user->name,
        ]);

        $this->bitrix24->createRevisionTask(
            $document,
            $document->initiator,
            $user,
            $comment,
            $stage->deadline_at
        );

        event(new DocumentRejected($document, $user, $comment));
    }

    private function handleProcessDecision(
        DocumentApprovalStage $stage,
        DocumentApproval $approval
    ): void {
        $requiredSignatories = $stage->workflowStage->approvers()
            ->where('is_required', true)
            ->where('participant_type', 'signatory')
            ->pluck('approver_id');

        $approvedUserIds = $stage->decisions()->where('action', 'approve')->pluck('user_id');
        $allSignatoriesApproved = $requiredSignatories->diff($approvedUserIds)->isEmpty();

        if ($allSignatoriesApproved) {
            $stage->update(['status' => 'approved', 'completed_at' => now()]);
            $this->completeTasksForStage($stage);
            event(new ApprovalStageChanged($stage));
            $this->moveToNextStage($approval);
        }
    }

    private function handleDelegate(
        DocumentApprovalStage $stage,
        Document $document,
        ?int $delegatedTo
    ): void {
        if (!$delegatedTo) {
            return;
        }

        $newUser = User::find($delegatedTo);
        if ($newUser) {
            // Задача переходит вместе с полномочием: у делегировавшего она уже закрыта
            // решением, а тому, кому делегировали, её надо выдать — иначе действовать
            // он может, а в «Моих задачах» документа не увидит.
            Task::create([
                'document_id'                => $document->id,
                'document_approval_stage_id' => $stage->id,
                'assignee_id'                => $newUser->id,
                'title'                      => 'Согласовать: ' . $document->title,
                'status'                     => 'pending',
                'deadline_at'                => $stage->deadline_at,
            ]);

            $this->notificationService->notify($newUser, 'delegated_to_you', [
                'title'       => $document->title,
                'document_id' => $document->id,
            ]);
        }
    }

    private function moveToNextStage(DocumentApproval $approval): void
    {
        $currentStage = $approval->stages()->where('status', 'in_progress')->first();
        $nextStage = $approval->stages()
            ->where('status', 'pending')
            ->whereHas('workflowStage', fn($q) => $q->orderBy('sort_order'))
            ->first();

        if ($nextStage) {
            $this->activateStage($nextStage, $approval);
        } else {
            $this->completeApproval($approval);
        }
    }

    private function activateNextStage(DocumentApproval $approval): void
    {
        $firstStage = $approval->stages()
            ->where('status', 'pending')
            ->first();

        if ($firstStage) {
            $this->activateStage($firstStage, $approval);
        }
    }

    private function activateStage(DocumentApprovalStage $stage, DocumentApproval $approval): void
    {
        $stage->update(['status' => 'in_progress', 'started_at' => now()]);

        $document = $approval->document;

        // Determine sender: last approver of previous stage, or document initiator for the first stage
        $previousStage = $approval->stages()
            ->where('status', 'approved')
            ->orderByDesc('completed_at')
            ->first();

        if ($previousStage) {
            $lastDecision = $previousStage->decisions()
                ->where('action', 'approve')
                ->orderByDesc('decided_at')
                ->first();
            $sender = $lastDecision?->user ?? $document->initiator;
        } else {
            $sender = $document->initiator;
        }

        $approverIds = $stage->workflowStage->approvers()->pluck('approver_id');
        $approvers = User::whereIn('id', $approverIds)->get();

        foreach ($approvers as $approver) {
            $task = Task::create([
                'document_id'                => $document->id,
                'document_approval_stage_id' => $stage->id,
                'assignee_id'                => $approver->id,
                'title'                      => 'Согласовать: ' . $document->title,
                'status'                     => 'pending',
                'deadline_at'                => $stage->deadline_at,
            ]);

            $bitrix24TaskId = $this->bitrix24->createApprovalTask(
                $document,
                $approver,
                $sender,
                $stage->deadline_at
            );
            if ($bitrix24TaskId) {
                $task->update(['bitrix24_task_id' => $bitrix24TaskId]);
            }

            $this->notificationService->notify($approver, 'new_document', [
                'title'       => $document->title,
                'document_id' => $document->id,
            ]);
        }

        // A non-blocking stage (ознакомление) hands out its tasks but does not hold the route:
        // the document moves on immediately, participants read it in their own time.
        // Только совещательное звено вправе так поступить: согласование, утверждение и приём
        // ждут решения даже если флаг в данных говорит обратное.
        if (!$stage->workflowStage->is_blocking && $stage->workflowStage->isAdvisory()) {
            $stage->update(['status' => 'approved', 'completed_at' => now()]);
            $this->moveToNextStage($approval);
        }
    }

    /** Задача одного участника звена: закрывается его собственным решением. */
    private function completeTaskFor(DocumentApprovalStage $stage, User $user): void
    {
        $tasks = Task::where('document_approval_stage_id', $stage->id)
            ->where('assignee_id', $user->id)
            ->where('status', 'pending')
            ->get();

        foreach ($tasks as $task) {
            if ($task->bitrix24_task_id) {
                $this->bitrix24->completeTask($task->bitrix24_task_id);
            }
            $task->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    private function completeTasksForStage(DocumentApprovalStage $stage): void
    {
        $tasks = Task::where('document_approval_stage_id', $stage->id)
            ->where('status', 'pending')
            ->get();

        foreach ($tasks as $task) {
            if ($task->bitrix24_task_id) {
                $this->bitrix24->completeTask($task->bitrix24_task_id);
            }
            $task->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    private function cancelTasksForStage(DocumentApprovalStage $stage): void
    {
        $tasks = Task::where('document_approval_stage_id', $stage->id)
            ->where('status', 'pending')
            ->get();

        foreach ($tasks as $task) {
            if ($task->bitrix24_task_id) {
                $this->bitrix24->completeTask($task->bitrix24_task_id);
            }
            $task->update(['status' => 'cancelled']);
        }
    }

    private function completeApproval(DocumentApproval $approval): void
    {
        $approval->update(['status' => 'approved', 'completed_at' => now()]);
        $document = $approval->document;
        $document->update(['status' => 'approved']);

        $this->registerDocument($document, 'on_approval');

        $this->notificationService->notify($document->initiator, 'document_approved', [
            'title'       => $document->title,
            'document_id' => $document->id,
        ]);

        event(new DocumentApproved($document));

        $this->auditService->log('document_approved', $document);
    }

    /**
     * Assigns a number if the type's numerator fires at this moment, then rebuilds the name so
     * the ___ / __.__.____ stubs of the draft are replaced by the real number and date.
     */
    private function registerDocument(Document $document, string $moment): void
    {
        $number = $this->numberService->registerIfDue($document, $moment, auth()->user());

        if (!$number) {
            return;
        }

        $name = $this->namingService->forDocument($document->refresh());

        if ($name) {
            $document->update(['title' => $name]);
        }
    }
}
