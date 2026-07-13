<?php

namespace App\Http\Controllers\Vacation;

use App\Http\Controllers\Controller;
use App\Models\Registry;
use App\Models\VacationRequest;
use App\Services\RegistryService;
use App\Services\ApprovalService;
use Illuminate\Http\Request;

class VacationRegistryController extends Controller
{
    public function __construct(
        private RegistryService $registryService,
        private ApprovalService $approvalService,
    ) {}

    public function index()
    {
        $user       = auth()->user();
        $registries = Registry::where('type', 'vacation')
            ->where(function ($q) use ($user) {
                if (!$user->hasAnyRole(['admin', 'director'])) {
                    $q->where('created_by', $user->id);
                }
            })
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Incoming registries to approve
        $incoming = $this->approvalService->getPendingRegistriesForApprover($user, 'vacation');

        // Available vacation requests to add to registry
        $availableVacations = VacationRequest::where('status', 'approved')
            ->whereDoesntHave('registryItem')
            ->visibleBy($user)
            ->with('user')
            ->get();

        return view('vacations.registries.index', compact('registries', 'incoming', 'availableVacations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'vacation_ids'   => ['required', 'array', 'min:1'],
            'vacation_ids.*' => ['exists:vacation_requests,id'],
            'comment'        => ['nullable', 'string'],
        ]);

        if (!$this->approvalService->findRoute(auth()->user(), 'vacation_registry', false)) {
            return back()->withInput()->with('error', 'Не настроен маршрут согласования реестра отпусков для вашего отдела. Обратитесь к администратору.');
        }

        $registry = $this->registryService->createVacationRegistry(
            auth()->user(),
            $request->title,
            $request->vacation_ids,
            $request->comment,
        );

        return redirect()->route('vacations.registries.show', $registry)->with('success', 'Реестр создан.');
    }

    public function show(Registry $registry)
    {
        $registry->load(['items.vacationRequest.user.department', 'creator', 'approvalLogs.approver']);
        return view('vacations.registries.show', compact('registry'));
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
