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
        $user = auth()->user();

        return view('vacations.create', [
            'deputies'         => $user->deputyCandidates(),
            'deputyByApprover' => $user->isLinearEmployee(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vacation_type' => ['required', 'in:annual,unpaid,sick_leave,other'],
            'date_start'    => ['required', 'date'],
            'days_count'    => ['required', 'integer', 'min:1', 'max:365'],
            'comment'       => ['nullable', 'string'],
            'deputy_id'     => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Сотрудник задаёт начало и число календарных дней, окончание выводим сами.
        $data['date_end'] = \Carbon\Carbon::parse($data['date_start'])->addDays($data['days_count'] - 1)->toDateString();

        $user = auth()->user();

        $hasFlow = \App\Models\RequestFlow::where('request_type', 'vacation')->where('status', 'published')->exists();
        if (!$hasFlow && !$this->approvalService->findRoute($user, 'vacation')) {
            return back()->withInput()->with('error', 'Для вашего отдела не настроен маршрут согласования отпусков. Обратитесь к администратору.');
        }

        $deputyId = $data['deputy_id'] ?? null;
        unset($data['deputy_id']);

        $submit   = $request->boolean('submit');
        $vacation = $this->vacationService->create($user, $data, $submit);

        // Линейному замещающего назначает первый согласующий (VacationService::approve), не он сам.
        if (! $user->isLinearEmployee()) {
            \App\Models\Substitution::assign($user, $deputyId, $data['date_start'], $data['date_end'], 'vacation_request_id', $vacation->id);
        }

        $msg = $submit ? 'Заявка отправлена на согласование.' : 'Черновик сохранён.';
        return redirect()->route('vacations.show', $vacation)->with('success', $msg);
    }

    public function show(VacationRequest $vacation)
    {
        $this->authorize('view', $vacation);
        $vacation->load(['user.department', 'route.steps.approverUser', 'approvalLogs.approver', 'signatory', 'substitution.deputy', 'tasks.assignee', 'tasks.files', 'tasks.messages.user']);
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
            'days_count'    => ['required', 'integer', 'min:1', 'max:365'],
            'comment'       => ['nullable', 'string'],
        ]);

        $data['date_end'] = \Carbon\Carbon::parse($data['date_start'])->addDays($data['days_count'] - 1)->toDateString();
        $vacation->update($data);

        if ($request->boolean('submit')) {
            $this->vacationService->submit($vacation);
            return redirect()->route('vacations.show', $vacation)->with('success', 'Заявка отправлена на согласование.');
        }

        return redirect()->route('vacations.show', $vacation)->with('success', 'Черновик обновлён.');
    }
}
