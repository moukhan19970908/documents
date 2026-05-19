<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\TripRequest;
use App\Services\TripService;
use Illuminate\Http\Request;

class TripRequestController extends Controller
{
    public function __construct(private TripService $tripService) {}

    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = TripRequest::visibleBy($user)
            ->with(['user.department', 'route'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('city', 'like', '%' . $request->search . '%')
                  ->orWhere('purpose', 'like', '%' . $request->search . '%');
            });
        }

        $trips = $query->paginate(20)->withQueryString();
        return view('trips.index', compact('trips'));
    }

    public function create()
    {
        return view('trips.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'city'                => ['required', 'string', 'max:255'],
            'purpose'             => ['required', 'string'],
            'date_start'          => ['required', 'date'],
            'date_end'            => ['required', 'date', 'gte:date_start'],
            'daily_rate'          => ['required', 'numeric', 'min:0'],
            'accommodation_total' => ['required', 'numeric', 'min:0'],
            'transport_total'     => ['required', 'numeric', 'min:0'],
            'comment'             => ['nullable', 'string'],
        ]);

        $submit = $request->boolean('submit');
        $trip   = $this->tripService->create(auth()->user(), $data, $submit);

        $msg = $submit ? 'Заявка отправлена на согласование.' : 'Черновик сохранён.';
        return redirect()->route('trips.show', $trip)->with('success', $msg);
    }

    public function show(TripRequest $trip)
    {
        $this->authorize('view', $trip);
        $trip->load(['user.department', 'route.steps.approverUser', 'approvalLogs.approver']);
        return view('trips.show', compact('trip'));
    }

    public function update(Request $request, TripRequest $trip)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'city'                => ['required', 'string', 'max:255'],
            'purpose'             => ['required', 'string'],
            'date_start'          => ['required', 'date'],
            'date_end'            => ['required', 'date', 'gte:date_start'],
            'daily_rate'          => ['required', 'numeric', 'min:0'],
            'accommodation_total' => ['required', 'numeric', 'min:0'],
            'transport_total'     => ['required', 'numeric', 'min:0'],
            'comment'             => ['nullable', 'string'],
        ]);

        $days          = \Carbon\Carbon::parse($data['date_start'])->diffInDays($data['date_end']) + 1;
        $data['total_amount'] = ($data['daily_rate'] * $days) + $data['accommodation_total'] + $data['transport_total'];

        $trip->update($data);

        if ($request->boolean('submit')) {
            $this->tripService->submit($trip);
            return redirect()->route('trips.show', $trip)->with('success', 'Заявка отправлена на согласование.');
        }

        return redirect()->route('trips.show', $trip)->with('success', 'Черновик обновлён.');
    }
}
