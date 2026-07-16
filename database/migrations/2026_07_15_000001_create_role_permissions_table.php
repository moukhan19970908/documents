<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 100);
            $table->unique(['role_id', 'permission']);
        });

        // Засеваем матрицу дефолтами из каталога для существующих ролей —
        // дальше БД становится источником правды.
        $roles = DB::table('roles')->get(['id', 'code']);
        $rows = [];

        foreach (Permissions::items() as $item) {
            foreach ($roles as $role) {
                if (Permissions::defaultAllows($item, $role->code)) {
                    $rows[] = ['role_id' => $role->id, 'permission' => $item['key']];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('role_permissions')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
