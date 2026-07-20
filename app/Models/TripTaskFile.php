<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripTaskFile extends Model
{
    protected $fillable = ['trip_task_id', 'uploaded_by', 'original_name', 'path', 'size', 'mime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TripTask::class, 'trip_task_id');
    }
}
