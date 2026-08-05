<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Личная заготовка маршрута инициатора для composed-сценариев: упорядоченный
 * набор фаз с участниками, который можно применить при запуске одним кликом.
 */
class RoutePreset extends Model
{
    protected $fillable = ['user_id', 'name', 'config'];

    protected $casts = [
        'config' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
