<?php

use App\Models\WorkflowStageApprover;
use App\Models\WorkflowStageBranch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            // Звено внутри ветки развилки: цепочку ветки образуют обычные звенья с этим branch_id.
            // У звеньев верхнего уровня — null. Порядок в цепочке — существующий sort_order.
            $table->foreignId('branch_id')->nullable()->after('branch_group')
                ->constrained('workflow_stage_branches')->nullOnDelete();
            // Заполняется только при публикации: дискриминатор ветки внутри развилки
            // ('branch-<razvilkaId>-<ordinal>'). Движок по нему пускает в маршрут все звенья
            // первой подходящей ветки, отсекая звенья прочих веток той же развилки.
            $table->string('branch_key')->nullable()->after('branch_group');
        });

        // Backfill: раньше ветка = одно звено (условие + согласующие). Разворачиваем каждую
        // существующую ветку в звено-цепочку из одного звена, чтобы уже собранные развилки
        // не остались без работы под новой моделью.
        WorkflowStageBranch::with('stage')->get()->each(function (WorkflowStageBranch $branch) {
            if (!$branch->stage) {
                return;
            }

            $stage = $branch->stage->newQuery()->create([
                'workflow_id'          => $branch->stage->workflow_id,
                'branch_id'            => $branch->id,
                'name'                 => $branch->name ?: 'Ветка',
                'phase'                => 'approval',
                'stage_type'           => ($branch->policy ?? 'all') === 'any' ? 'sequential' : 'parallel',
                'resolver'             => 'user',
                'group_department_ids' => $branch->department_ids ?: null,
                'policy'               => $branch->policy ?? 'all',
                'sla_days'             => 2,
                'is_blocking'          => true,
                'on_reject'            => 'return_initiator',
                'sort_order'           => 0,
            ]);

            foreach ($branch->approver_ids ?? [] as $userId) {
                WorkflowStageApprover::create([
                    'workflow_stage_id' => $stage->id,
                    'approver_type'     => 'user',
                    'approver_id'       => $userId,
                    'is_required'       => true,
                    'participant_type'  => 'signatory',
                ]);
            }
        });
    }

    public function down(): void
    {
        // Снести звенья-цепочки (и их согласующих) прежде, чем убирать колонку.
        WorkflowStageApprover::whereIn('workflow_stage_id', function ($q) {
            $q->select('id')->from('workflow_stages')->whereNotNull('branch_id');
        })->delete();

        \App\Models\WorkflowStage::whereNotNull('branch_id')->forceDelete();

        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('branch_key');
        });
    }
};
