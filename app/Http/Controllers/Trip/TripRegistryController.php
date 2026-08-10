<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\Registry;
use App\Models\RegistryItem;
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
                if ($user->hasAnyRole(['admin', 'director'])) {
                    return; // видят все
                }
                $q->where('created_by', $user->id);
                // Бухгалтерия видит переданные ей реестры, кем бы они ни были собраны (общий пул).
                if ($user->isAccounting()) {
                    $q->orWhereIn('status', ['sent_to_accounting', 'accepted_by_accounting']);
                }
            })
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Incoming registries to approve
        $incoming = $this->approvalService->getPendingRegistriesForApprover($user, 'trip');

        // Пул заявок: одобренные заявки ТОЛЬКО линейных сотрудников (руководители идут
        // индивидуальным маршрутом, без реестра — ТЗ 18), ещё не в активном реестре.
        $availableTrips = TripRequest::where('status', 'approved')
            ->whereDoesntHave('registryItem', fn ($q) => $q->where('status', 'active'))
            ->whereHas('user', fn ($q) => $q
                ->where(fn ($qq) => $qq->whereNull('role_level')->orWhere('role_level', '<', 2))
                ->whereNotIn('role', ['admin', 'director', 'ceo', 'chief_of_staff']))
            ->visibleBy($user)
            ->with('user')
            ->get();

        // Выбывшие: позиции, выведенные из моих реестров на доработку (ТЗ 18.4).
        $droppedItems = RegistryItem::where('status', 'dropped')
            ->whereHas('registry', fn ($q) => $q->where('type', 'trip')->where('created_by', $user->id))
            ->with(['tripRequest.user', 'dropper', 'registry'])
            ->latest('dropped_at')->get();

        return view('trips.registries.index', compact('registries', 'incoming', 'availableTrips', 'droppedItems'));
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
        $registry->load(['items.tripRequest.user.department', 'items.dropper', 'creator', 'approvalLogs.approver']);
        $canReturnItems = $this->approvalService->getPendingRegistriesForApprover(auth()->user(), 'trip')->contains('id', $registry->id);
        return view('trips.registries.show', compact('registry', 'canReturnItems'));
    }

    /** Частичный возврат: вывести одну заявку из реестра на доработку (ТЗ 18.1 п.12). */
    public function returnItem(Request $request, Registry $registry, RegistryItem $item)
    {
        abort_unless($item->registry_id === $registry->id, 404);
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);

        $incoming = $this->approvalService->getPendingRegistriesForApprover(auth()->user(), 'trip');
        abort_unless($incoming->contains('id', $registry->id), 403);

        $this->registryService->returnItem($registry, $item, auth()->user(), $data['comment']);

        return back()->with('success', 'Заявка выведена из реестра на доработку.');
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
        abort_unless(auth()->user()->isAccounting(), 403);
        $this->registryService->acceptByAccounting($registry, auth()->user());
        return back()->with('success', 'Реестр принят.');
    }
}
