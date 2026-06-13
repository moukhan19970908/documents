<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    public function index()
    {
        $user  = auth()->user();
        $chats = $this->chatList($user);

        $first = $chats->first();
        if ($first) {
            return redirect()->route('chats.show', $first);
        }

        return view('chats.index', ['chats' => $chats, 'activeChat' => null]);
    }

    public function show(Chat $chat)
    {
        $this->authorize('view', $chat);

        $user = auth()->user();

        // Find the first unread message ID before marking as read
        $firstUnreadId = $chat->messages()
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))
            ->oldest()
            ->value('id');

        // Mark all messages as read
        $this->chatService->markAsRead($chat, $user);

        $chats = $this->chatList($user);

        $chat->load([
            'document:id,title,status,initiator_id',
            'document.initiator:id,name',
            'document.type:id,name',
            'participants:id,name',
            'messages' => fn($q) => $q->oldest()->with(['user:id,name', 'reads:id,chat_message_id,user_id']),
        ]);

        return view('chats.index', [
            'chats'        => $chats,
            'activeChat'   => $chat,
            'firstUnreadId' => $firstUnreadId,
        ]);
    }

    public function messages(Request $request, Chat $chat)
    {
        $this->authorize('view', $chat);

        $userId   = auth()->id();
        $messages = $chat->messages()
            ->oldest()
            ->with(['user:id,name', 'reads:id,chat_message_id,user_id'])
            ->cursorPaginate(50);

        return response()->json([
            'data'        => $messages->map(fn($m) => $this->serializeMessage($m, $userId)),
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

        return response()->json($this->serializeMessage($message, auth()->id()), 201);
    }

    public function markRead(Chat $chat)
    {
        $this->authorize('view', $chat);

        $this->chatService->markAsRead($chat, auth()->user());

        return response()->json(['ok' => true]);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function chatList(User $user): Collection
    {
        return Chat::whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->with([
                'document:id,title,status',
                'messages' => fn($q) => $q->latest()->limit(1)->with('user:id,name'),
            ])
            ->latest()
            ->get()
            ->each(fn($c) => $c->unread_count = $this->chatService->unreadCount($c, $user));
    }

    private function serializeMessage(\App\Models\ChatMessage $message, int $currentUserId): array
    {
        $readByCount = $message->reads
            ->where('user_id', '!=', $message->user_id)
            ->count();

        return [
            'id'           => $message->id,
            'body'         => $message->body,
            'created_at'   => $message->created_at->toISOString(),
            'user'         => ['id' => $message->user->id, 'name' => $message->user->name],
            'read_by_count' => $readByCount,
        ];
    }
}

