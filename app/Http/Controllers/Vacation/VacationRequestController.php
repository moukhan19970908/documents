<?php

namespace App\Http\Controllers\Vacation;

use App\Http\Controllers\Controller;
use App\Models\VacationRequest;
use App\Services\VacationService;
use Illuminate\Http\Request;

class VacationRequestController extends Controller
{
    public function __construct(private VacationService $vacationService) {}

    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = VacationRequest::visibleBy($user)
            ->with(['user.department', 'route'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $vacations = $query->paginate(20)->withQueryString();
        return view('vacations.index', compact('vacations'));
    }

    public function create()
    {
        return view('vacations.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vacation_type' => ['required', 'in:annual,unpaid,sick_leave,other'],
            'date_start'    => ['required', 'date'],
            'date_end'      => ['required', 'date', 'gte:date_start'],
            'comment'       => ['nullable', 'string'],
        ]);

        $submit   = $request->boolean('submit');
        $vacation = $this->vacationService->create(auth()->user(), $data, $submit);

        $msg = $submit ? 'Заявка отправлена на согласование.' : 'Черновик сохранён.';
        return redirect()->route('vacations.show', $vacation)->with('success', $msg);
    }

    public function show(VacationRequest $vacation)
    {
        $this->authorize('view', $vacation);
        $vacation->load(['user.department', 'route.steps.approverUser', 'approvalLogs.approver']);
        return view('vacations.show', compact('vacation'));
    }

    public function update(Request $request, VacationRequest $vacation)
    {
        $this->authorize('update', $vacation);

        $data = $request->validate([
            'vacation_type' => ['required', 'in:annual,unpaid,sick_leave,other'],
            'date_start'    => ['required', 'date'],
            'date_end'      => ['required', 'date', 'gte:date_start'],
            'comment'       => ['nullable', 'string'],
        ]);

        $data['days_count'] = \Carbon\Carbon::parse($data['date_start'])->diffInDays($data['date_end']) + 1;
        $vacation->update($data);

        if ($request->boolean('submit')) {
            $this->vacationService->submit($vacation);
            return redirect()->route('vacations.show', $vacation)->with('success', 'Заявка отправлена на согласование.');
        }

        return redirect()->route('vacations.show', $vacation)->with('success', 'Черновик обновлён.');
    }
}
