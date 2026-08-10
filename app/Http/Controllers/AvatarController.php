<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    /**
     * Отдаёт аватар пользователя через приложение (по его же HTTPS), чтобы MinIO/S3
     * не светился в браузер напрямую. Сервер читает файл из S3 по внутреннему HTTP,
     * кэширует на локальном диске и отдаёт с браузерным кэшем. При отсутствии аватара
     * или сбое хранилища — редирект на генератор-заглушку (ui-avatars).
     */
    public function show(User $user)
    {
        $fallback = 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=5B4FE8&color=fff';

        if (! $user->avatar) {
            return redirect($fallback);
        }

        $ext  = strtolower(pathinfo($user->avatar, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        // Локальный файловый кэш: содержимое по пути неизменно (Bitrix24Service не
        // перекачивает уже сохранённые аватары), поэтому ключ = путь-в-S3.
        $cacheKey = 'avatar-cache/' . $user->id . '-' . substr(md5($user->avatar), 0, 10) . '.' . ($ext ?: 'jpg');

        try {
            if (! Storage::disk('local')->exists($cacheKey)) {
                Storage::disk('local')->put($cacheKey, Storage::disk('s3')->get($user->avatar));
            }
            $body = Storage::disk('local')->get($cacheKey);
        } catch (\Throwable $e) {
            return redirect($fallback);
        }

        return response($body, 200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'private, max-age=86400');
    }
}
