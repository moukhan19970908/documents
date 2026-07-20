<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistryItem extends Model
{
    protected $fillable = [
        'registry_id', 'trip_request_id', 'vacation_request_id',
        'status', 'dropped_by', 'drop_comment', 'dropped_at',
    ];

    protected $casts = ['dropped_at' => 'datetime'];

    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class);
    }

    public function tripRequest(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }

    public function vacationRequest(): BelongsTo
    {
        return $this->belongsTo(VacationRequest::class, 'vacation_request_id');
    }

    /** Кто вывел заявку из реестра. */
    public function dropper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dropped_by');
    }

    /** Заявка позиции — командировочная или отпускная. */
    public function request()
    {
        return $this->tripRequest ?? $this->vacationRequest;
    }

    public function isDropped(): bool
    {
        return $this->status === 'dropped';
    }
}
