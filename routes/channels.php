<?php

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function (User $user, int $chatId) {
    return Chat::find($chatId)?->participants()->where('user_id', $user->id)->exists() ?? false;
});
