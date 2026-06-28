<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Models\TripRequest;
use App\Services\ApprovalService;
use App\Services\TripNormService;
use App\Services\TripService;
use Illuminate\Http\Request;

class TripRequestController extends Controller
{
    public function __construct(
        private TripService $tripService,
        private ApprovalService $approvalService,
        private TripNormService $normService,
    ) {}

    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = TripRequest::visibleBy($user)
            ->with(['user.department', 'route', 'signatory'])
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
        $norms = $this->normService->payloadFor(auth()->user());
        return view('trips.create', compact('norms'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'city_type'           => ['required', 'in:moscow,spb,sochi,other_rf,abroad'],
            'city_name'           => ['nullable', 'string', 'max:255', 'required_if:city_type,other_rf,abroad'],
            'purpose'             => ['required', 'string'],
            'date_start'          => ['required', 'date'],
            'date_end'            => ['required', 'date', 'gte:date_start'],
            'daily_rate'          => ['required', 'numeric', 'min:0'],
            'accommodation_total' => ['required', 'numeric', 'min:0'],
            'comment'             => ['nullable', 'string'],
        ]);

        $user = auth()->user();

        if (!$this->approvalService->findRoute($user, 'trip')) {
            return back()->withInput()->with('error', 'Для вашего отдела не настроен маршрут согласования командировок. Обратитесь к администратору.');
        }

        $data = $this->composeLocation($data);
        $data['transport_total'] = 0;

        $submit = $request->boolean('submit');
        $trip   = $this->tripService->create($user, $data, $submit);

        $msg = $submit ? 'Заявка отправлена на согласование.' : 'Черновик сохранён.';
        return redirect()->route('trips.show', $trip)->with('success', $msg);
    }

    public function show(TripRequest $trip)
    {
        $this->authorize('view', $trip);
        $trip->load(['user.department', 'route.steps.approverUser', 'approvalLogs.approver', 'signatory']);
        return view('trips.show', compact('trip'));
    }

    public function edit(TripRequest $trip)
    {
        $this->authorize('update', $trip);
        $norms = $this->normService->payloadFor($trip->user);
        return view('trips.edit', compact('trip', 'norms'));
    }

    public function destroy(TripRequest $trip)
    {
        $this->authorize('delete', $trip);
        $trip->delete();
        return redirect()->route('trips.index')->with('success', 'Заявка удалена.');
    }

    public function update(Request $request, TripRequest $trip)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'city_type'           => ['nullable', 'in:moscow,spb,sochi,other_rf,abroad'],
            'city_name'           => ['nullable', 'string', 'max:255', 'required_if:city_type,other_rf,abroad'],
            'purpose'             => ['required', 'string'],
            'date_start'          => ['required', 'date'],
            'date_end'            => ['required', 'date', 'gte:date_start'],
            'daily_rate'          => ['required', 'numeric', 'min:0'],
            'accommodation_total' => ['required', 'numeric', 'min:0'],
            'comment'             => ['nullable', 'string'],
        ]);

        // When a location is provided, recompose it; otherwise keep the trip's existing city.
        if (!empty($data['city_type'])) {
            $data = $this->composeLocation($data);
        } else {
            unset($data['city_type'], $data['city_name']);
        }

        $data['transport_total'] = 0;

        $days = \Carbon\Carbon::parse($data['date_start'])->diffInDays($data['date_end']) + 1;
        $data['total_amount'] = ($data['daily_rate'] * $days) + $data['accommodation_total'] + $data['transport_total'];

        $trip->update($data);

        if ($request->boolean('submit')) {
            $this->tripService->submit($trip);
            return redirect()->route('trips.show', $trip)->with('success', 'Заявка отправлена на согласование.');
        }

        return redirect()->route('trips.show', $trip)->with('success', 'Черновик обновлён.');
    }

    /**
     * Turn the form's city_type / city_name into the stored city string and location_type.
     */
    private function composeLocation(array $data): array
    {
        $fixed = [
            'moscow' => 'Москва',
            'spb'    => 'Санкт-Петербург',
            'sochi'  => 'Сочи',
        ];

        $type = $data['city_type'];
        $data['location_type'] = $type;
        $data['city'] = $fixed[$type] ?? trim($data['city_name'] ?? '');

        unset($data['city_type'], $data['city_name']);

        return $data;
    }
}
