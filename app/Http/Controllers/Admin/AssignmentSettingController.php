<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSetting;
use App\Models\BlankTemplate;
use App\Models\DocumentType;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignmentSettingController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function edit()
    {
        return view('admin.assignments.settings', [
            'settings'  => AssignmentSetting::current(),
            'types'     => DocumentType::orderBy('name')->get(['id', 'name', 'code']),
            'blanks'    => BlankTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'scopes'    => AssignmentSetting::SCOPES,
            'deadlines' => AssignmentSetting::DEADLINE_MODES,
        ]);
    }

    public function update(Request $request)
    {
        $settings = AssignmentSetting::current();

        $data = $request->validate([
            'manager_scope'        => ['required', Rule::in(array_keys(AssignmentSetting::SCOPES))],
            'sub_scope'            => ['required', Rule::in(array_keys(AssignmentSetting::SCOPES))],
            'allow_subassignments' => ['nullable', 'boolean'],
            'max_depth'            => ['required', 'integer', 'min:1', 'max:20'],
            'aggregate_up'         => ['nullable', 'boolean'],
            'coexecutors_enabled'  => ['nullable', 'boolean'],
            'controller_enabled'   => ['nullable', 'boolean'],
            'deadline_extension'   => ['required', Rule::in(array_keys(AssignmentSetting::DEADLINE_MODES))],
            'document_type_id'     => ['nullable', 'exists:document_types,id'],
            'blank_template_id'    => ['nullable', 'exists:blank_templates,id'],
        ]);

        $settings->update([
            'manager_scope'        => $data['manager_scope'],
            'sub_scope'            => $data['sub_scope'],
            'allow_subassignments' => $request->boolean('allow_subassignments'),
            'max_depth'            => $data['max_depth'],
            'aggregate_up'         => $request->boolean('aggregate_up'),
            'coexecutors_enabled'  => $request->boolean('coexecutors_enabled'),
            'controller_enabled'   => $request->boolean('controller_enabled'),
            'deadline_extension'   => $data['deadline_extension'],
            'document_type_id'     => $data['document_type_id'] ?? null,
            'blank_template_id'    => $data['blank_template_id'] ?? null,
        ]);

        $this->auditService->log('assignment_settings_updated', $settings);

        return redirect()->route('admin.assignments.settings')->with('success', 'Правила поручений сохранены.');
    }
}
