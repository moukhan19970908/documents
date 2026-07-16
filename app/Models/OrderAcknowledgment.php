<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAcknowledgment extends Model
{
    protected $fillable = ['order_id', 'user_id', 'acknowledged_at', 'reminded_at'];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'reminded_at'     => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
