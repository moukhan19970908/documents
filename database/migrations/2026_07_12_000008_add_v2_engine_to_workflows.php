<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            // 1 — legacy scenarios: live stages, old editor, current behaviour untouched.
            // 2 — scenarios from the new builder: published into immutable version copies.
            $table->unsignedTinyInteger('engine_version')->default(1)->after('status');
            $table->boolean('is_version')->default(false)->after('engine_version');
            $table->foreignId('parent_workflow_id')->nullable()->after('is_version')
                ->constrained('workflows')->cascadeOnDelete();
            $table->string('version_label')->nullable()->after('parent_workflow_id');
            $table->timestamp('published_at')->nullable()->after('version_label');
        });

        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->string('phase')->nullable()->after('name');                       // approval | approve | ack | intake
            $table->enum('resolver', ['user', 'group'])->default('user')->after('phase');
            $table->foreignId('group_department_id')->nullable()->after('resolver')
                ->constrained('departments')->nullOnDelete();
            $table->string('group_role')->nullable()->after('group_department_id');
            $table->enum('policy', ['any', 'all'])->default('all')->after('group_role'); // любой из группы | все
            $table->unsignedSmallInteger('sla_days')->nullable()->after('deadline_hours');
            $table->boolean('is_blocking')->default(true)->after('sla_days');
            // 'reject' keeps the current v1 behaviour; the builder defaults new stages to 'return_initiator'.
            $table->enum('on_reject', ['reject', 'return_initiator'])->default('reject')->after('is_blocking');

            // Simple mode by design: one stage — one condition on one parameter.
            $table->string('condition_key', 64)->nullable()->after('on_reject');
            $table->enum('condition_operator', ['=', '!=', 'in', '>', '<'])->nullable()->after('condition_key');
            $table->string('condition_value')->nullable()->after('condition_operator');
        });

        Schema::table('document_approvals', function (Blueprint $table) {
            // Answers are frozen with the route: the audit must match what was actually asked.
            $table->json('parameter_values')->nullable()->after('workflow_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_approvals', function (Blueprint $table) {
            $table->dropColumn('parameter_values');
        });

        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropForeign(['group_department_id']);
            $table->dropColumn([
                'phase', 'resolver', 'group_department_id', 'group_role', 'policy',
                'sla_days', 'is_blocking', 'on_reject',
                'condition_key', 'condition_operator', 'condition_value',
            ]);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['parent_workflow_id']);
            $table->dropColumn(['engine_version', 'is_version', 'parent_workflow_id', 'version_label', 'published_at']);
        });
    }
};
