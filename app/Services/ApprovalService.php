<?php

namespace App\Services;

use App\Models\ApprovalRoute;
use App\Models\ApprovalRouteStep;
use App\Models\TripApprovalLog;
use App\Models\User;

class ApprovalService
{
    /**
     * Find the best matching approval route for a user and request type.
     */
    public function findRoute(User $user, string $type): ?ApprovalRoute
    {
        return ApprovalRoute::where('request_type', $type)
            ->where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where('department_id', $user->department_id)
                  ->orWhereNull('department_id');
            })
            ->orderByRaw('department_id IS NULL ASC') // department-specific first
            ->first();
    }

    /**
     * Find which user should approve a given step for a given requester.
     */
    public function findApprover(User $requester, ApprovalRouteStep $step): ?User
    {
        if ($step->approver_user_id) {
            return User::find($step->approver_user_id);
        }

        if (!$step->approver_role_level) {
            return null;
        }

        // Walk up the manager chain to find someone with sufficient role_level
        $manager = $requester->manager;
        while ($manager) {
            if (($manager->role_level ?? 1) >= $step->approver_role_level) {
                return $manager;
            }
            $manager = $manager->manager;
        }

        // Fallback: find any active user with the required role_level
        return User::where('role_level', '>=', $step->approver_role_level)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Log an approval action.
     */
    public function log(string $type, int $requestId, int $step, int $approverId, string $action, ?string $comment = null): void
    {
        TripApprovalLog::create([
            'request_type' => $type,
            'request_id'   => $requestId,
            'step_number'  => $step,
            'approver_id'  => $approverId,
            'action'       => $action,
            'comment'      => $comment,
        ]);
    }

    /**
     * Get requests pending approval by the given user.
     */
    public function getPendingForApprover(User $approver, string $type): \Illuminate\Support\Collection
    {
        $model = $type === 'trip'
            ? \App\Models\TripRequest::class
            : \App\Models\VacationRequest::class;

        $pending = $model::where('status', 'pending')
            ->with(['user.department', 'route.steps'])
            ->get();

        return $pending->filter(function ($request) use ($approver) {
            if (!$request->route) {
                return false;
            }
            $step = $request->route->steps->firstWhere('step_order', $request->current_step);
            if (!$step) {
                return false;
            }
            // Specific user check
            if ($step->approver_user_id) {
                return $step->approver_user_id === $approver->id;
            }
            // Role level check — approver must have sufficient level
            if ($step->approver_role_level) {
                $approverLevel = $approver->role_level ?? 1;
                if ($approverLevel < $step->approver_role_level) {
                    return false;
                }
                // Approver must be in the requester's manager chain
                $manager = $request->user->manager;
                while ($manager) {
                    if ($manager->id === $approver->id) {
                        return true;
                    }
                    $manager = $manager->manager;
                }
                // Admins / directors see all
                return $approver->isAdmin() || $approver->role === 'director';
            }
            return false;
        });
    }
}
