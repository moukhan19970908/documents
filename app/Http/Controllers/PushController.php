<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'max:500'],
            'keys.p256dh'      => ['required', 'string'],
            'keys.auth'        => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id'          => auth()->id(),
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => ['required', 'string']]);

        PushSubscription::where('endpoint', $request->input('endpoint'))
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json(['ok' => true]);
    }
}
