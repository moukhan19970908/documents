<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['code', 'name', 'description', 'icon', 'color', 'level', 'is_system'];

    protected function casts(): array
    {
        return [
            'level'     => 'integer',
            'is_system' => 'boolean',
        ];
    }

    public const ICONS = [
        'user'      => 'Сотрудник',
        'users'     => 'Группа',
        'building'  => 'Департамент',
        'flag'      => 'Направление',
        'crown'     => 'Руководство',
        'briefcase' => 'Аппарат управления',
        'gear'      => 'Администрирование',
        'process'   => 'Процесс',
        'clipboard' => 'Регистрация',
        'eye'       => 'Наблюдение',
        'external'  => 'Внешний участник',
    ];

    public const COLORS = [
        'indigo'  => 'Фиолетовый',
        'blue'    => 'Синий',
        'emerald' => 'Зелёный',
        'amber'   => 'Жёлтый',
        'rose'    => 'Красный',
        'slate'   => 'Серый',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
