<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        return view('chats.index', [
            'chats'         => $chats,
            'activeChat'    => null,
            'documentTypes' => DocumentType::orderBy('name')->get(['id', 'name']),
        ]);
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
            'chats'         => $chats,
            'activeChat'    => $chat,
            'firstUnreadId' => $firstUnreadId,
            'documentTypes' => DocumentType::orderBy('name')->get(['id', 'name']),
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
            'body'               => ['nullable', 'string', 'max:5000'],
            'file'               => ['nullable', 'file', 'max:51200'],
            'attach_to_document' => ['nullable', 'boolean'],
        ]);

        $body     = trim((string) $request->input('body', ''));
        $uploaded = $request->file('file');

        if ($body === '' && !$uploaded) {
            return response()->json(['message' => 'Сообщение не может быть пустым.'], 422);
        }

        $attachment = null;
        if ($uploaded) {
            $path = $uploaded->store("chats/{$chat->id}/attachments", 's3');
            if ($path === false) {
                return response()->json(['message' => 'Не удалось загрузить файл.'], 500);
            }

            $attachment = [
                'file_path' => $path,
                'file_name' => $uploaded->getClientOriginalName(),
                'file_size' => $uploaded->getSize(),
                'mime_type' => $uploaded->getMimeType(),
            ];

            // Optionally push a copy into the document's related files.
            if ($request->boolean('attach_to_document') && $chat->document_id) {
                $relPath = $uploaded->store("documents/{$chat->document_id}/related", 's3');
                if ($relPath !== false) {
                    Document::find($chat->document_id)?->relatedFiles()->create([
                        'uploaded_by' => auth()->id(),
                        'file_path'   => $relPath,
                        'file_name'   => $uploaded->getClientOriginalName(),
                        'file_size'   => $uploaded->getSize(),
                        'mime_type'   => $uploaded->getMimeType(),
                        'description' => 'Прикреплён из чата',
                    ]);
                }
            }
        }

        $message = $this->chatService->sendMessage($chat, auth()->user(), $body, $attachment);

        return response()->json($this->serializeMessage($message, auth()->id()), 201);
    }

    public function downloadAttachment(Chat $chat, ChatMessage $message)
    {
        $this->authorize('view', $chat);
        abort_if($message->chat_id !== $chat->id || !$message->file_path, 404);

        $disk = Storage::disk('s3');
        try {
            if (!$disk->exists($message->file_path)) {
                abort(404);
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            abort(404);
        }

        return $disk->download($message->file_path, $message->file_name);
    }

    public function previewAttachment(Chat $chat, ChatMessage $message)
    {
        $this->authorize('view', $chat);
        abort_if($message->chat_id !== $chat->id || !$message->file_path, 404);

        $disk = Storage::disk('s3');
        try {
            if (!$disk->exists($message->file_path)) {
                abort(404);
            }

            return response($disk->get($message->file_path), 200, [
                'Content-Type'        => $message->mime_type,
                'Content-Disposition' => 'inline; filename="' . rawurlencode($message->file_name) . '"',
                'Content-Length'      => $disk->size($message->file_path),
            ]);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            abort(404);
        }
    }

    public function markRead(Chat $chat)
    {
        $this->authorize('view', $chat);

        $this->chatService->markAsRead($chat, auth()->user());

        return response()->json(['ok' => true]);
    }

    public function toggleFavorite(Chat $chat)
    {
        $this->authorize('view', $chat);

        $changes    = $chat->favoritedBy()->toggle(auth()->id());
        $favorited  = ! empty($changes['attached']);

        return response()->json(['favorited' => $favorited]);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function chatList(User $user): Collection
    {
        $favoriteIds = DB::table('chat_favorites')->where('user_id', $user->id)->pluck('chat_id')->all();

        return Chat::whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->with([
                'document:id,title,status,initiator_id,document_type_id',
                'document.type:id,name',
                'document.initiator:id,name,department_id',
                'document.initiator.department:id,name',
                'messages' => fn($q) => $q->latest()->limit(1)->with('user:id,name'),
            ])
            ->latest()
            ->get()
            ->each(function ($c) use ($user, $favoriteIds) {
                $c->unread_count = $this->chatService->unreadCount($c, $user);
                $c->is_favorite  = in_array($c->id, $favoriteIds, true);
            });
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
            'attachment'   => $message->attachment,
        ];
    }
}

