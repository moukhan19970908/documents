<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackHandler;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class FeedbackSettingController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function edit()
    {
        return view('admin.feedback.settings', [
            'users'      => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'handlerIds' => FeedbackHandler::userIds(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'users'   => ['array'],
            'users.*' => ['integer', 'exists:users,id'],
        ]);

        FeedbackHandler::sync(array_values(array_unique(array_map('intval', $data['users'] ?? []))));

        $this->auditService->log('feedback_handlers_updated');

        return redirect()->route('admin.feedback.settings')->with('success', 'Обработчики обращений сохранены.');
    }
}
