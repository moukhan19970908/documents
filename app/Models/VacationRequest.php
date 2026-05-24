<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class VacationRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'signatory_id', 'route_id', 'current_step', 'status',
        'vacation_type', 'date_start', 'date_end', 'days_count', 'comment',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end'   => 'date',
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
            ->where('request_type', 'vacation')
            ->orderBy('created_at', 'desc');
    }

    public function getVacationTypeLabelAttribute(): string
    {
        return match($this->vacation_type) {
            'annual'     => 'Ежегодный',
            'unpaid'     => 'За свой счёт',
            'sick_leave' => 'Больничный',
            'other'      => 'Иное',
            default      => $this->vacation_type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'    => 'Черновик',
            'pending'  => 'На согласовании',
            'approved' => 'Согласовано',
            'rejected' => 'Отклонено',
            'revision' => 'На доработке',
            default    => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft'    => 'bg-gray-100 text-gray-600',
            'pending'  => 'bg-yellow-100 text-yellow-700',
            'approved' => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700',
            'revision' => 'bg-orange-100 text-orange-700',
            default    => 'bg-gray-100 text-gray-600',
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
