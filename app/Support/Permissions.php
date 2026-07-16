<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Мост между каталогом прав (config/permissions.php) и сохранёнными
 * грантами ролей (таблица role_permissions).
 *
 * Каталог задаёт структуру матрицы и дефолты (only/except), а фактическую
 * выдачу прав ролям хранит БД: наличие строки role_permissions = право есть.
 */
class Permissions
{
    /** @var array<string, array<int, string>>|null  key => [role_code, ...] */
    private static ?array $granted = null;

    /**
     * Плоский список пунктов каталога из config (без разбивки на группы).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function items(): array
    {
        return collect(config('permissions'))
            ->flatMap(fn (array $group) => $group['items'])
            ->all();
    }

    /** @return array<int, string> */
    public static function allKeys(): array
    {
        return array_map(fn (array $item) => $item['key'], self::items());
    }

    /**
     * Дефолтная выдача пункта роли по правилам каталога:
     * «only» — белый список, «except» — чёрный (белый приоритетнее).
     */
    public static function defaultAllows(array $item, string $roleCode): bool
    {
        if (isset($item['only'])) {
            return in_array($roleCode, $item['only'], true);
        }

        return ! in_array($roleCode, $item['except'] ?? [], true);
    }

    /**
     * Коды ролей, которым выдано право (из БД). Кэшируется на запрос.
     *
     * @return array<int, string>
     */
    public static function grantedRoleCodes(string $key): array
    {
        if (self::$granted === null) {
            self::load();
        }

        return self::$granted[$key] ?? [];
    }

    public static function clearCache(): void
    {
        self::$granted = null;
    }

    private static function load(): void
    {
        self::$granted = [];

        DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->select('role_permissions.permission', 'roles.code')
            ->get()
            ->each(function ($row) {
                self::$granted[$row->permission][] = $row->code;
            });
    }
}
