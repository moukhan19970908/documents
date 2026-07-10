<?php

use App\Models\Chat;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function (User $user, int $chatId) {
    return Chat::find($chatId)?->participants()->where('user_id', $user->id)->exists() ?? false;
});

Broadcast::channel('document.{documentId}', function (User $user, int $documentId) {
    $document = Document::find($documentId);
    return $document ? $user->can('view', $document) : false;
});

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
