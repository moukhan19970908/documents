<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureTemplate extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function stages(): HasMany
    {
        return $this->hasMany(ProcedureStage::class)->orderBy('position');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ProcedureChecklistItem::class)->orderBy('position');
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(Procedure::class);
    }
}
