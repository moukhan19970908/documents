<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Гранты новых прав «Поручения» по дефолтам каталога (config/permissions.php). */
    private const KEYS = ['assignments.issue', 'assignments.view_all'];

    public function up(): void
    {
        $items = collect(Permissions::items())->keyBy('key');
        $roles = DB::table('roles')->get(['id', 'code']);

        foreach (self::KEYS as $key) {
            $item = $items[$key] ?? null;
            if (! $item) {
                continue;
            }

            foreach ($roles as $role) {
                if (Permissions::defaultAllows($item, $role->code)) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role_id' => $role->id, 'permission' => $key],
                        [],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', self::KEYS)->delete();
    }
};
