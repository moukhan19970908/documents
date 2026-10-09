<?php

namespace App\Http\Controllers\Vacation;

use App\Http\Controllers\Controller;
use App\Models\VacationRequest;
use App\Services\VacationService;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VacationApprovalController extends Controller
{
    public function __construct(
        private VacationService $vacationService,
        private ApprovalService $approvalService,
    ) {}

    public function index()
    {
        $user      = auth()->user();
        $vacations = $this->approvalService->getPendingForApprover($user, 'vacation');
        return view('vacations.approvals', compact('vacations'));
    }

    public function approve(Request $request, VacationRequest $vacation)
    {
        $request->validate([
            'comment'   => ['nullable', 'string', 'max:1000'],
            'deputy_id' => ['nullable', 'integer', Rule::in($vacation->user->deputyCandidates()->pluck('id'))],
        ]);
        $this->vacationService->approve($vacation, auth()->user(), $request->comment, $request->integer('deputy_id') ?: null);
        return back()->with('success', 'Заявка согласована.');
    }

    public function reject(Request $request, VacationRequest $vacation)
    {
        $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->vacationService->reject($vacation, auth()->user(), $request->comment);
        return back()->with('success', 'Заявка отклонена.');
    }

    public function revision(Request $request, VacationRequest $vacation)
    {
        $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->vacationService->sendRevision($vacation, auth()->user(), $request->comment);
        return back()->with('success', 'Заявка отправлена на доработку.');
    }
}
