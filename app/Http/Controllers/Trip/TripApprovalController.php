<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\TripRequest;
use App\Services\TripService;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripApprovalController extends Controller
{
    public function __construct(
        private TripService $tripService,
        private ApprovalService $approvalService,
    ) {}

    public function index()
    {
        $user  = auth()->user();
        $trips = $this->approvalService->getPendingForApprover($user, 'trip');
        return view('trips.approvals', compact('trips'));
    }

    public function approve(Request $request, TripRequest $trip)
    {
        $request->validate([
            'comment'   => ['nullable', 'string', 'max:1000'],
            'deputy_id' => ['nullable', 'integer', Rule::in($trip->user->deputyCandidates()->pluck('id'))],
        ]);
        $this->tripService->approve($trip, auth()->user(), $request->comment, $request->integer('deputy_id') ?: null);
        return back()->with('success', 'Заявка согласована.');
    }

    public function reject(Request $request, TripRequest $trip)
    {
        $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->tripService->reject($trip, auth()->user(), $request->comment);
        return back()->with('success', 'Заявка отклонена.');
    }

    public function revision(Request $request, TripRequest $trip)
    {
        $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->tripService->sendRevision($trip, auth()->user(), $request->comment);
        return back()->with('success', 'Заявка отправлена на доработку.');
    }
}
