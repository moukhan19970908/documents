<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Звено маршрута нельзя удалять физически, если по нему уже шли согласования:
     * на него ссылается история (document_approval_stages, FK без каскада).
     * Мягкое удаление убирает звено из маршрута, сохраняя историю.
     */
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
