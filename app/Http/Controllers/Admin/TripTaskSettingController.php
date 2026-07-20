<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TripTask;
use App\Models\TripTaskSetting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class TripTaskSettingController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function edit()
    {
        return view('admin.trip-tasks.settings', [
            'settings' => TripTaskSetting::current(),
            'users'    => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'targets'  => TripTask::TARGETS,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hr_user_id'        => ['nullable', 'exists:users,id'],
            'office_manager_id' => ['nullable', 'exists:users,id'],
            'logistics_id'      => ['nullable', 'exists:users,id'],
            'transport_id'      => ['nullable', 'exists:users,id'],
        ]);

        $settings = TripTaskSetting::current();
        $settings->update($data);

        $this->auditService->log('trip_task_settings_updated', $settings);

        return redirect()->route('admin.trip-tasks.settings')->with('success', 'Исполнители заданий сохранены.');
    }
}
