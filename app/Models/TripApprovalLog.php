<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripApprovalLog extends Model
{
    protected $fillable = ['request_type', 'request_id', 'step_number', 'approver_id', 'action', 'comment'];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'approved'      => 'Согласовано',
            'rejected'      => 'Отклонено',
            'sent_revision' => 'На доработку',
            'submitted'     => 'Отправлено',
            'reassigned'    => 'Переназначено',
            default         => $this->action,
        };
    }
}
