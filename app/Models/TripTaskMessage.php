<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Сообщение треда задания (вопрос инициатору / ответ). */
class TripTaskMessage extends Model
{
    protected $fillable = ['trip_task_id', 'user_id', 'body'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TripTask::class, 'trip_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
