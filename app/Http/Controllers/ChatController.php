<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    public function index()
    {
        $chats = Chat::whereHas('participants', fn($q) => $q->where('user_id', auth()->id()))
            ->with(['document:id,title', 'messages' => fn($q) => $q->latest()->limit(1)->with('user:id,name')])
            ->latest()
            ->get();

        return view('chats.index', compact('chats'));
    }

    public function show(Chat $chat)
    {
        $this->authorize('view', $chat);

        $chat->load([
            'document:id,title',
            'messages' => fn($q) => $q->latest()->limit(30)->with('user:id,name'),
        ]);

        return view('chats.show', compact('chat'));
    }

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
