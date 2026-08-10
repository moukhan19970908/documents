<?php

namespace App\Http\Controllers\Vacation;

use App\Http\Controllers\Controller;
use App\Models\VacationRequest;
use App\Services\ApprovalService;
use App\Services\VacationService;
use Illuminate\Http\Request;

class VacationRequestController extends Controller
{
    public function __construct(
        private VacationService $vacationService,
        private ApprovalService $approvalService,
    ) {}

    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = VacationRequest::where('user_id', $user->id)
            ->with(['user.department', 'route', 'signatory'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $vacations = $query->paginate(20)->withQueryString();
        return view('vacations.index', compact('vacations'));
    }

    public function create()
    {
        $deputies = \App\Models\User::where('is_active', true)
            ->where('id', '!=', auth()->id())
            ->when(auth()->user()->department_id, fn ($q, $d) => $q->where('department_id', $d))
            ->orderBy('name')->get(['id', 'name', 'position']);

        return view('vacations.create', compact('deputies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vacation_type' => ['required', 'in:annual,unpaid,sick_leave,other'],
            'date_start'    => ['required', 'date'],
            'date_end'      => ['required', 'date', 'gte:date_start'],
            'comment'       => ['nullable', 'string'],
            'deputy_id'     => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = auth()->user();

        $hasFlow = \App\Models\RequestFlow::where('request_type', 'vacation')->where('status', 'published')->exists();
        if (!$hasFlow && !$this->approvalService->findRoute($user, 'vacation')) {
            return back()->withInput()->with('error', 'Для вашего отдела не настроен маршрут согласования отпусков. Обратитесь к администратору.');
        }

        $deputyId = $data['deputy_id'] ?? null;
        unset($data['deputy_id']);

        $submit   = $request->boolean('submit');
        $vacation = $this->vacationService->create($user, $data, $submit);

        \App\Models\Substitution::assign($user, $deputyId, $request->date_start, $request->date_end, 'vacation_request_id', $vacation->id);

        $msg = $submit ? 'Заявка отправлена на согласование.' : 'Черновик сохранён.';
        return redirect()->route('vacations.show', $vacation)->with('success', $msg);
    }

    public function show(VacationRequest $vacation)
    {
        $this->authorize('view', $vacation);
        $vacation->load(['user.department', 'route.steps.approverUser', 'approvalLogs.approver', 'signatory', 'substitution.deputy']);
        return view('vacations.show', compact('vacation'));
    }

    public function destroy(VacationRequest $vacation)
    {
        $this->authorize('delete', $vacation);
        $vacation->delete();
        return redirect()->route('vacations.index')->with('success', 'Заявка удалена.');
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
