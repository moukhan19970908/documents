<?php

namespace App\Http\Controllers;

use App\Models\Registry;
use App\Models\TripRequest;
use App\Models\VacationRequest;
use App\Services\ApprovalService;
use Illuminate\Http\Request;

/**
 * Единая страница «Заявки» (ТЗ 18): хаб над модулями Отпуска / Командировки / Иное.
 * Ничего не дублирует — агрегирует существующие заявки и реестры в одном месте.
 */
class RequestsController extends Controller
{
    public function __construct(private ApprovalService $approvals) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Мои заявки обоих видов в общем формате.
        $trips = TripRequest::where('user_id', $user->id)->latest()->get()->map(fn ($r) => [
            'kind'   => 'trip',
            'title'  => 'Командировка' . ($r->city ? ' — ' . $r->city : ''),
            'dates'  => $r->date_start?->format('d.m.Y') . ' – ' . $r->date_end?->format('d.m.Y'),
            'status' => $r->status_label,
            'color'  => $r->status_color,
            'url'    => route('trips.show', $r),
            'at'     => $r->created_at,
        ]);

        $vacations = VacationRequest::where('user_id', $user->id)->latest()->get()->map(fn ($r) => [
            'kind'   => 'vacation',
            'title'  => 'Отпуск — ' . $r->vacation_type_label,
            'dates'  => $r->date_start?->format('d.m.Y') . ' – ' . $r->date_end?->format('d.m.Y'),
            'status' => $r->status_label,
            'color'  => $r->status_color,
            'url'    => route('vacations.show', $r),
            'at'     => $r->created_at,
        ]);

        $myRequests = $trips->concat($vacations)->sortByDesc('at')->values();

        // На согласование — считаем только для тех, кто может согласовывать.
        $canApprove = $user->isManager() || $user->isApprover('trip') || $user->isApprover('vacation');
        $pending = [
            'trip'          => $canApprove ? $this->approvals->getPendingForApprover($user, 'trip')->count() : 0,
            'vacation'      => $canApprove ? $this->approvals->getPendingForApprover($user, 'vacation')->count() : 0,
            'trip_reg'      => $user->isManager() ? $this->approvals->getPendingRegistriesForApprover($user, 'trip')->count() : 0,
            'vacation_reg'  => $user->isManager() ? $this->approvals->getPendingRegistriesForApprover($user, 'vacation')->count() : 0,
        ];

        $myRegistries = Registry::where('created_by', $user->id)->latest()->take(6)->get();

        return view('requests.index', [
            'myRequests'   => $myRequests,
            'pending'      => $pending,
            'myRegistries' => $myRegistries,
            'counts'       => [
                'active' => $myRequests->whereIn('status', ['На согласовании', 'В реестре'])->count(),
                'total'  => $myRequests->count(),
            ],
        ]);
    }
}
