<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Замещение: пока absent_user отсутствует (date_from..date_to), его входящие
 * согласования и задания получает deputy_user.
 */
class Substitution extends Model
{
    protected $fillable = [
        'absent_user_id', 'deputy_user_id', 'date_from', 'date_to',
        'trip_request_id', 'vacation_request_id',
    ];

    protected $casts = ['date_from' => 'date', 'date_to' => 'date'];

    public function absent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'absent_user_id');
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_user_id');
    }

    /** Действующие на дату (по умолчанию — сегодня). */
    public function scopeActiveOn(Builder $query, $date = null): Builder
    {
        $date = $date ?: now()->toDateString();

        return $query->whereDate('date_from', '<=', $date)
                     ->whereDate('date_to', '>=', $date);
    }

    /**
     * Назначить замещающего на период заявки. Ничего не делает, если замещающий не выбран
     * или совпадает с инициатором. $column — trip_request_id | vacation_request_id.
     */
    public static function assign(User $absent, ?int $deputyId, $from, $to, string $column, int $sourceId): void
    {
        if (! $deputyId || $deputyId === $absent->id) {
            return;
        }

        self::create([
            'absent_user_id' => $absent->id,
            'deputy_user_id' => $deputyId,
            'date_from'      => $from,
            'date_to'        => $to,
            $column          => $sourceId,
        ]);
    }

    /** Пользователи, которых $deputy сейчас замещает. */
    public static function coveredUsers(User $deputy): Collection
    {
        return self::activeOn()
            ->where('deputy_user_id', $deputy->id)
            ->with('absent')
            ->get()->pluck('absent')->filter()->unique('id')->values();
    }

    /** ID пользователей, которых $deputy сейчас замещает. */
    public static function coveredUserIds(User $deputy): array
    {
        return self::coveredUsers($deputy)->pluck('id')->all();
    }
}
