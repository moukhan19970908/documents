<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
use App\Models\User;
use App\Models\DocumentType;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $director = User::where('role', 'director')->first();
        $admin    = User::where('role', 'admin')->first();

        $workflows = [
            [
                'name'        => 'Стандартное согласование',
                'description' => 'Руководитель отдела → Директор',
                'stages'      => [
                    ['name' => 'Руководитель отдела', 'sort_order' => 1, 'stage_type' => 'parallel', 'deadline_hours' => 72,  'approver' => $director],
                    ['name' => 'Директор',            'sort_order' => 2, 'stage_type' => 'sequential','deadline_hours' => 48,  'approver' => $admin],
                ],
            ],
            [
                'name'        => 'Упрощённое согласование',
                'description' => 'Только руководитель',
                'stages'      => [
                    ['name' => 'Руководитель', 'sort_order' => 1, 'stage_type' => 'parallel', 'deadline_hours' => 48, 'approver' => $director],
                ],
            ],
            [
                'name'        => 'Расширенное согласование договоров',
                'description' => 'Юрист → Руководитель → Директор',
                'stages'      => [
                    ['name' => 'Юридическая проверка',  'sort_order' => 1, 'stage_type' => 'sequential', 'deadline_hours' => 120, 'approver' => $director],
                    ['name' => 'Финансовое согласование','sort_order' => 2, 'stage_type' => 'parallel',   'deadline_hours' => 72,  'approver' => $director],
                    ['name' => 'Утверждение директором', 'sort_order' => 3, 'stage_type' => 'sequential', 'deadline_hours' => 48,  'approver' => $admin],
                ],
            ],
        ];

        foreach ($workflows as $wfData) {
            $stages = $wfData['stages'];
            unset($wfData['stages']);

            $wf = Workflow::firstOrCreate(
                ['name' => $wfData['name']],
                array_merge($wfData, ['created_by' => $admin?->id ?? 1])
            );

            foreach ($stages as $stageData) {
                $approver = $stageData['approver'];
                unset($stageData['approver']);

                $stage = WorkflowStage::firstOrCreate(
                    ['workflow_id' => $wf->id, 'sort_order' => $stageData['sort_order']],
                    array_merge($stageData, ['workflow_id' => $wf->id])
                );

                if ($approver) {
                    WorkflowStageApprover::firstOrCreate([
                        'workflow_stage_id' => $stage->id,
                        'approver_id'       => $approver->id,
                        'approver_type'     => 'user',
                    ]);
                }
            }
        }

        // Bind first workflow as default to client-contract type
        $contract = DocumentType::where('slug', 'client-contract')->first();
        if ($contract) {
            $std = Workflow::where('name', 'Стандартное согласование')->first();
            $contract->update(['default_workflow_id' => $std?->id]);
        }
    }
}
