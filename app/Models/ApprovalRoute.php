<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRoute extends Model
{
    protected $fillable = ['name', 'request_type', 'department_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRouteStep::class, 'route_id')->orderBy('step_order');
    }

    public function tripRequests(): HasMany
    {
        return $this->hasMany(TripRequest::class, 'route_id');
    }

    public function vacationRequests(): HasMany
    {
        return $this->hasMany(VacationRequest::class, 'route_id');
    }
}
