<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = NotificationLog::where('user_id', auth()->id())
            ->latest()
            ->paginate(25);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(NotificationLog $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);
        $notification->markRead();
        return back();
    }

    public function markAllRead()
    {
        NotificationLog::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return back()->with('success', 'Все уведомления прочитаны.');
    }
}
