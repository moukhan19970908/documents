<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Chat;
use App\Models\Document;
use App\Models\DocumentApprovalStage;
use App\Models\Task;
use App\Models\Workflow;
use App\Models\TripRequest;
use App\Models\VacationRequest;
use App\Policies\ChatPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\WorkflowPolicy;
use App\Policies\TripRequestPolicy;
use App\Policies\VacationRequestPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Workflow::class, WorkflowPolicy::class);
        Gate::policy(Chat::class, ChatPolicy::class);
        Gate::policy(TripRequest::class, TripRequestPolicy::class);
        Gate::policy(VacationRequest::class, VacationRequestPolicy::class);
        // URL::forceScheme('https');

        // Sidebar badge counters: active tasks assigned to the user and
        // documents awaiting their approval decision. Recomputed per request
        // so they drop off once the task/stage is completed.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            if (! $user) {
                $view->with([
                    'menuActiveTasks' => 0, 'menuPendingApprovals' => 0, 'menuUnreadChats' => 0,
                    'menuTripApprovals' => 0, 'menuTripRegistries' => 0,
                    'menuVacationApprovals' => 0, 'menuVacationRegistries' => 0,
                ]);
                return;
            }

            $menuActiveTasks = Task::where('assignee_id', $user->id)
                ->where('status', 'pending')
                ->count();

            $menuPendingApprovals = DocumentApprovalStage::where('status', 'in_progress')
                ->where(fn($q) => $q
                    ->whereHas('workflowStage.approvers', fn($q2) => $q2->where('approver_id', $user->id))
                    ->orWhereHas('decisions', fn($q2) => $q2->where('action', 'delegate')->where('delegated_to', $user->id))
                )
                ->whereDoesntHave('decisions', fn($q) => $q->where('user_id', $user->id)->whereIn('action', ['approve', 'reject', 'delegate']))
                ->count();

            // Number of chats (not messages) that have at least one unread message
            // for the user: a message is unread when it's from someone else and
            // has no read receipt from this user.
            $menuUnreadChats = Chat::whereHas('participants', fn($q) => $q->where('user_id', $user->id))
                ->whereHas('messages', fn($q) => $q
                    ->where('user_id', '!=', $user->id)
                    ->whereDoesntHave('reads', fn($r) => $r->where('user_id', $user->id))
                )
                ->count();

            // Pending trip/vacation requests and registries awaiting this user's
            // approval. Guarded by the same conditions that show the menu links so
            // we don't run the queries for users who can't approve.
            $approvalService = app(\App\Services\ApprovalService::class);
            $isManager = $user->isManager();

            $menuTripApprovals = ($isManager || $user->isApprover('trip'))
                ? $approvalService->getPendingForApprover($user, 'trip')->count() : 0;
            $menuTripRegistries = $isManager
                ? $approvalService->getPendingRegistriesForApprover($user, 'trip')->count() : 0;
            $menuVacationApprovals = ($isManager || $user->isApprover('vacation'))
                ? $approvalService->getPendingForApprover($user, 'vacation')->count() : 0;
            $menuVacationRegistries = ($isManager || $user->isApprover('vacation_registry'))
                ? $approvalService->getPendingRegistriesForApprover($user, 'vacation')->count() : 0;

            $view->with(compact(
                'menuActiveTasks', 'menuPendingApprovals', 'menuUnreadChats',
                'menuTripApprovals', 'menuTripRegistries',
                'menuVacationApprovals', 'menuVacationRegistries',
            ));
        });
    }
}
