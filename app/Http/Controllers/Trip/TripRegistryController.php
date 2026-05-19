<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\Registry;
use App\Models\TripRequest;
use App\Services\RegistryService;
use App\Services\ApprovalService;
use Illuminate\Http\Request;

class TripRegistryController extends Controller
{
    public function __construct(
        private RegistryService $registryService,
        private ApprovalService $approvalService,
    ) {}

    public function index()
    {
        $user       = auth()->user();
        $registries = Registry::where('type', 'trip')
            ->where(function ($q) use ($user) {
                if (!$user->isAdmin() && $user->role !== 'director') {
                    $q->where('created_by', $user->id);
                }
            })
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Incoming registries to approve
        $incoming = $this->approvalService->getPendingForApprover($user, 'trip');

        // Available trip requests to add to registry
        $availableTrips = TripRequest::where('status', 'approved')
            ->whereDoesntHave('registryItem')
            ->visibleBy($user)
            ->with('user')
            ->get();

        return view('trips.registries.index', compact('registries', 'incoming', 'availableTrips'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'trip_ids' => ['required', 'array', 'min:1'],
            'trip_ids.*' => ['exists:trip_requests,id'],
            'comment'  => ['nullable', 'string'],
        ]);

        $registry = $this->registryService->createTripRegistry(
            auth()->user(),
            $request->title,
            $request->trip_ids,
            $request->comment,
        );

        return redirect()->route('trips.registries.show', $registry)->with('success', 'Реестр создан.');
    }

    public function show(Registry $registry)
    {
        $registry->load(['items.tripRequest.user.department', 'creator', 'approvalLogs.approver']);
        return view('trips.registries.show', compact('registry'));
    }

    public function send(Registry $registry)
    {
        $this->registryService->submit($registry);
        return back()->with('success', 'Реестр отправлен на согласование.');
    }

    public function approve(Request $request, Registry $registry)
    {
        $request->validate(['comment' => ['nullable', 'string']]);
        $this->registryService->approve($registry, auth()->user(), $request->comment);
        return back()->with('success', 'Реестр согласован.');
    }

    public function reject(Request $request, Registry $registry)
    {
        $request->validate(['comment' => ['required', 'string']]);
        $this->registryService->reject($registry, auth()->user(), $request->comment);
        return back()->with('success', 'Реестр отклонён.');
    }

    public function sendToAccounting(Registry $registry)
    {
        $this->registryService->sendToAccounting($registry);
        return back()->with('success', 'Реестр передан в бухгалтерию.');
    }

    public function accept(Registry $registry)
    {
        $this->registryService->acceptByAccounting($registry, auth()->user());
        return back()->with('success', 'Реестр принят.');
    }
}
