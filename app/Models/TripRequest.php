<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class TripRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'signatory_id', 'route_id', 'current_step', 'status',
        'city', 'purpose', 'date_start', 'date_end',
        'daily_rate', 'accommodation_total', 'transport_total', 'total_amount', 'comment',
    ];

    protected $casts = [
        'date_start'           => 'date',
        'date_end'             => 'date',
        'daily_rate'           => 'decimal:2',
        'accommodation_total'  => 'decimal:2',
        'transport_total'      => 'decimal:2',
        'total_amount'         => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(ApprovalRoute::class, 'route_id');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(TripApprovalLog::class, 'request_id')
            ->where('request_type', 'trip')
            ->orderBy('created_at', 'desc');
    }

    public function registryItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RegistryItem::class, 'trip_request_id');
    }

    public function getDaysCountAttribute(): int
    {
        return $this->date_start->diffInDays($this->date_end) + 1;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'       => 'Черновик',
            'pending'     => 'На согласовании',
            'approved'    => 'Согласовано',
            'rejected'    => 'Отклонено',
            'revision'    => 'На доработке',
            'in_registry' => 'В реестре',
            default       => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft'       => 'bg-gray-100 text-gray-600',
            'pending'     => 'bg-yellow-100 text-yellow-700',
            'approved'    => 'bg-green-100 text-green-700',
            'rejected'    => 'bg-red-100 text-red-700',
            'revision'    => 'bg-orange-100 text-orange-700',
            'in_registry' => 'bg-blue-100 text-blue-700',
            default       => 'bg-gray-100 text-gray-600',
        };
    }

    public function scopeVisibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->role === 'director' || ($user->role_level ?? 1) >= 5) {
            return $query;
        }

        $level = $user->role_level ?? 1;

        if ($level >= 3) {
            $ids = array_merge([$user->id], $user->allSubordinateIds());
            return $query->whereIn('user_id', $ids);
        }

        if ($level === 2) {
            $ids = array_merge([$user->id], $user->directSubordinateIds());
            return $query->whereIn('user_id', $ids);
        }

        return $query->where('user_id', $user->id);
    }
}
