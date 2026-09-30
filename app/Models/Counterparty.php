<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Организация из справочника контрагентов; данные синхронизируются с Checko по ИНН. */
class Counterparty extends Model
{
    protected $fillable = [
        'inn', 'short_name', 'legal_address', 'director_name',
        'phones', 'emails', 'website', 'synced_at', 'created_by',
    ];

    protected $casts = [
        'phones'    => 'array',
        'emails'    => 'array',
        'synced_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
