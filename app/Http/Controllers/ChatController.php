<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    public function messages(Request $request, Chat $chat)
    {
        $this->authorize('view', $chat);

        $messages = $chat->messages()
            ->with('user:id,name')
            ->cursorPaginate(30);

        return response()->json([
            'data'        => $messages->map(fn($m) => [
                'id'         => $m->id,
                'body'       => $m->body,
                'created_at' => $m->created_at->toISOString(),
                'user'       => ['id' => $m->user->id, 'name' => $m->user->name],
            ]),
            'next_cursor' => $messages->nextCursor()?->encode(),
        ]);
    }

    public function store(Request $request, Chat $chat)
    {
        $this->authorize('sendMessage', $chat);

        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->chatService->sendMessage($chat, auth()->user(), $request->input('body'));

        return response()->json([
            'id'         => $message->id,
            'body'       => $message->body,
            'created_at' => $message->created_at->toISOString(),
            'user'       => ['id' => auth()->id(), 'name' => auth()->user()->name],
        ], 201);
    }
}
