<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Registry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'created_by', 'route_id', 'current_step',
        'status', 'title', 'total_amount', 'comment',
    ];

    protected $casts = ['total_amount' => 'decimal:2'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(ApprovalRoute::class, 'route_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RegistryItem::class);
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(TripApprovalLog::class, 'request_id')
            ->where('request_type', 'registry')
            ->orderBy('created_at', 'desc');
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'                  => 'Черновик',
            'pending'                => 'На согласовании',
            'approved'               => 'Согласовано',
            'rejected'               => 'Отклонено',
            'sent_to_accounting'     => 'Передан в бухгалтерию',
            'accepted_by_accounting' => 'Принят бухгалтерией',
            default                  => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft'                  => 'bg-gray-100 text-gray-600',
            'pending'                => 'bg-yellow-100 text-yellow-700',
            'approved'               => 'bg-green-100 text-green-700',
            'rejected'               => 'bg-red-100 text-red-700',
            'sent_to_accounting'     => 'bg-blue-100 text-blue-700',
            'accepted_by_accounting' => 'bg-emerald-100 text-emerald-700',
            default                  => 'bg-gray-100 text-gray-600',
        };
    }
}
