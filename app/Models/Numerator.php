<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Numerator extends Model
{
    protected $fillable = [
        'name', 'mask', 'scope', 'reset_period', 'padding',
        'start_value', 'assign_moment', 'allow_manual', 'manual_roles',
    ];

    protected $casts = [
        'scope'        => 'array',
        'manual_roles' => 'array',
        'allow_manual' => 'boolean',
    ];

    public function counters(): HasMany
    {
        return $this->hasMany(DocumentCounter::class);
    }

    public function allowsManualFor(User $user): bool
    {
        return $this->allow_manual
            && (empty($this->manual_roles) || $user->hasAnyRole($this->manual_roles));
    }
}
