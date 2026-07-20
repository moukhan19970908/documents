<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripTaskSetting extends Model
{
    protected $fillable = ['hr_user_id', 'office_manager_id', 'logistics_id', 'transport_id'];

    private static ?TripTaskSetting $cached = null;

    /** Единственная строка настроек (создаётся при первом обращении). */
    public static function current(): self
    {
        return self::$cached ??= self::first() ?? self::create([]);
    }
}
