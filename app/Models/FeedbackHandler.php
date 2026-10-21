<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Обработчик обращений тех поддержки (назначается в админке). Назначение само по себе
 * даёт право видеть все обращения и отвечать — отдельного права в матрице не требует.
 */
class FeedbackHandler extends Model
{
    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return int[] */
    public static function userIds(): array
    {
        return self::query()->pluck('user_id')->map('intval')->all();
    }

    /** @param int[] $userIds */
    public static function sync(array $userIds): void
    {
        self::whereNotIn('user_id', $userIds ?: [0])->delete();

        foreach ($userIds as $userId) {
            self::firstOrCreate(['user_id' => $userId]);
        }
    }
}
