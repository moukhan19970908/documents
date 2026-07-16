<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderApproval extends Model
{
    protected $fillable = ['order_id', 'approver_id', 'role_label', 'position', 'status', 'comment', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
