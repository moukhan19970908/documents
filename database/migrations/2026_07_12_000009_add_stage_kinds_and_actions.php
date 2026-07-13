<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ознакомление, приём (в два шага) и заключения — новые решения участника.
        DB::statement("ALTER TABLE `document_approval_decisions` MODIFY `action` ENUM(
            'approve','reject','delegate','request_changes','process_approve','process_reject',
            'acknowledge','accept','execute','opinion_yes','opinion_no'
        ) NOT NULL");

        // Приём живёт в двух состояниях: принято к исполнению → исполнено.
        DB::statement("ALTER TABLE `document_approval_stages` MODIFY `status` ENUM(
            'pending','in_progress','accepted','approved','rejected','delegated','requires_changes'
        ) NOT NULL DEFAULT 'pending'");

        DB::statement("ALTER TABLE `workflow_stages` MODIFY `condition_operator` ENUM(
            '=','!=','in','>','<','contains','not_contains'
        ) NULL");

        Schema::table('workflow_stages', function (Blueprint $table) {
            // Ветки развилки при публикации разворачиваются в обычные условные звенья одной группы:
            // в маршрут документа попадает первая подходящая.
            $table->string('branch_group')->nullable()->after('condition_value');
            // Параллельная группа: несколько отделов и/или конкретных участников сразу.
            $table->json('group_department_ids')->nullable()->after('group_department_id');
        });

        Schema::create('workflow_stage_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('condition_key', 64)->nullable();
            $table->enum('condition_operator', ['=', '!=', 'in', '>', '<', 'contains', 'not_contains'])->nullable();
            $table->string('condition_value')->nullable();
            $table->json('approver_ids')->nullable();
            $table->json('department_ids')->nullable();
            $table->enum('policy', ['any', 'all'])->default('all');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stage_branches');

        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn(['branch_group', 'group_department_ids']);
        });

        DB::statement("ALTER TABLE `workflow_stages` MODIFY `condition_operator` ENUM('=','!=','in','>','<') NULL");

        DB::statement("ALTER TABLE `document_approval_stages` MODIFY `status` ENUM(
            'pending','in_progress','approved','rejected','delegated','requires_changes'
        ) NOT NULL DEFAULT 'pending'");

        DB::statement("ALTER TABLE `document_approval_decisions` MODIFY `action` ENUM(
            'approve','reject','delegate','request_changes','process_approve','process_reject'
        ) NOT NULL");
    }
};
