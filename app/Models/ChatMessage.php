<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $fillable = ['chat_id', 'user_id', 'body', 'file_path', 'file_name', 'file_size', 'mime_type'];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ChatMessageRead::class);
    }

    /** Normalised attachment payload for the client, or null when there is none. */
    public function getAttachmentAttribute(): ?array
    {
        if (!$this->file_path) {
            return null;
        }

        return [
            'name'         => $this->file_name,
            'size'         => $this->formattedSize(),
            'mime'         => $this->mime_type,
            'is_image'     => str_starts_with((string) $this->mime_type, 'image/'),
            'download_url' => route('chats.attachment.download', ['chat' => $this->chat_id, 'message' => $this->id]),
            'preview_url'  => route('chats.attachment.preview', ['chat' => $this->chat_id, 'message' => $this->id]),
        ];
    }

    private function formattedSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' МБ';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' КБ';
        }
        return $bytes . ' Б';
    }
}
