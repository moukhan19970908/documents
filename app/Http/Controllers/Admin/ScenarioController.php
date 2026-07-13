<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlankTemplate;
use App\Models\Department;
use App\Models\DocumentApproval;
use App\Models\DocumentType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowParameter;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageApprover;
use App\Services\AuditService;
use App\Services\ScenarioPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScenarioController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index()
    {
        $scenarios = Workflow::with(['owner', 'parameters', 'subtypes.type', 'versions'])
            ->withCount('stages')
            ->where('is_system', false)
            ->where('is_version', false)   // версии-копии — это история публикаций, а не отдельные сценарии
            ->orderByDesc('id')
            ->get();

        return view('admin.scenarios.index', compact('scenarios'));
    }

    public function create()
    {
        return view('admin.scenarios.wizard', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validateScenario($request);

        $scenario = DB::transaction(function () use ($request, $validated) {
            $scenario = Workflow::create($this->scenarioAttributes($validated) + [
                'created_by'     => auth()->id(),
                'is_system'      => false,
                'is_active'      => false,   // a draft is not offered for launch yet
                'status'         => 'draft',
                'engine_version' => 2,       // everything from the new builder runs on v2
            ]);

            $this->syncSubtypes($scenario, $validated['subtypes'] ?? []);
            $this->syncParameters($scenario, $request->input('parameters', []));
            $scenario->blankTemplates()->sync($validated['blank_template_ids'] ?? []);

            return $scenario;
        });

        $this->auditService->log('scenario_created', $scenario);

        return redirect()->route('admin.scenarios.edit', ['scenario' => $scenario, 'step' => $this->stepFrom($request)])
            ->with('success', 'Черновик сценария сохранён.');
    }

    public function edit(Workflow $scenario)
    {
        $scenario->load(['parameters', 'subtypes.type', 'owner', 'stages.approvers', 'stages.branches', 'blankTemplates']);

        return view('admin.scenarios.wizard', $this->formData($scenario));
    }

    public function update(Request $request, Workflow $scenario)
    {
        $validated = $this->validateScenario($request, $scenario);

        DB::transaction(function () use ($request, $validated, $scenario) {
            $scenario->update($this->scenarioAttributes($validated));

            $this->syncSubtypes($scenario, $validated['subtypes'] ?? []);
            $this->syncParameters($scenario, $request->input('parameters', []));
            $scenario->blankTemplates()->sync($validated['blank_template_ids'] ?? []);
        });

        $this->auditService->log('scenario_updated', $scenario);

        return redirect()->route('admin.scenarios.edit', ['scenario' => $scenario, 'step' => $this->stepFrom($request)])
            ->with('success', 'Сценарий сохранён.');
    }

    /** The wizard tells us which step to land on after the save. */
    private function stepFrom(Request $request): string
    {
        $step = $request->input('step');

        return in_array($step, ['basic', 'classifier', 'parameters', 'route'], true) ? $step : 'basic';
    }

    /** Step 4 — the route. Stages are rewritten wholesale: the template is not what running processes use. */
    public function updateRoute(Request $request, Workflow $scenario)
    {
        $operators = array_keys(WorkflowStage::OPERATORS);

        $validated = $request->validate([
            'stages'                          => ['nullable', 'array'],
            'stages.*.name'                   => ['required', 'string', 'max:255'],
            'stages.*.phase'                  => ['required', Rule::in(array_keys(WorkflowStage::PHASES))],
            'stages.*.resolver'               => ['required', Rule::in(['user', 'group'])],
            'stages.*.approver_ids'           => ['nullable', 'array'],
            'stages.*.approver_ids.*'         => ['integer', 'exists:users,id'],
            'stages.*.group_department_ids'   => ['nullable', 'array'],
            'stages.*.group_department_ids.*' => ['integer', 'exists:departments,id'],
            'stages.*.group_role'             => ['nullable', 'string'],
            'stages.*.policy'                 => ['required', Rule::in(['any', 'all'])],
            'stages.*.is_blocking'            => ['nullable', 'boolean'],
            'stages.*.sla_days'               => ['nullable', 'integer', 'min:1', 'max:365'],
            'stages.*.on_reject'              => ['required', Rule::in(array_keys(WorkflowStage::ON_REJECT))],
            'stages.*.condition_key'          => ['nullable', 'string', 'max:64'],
            'stages.*.condition_operator'     => ['nullable', Rule::in($operators)],
            'stages.*.condition_value'        => ['nullable', 'string', 'max:255'],

            // Ветки развилки: у каждой своё условие и свой состав согласующих.
            'stages.*.branches'                        => ['nullable', 'array'],
            'stages.*.branches.*.name'                 => ['nullable', 'string', 'max:255'],
            'stages.*.branches.*.condition_key'        => ['nullable', 'string', 'max:64'],
            'stages.*.branches.*.condition_operator'   => ['nullable', Rule::in($operators)],
            'stages.*.branches.*.condition_value'      => ['nullable', 'string', 'max:255'],
            'stages.*.branches.*.approver_ids'         => ['nullable', 'array'],
            'stages.*.branches.*.approver_ids.*'       => ['integer', 'exists:users,id'],
            'stages.*.branches.*.department_ids'       => ['nullable', 'array'],
            'stages.*.branches.*.department_ids.*'     => ['integer', 'exists:departments,id'],
            'stages.*.branches.*.policy'               => ['nullable', Rule::in(['any', 'all'])],
        ]);

        DB::transaction(function () use ($scenario, $validated, $request) {
            foreach ($scenario->stages as $stage) {
                $stage->approvers()->delete();
                $stage->delete();
            }

            foreach (array_values($validated['stages'] ?? []) as $i => $data) {
                $data += [
                    'group_department_ids' => [],
                    'group_role'           => null,
                    'sla_days'             => null,
                    'condition_key'        => null,
                    'condition_operator'   => null,
                    'condition_value'      => null,
                    'branches'             => [],
                ];

                $stage = WorkflowStage::create([
                    'workflow_id'          => $scenario->id,
                    'name'                 => $data['name'],
                    'phase'                => $data['phase'],
                    'stage_type'           => $data['policy'] === 'any' ? 'sequential' : 'parallel',
                    'resolver'             => $data['resolver'],
                    'group_department_ids' => $data['group_department_ids'] ?: null,
                    'group_role'           => $data['resolver'] === 'group' ? ($data['group_role'] ?: null) : null,
                    'policy'               => $data['policy'],
                    'sla_days'             => $data['sla_days'] ?? null,
                    // Не держать маршрут может только звено, чьё решение ни на что не влияет:
                    // ознакомление и заключения. Согласование, утверждение и приём держат его всегда —
                    // иначе документ проскакивает маршрут и согласуется сам.
                    'is_blocking'          => in_array($data['phase'], ['ack', 'opinion'], true)
                        ? (bool) ($data['is_blocking'] ?? true)
                        : true,
                    'on_reject'            => $data['on_reject'],
                    'condition_key'        => $data['condition_key'] ?: null,
                    'condition_operator'   => $data['condition_key'] ? ($data['condition_operator'] ?: '=') : null,
                    'condition_value'      => $data['condition_key'] ? ($data['condition_value'] ?: null) : null,
                    'sort_order'           => $i,
                ]);

                foreach ($data['approver_ids'] ?? [] as $userId) {
                    WorkflowStageApprover::create([
                        'workflow_stage_id' => $stage->id,
                        'approver_type'     => 'user',
                        'approver_id'       => $userId,
                        'is_required'       => true,
                        'participant_type'  => 'signatory',
                    ]);
                }

                foreach (array_values($data['branches']) as $j => $branch) {
                    $stage->branches()->create([
                        'name'               => $branch['name'] ?? null,
                        'condition_key'      => $branch['condition_key'] ?: null,
                        'condition_operator' => $branch['condition_key'] ? ($branch['condition_operator'] ?: '=') : null,
                        'condition_value'    => $branch['condition_key'] ? ($branch['condition_value'] ?: null) : null,
                        'approver_ids'       => $branch['approver_ids'] ?? [],
                        'department_ids'     => $branch['department_ids'] ?? [],
                        'policy'             => $branch['policy'] ?? 'all',
                        'sort_order'         => $j,
                    ]);
                }
            }

            // Editing a published scenario produces a new draft state until it is published again.
            $scenario->update(['engine_version' => 2]);
        });

        $this->auditService->log('scenario_route_updated', $scenario);

        return redirect()->route('admin.scenarios.edit', ['scenario' => $scenario, 'step' => 'route'])
            ->with('success', 'Маршрут сохранён. Опубликуйте сценарий, чтобы он стал доступен для запуска.');
    }

    /** Publishing freezes an immutable version — running processes keep the one they started with. */
    public function publish(Workflow $scenario, ScenarioPublisher $publisher)
    {
        $version = $publisher->publish($scenario);

        return redirect()->route('admin.scenarios.edit', ['scenario' => $scenario, 'step' => 'route'])
            ->with('success', "Сценарий опубликован, версия {$version->version_label}. Идущие процессы продолжатся по своей версии.");
    }

    public function destroy(Workflow $scenario)
    {
        // Запущенные процессы ссылаются на версию, а не на шаблон — проверяем и то, и другое.
        $definitionIds = $scenario->versions()->pluck('id')->push($scenario->id);

        if (DocumentApproval::whereIn('workflow_id', $definitionIds)->exists()) {
            return back()->with('error', 'По сценарию уже шли согласования — его нельзя удалить, только снять с публикации.');
        }

        $this->auditService->log('scenario_deleted', $scenario);
        $scenario->delete();

        return redirect()->route('admin.scenarios.index')->with('success', 'Сценарий удалён.');
    }

    private function formData(?Workflow $scenario = null): array
    {
        return [
            'scenario'       => $scenario,
            'documentTypes'  => DocumentType::with(['subtypes', 'numerator'])->orderBy('name')->get(),
            'users'          => User::where('is_active', true)->where('role', '!=', 'external')->orderBy('name')->get(),
            'departments'    => Department::orderBy('name')->get(),
            'versions'       => $scenario?->versions()->get() ?? collect(),
            'blankTemplates' => BlankTemplate::with('subtype')->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validateScenario(Request $request, ?Workflow $scenario = null): array
    {
        return $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'process_type'          => ['required', Rule::in(array_keys(Workflow::PROCESS_TYPES))],
            'icon'                  => ['nullable', Rule::in(Workflow::ICONS)],
            'owner_id'              => ['nullable', 'exists:users,id'],

            'document_type_id'      => ['nullable', 'exists:document_types,id'],
            'subtypes'              => ['nullable', 'array'],
            'subtypes.*'            => ['integer', 'exists:document_subtypes,id'],

            'blank_template_ids'    => ['nullable', 'array'],
            'blank_template_ids.*'  => ['integer', 'exists:blank_templates,id'],
            'allow_file_upload'     => ['nullable', 'boolean'],

            'parameters'                => ['nullable', 'array'],
            'parameters.*.key'          => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/i'],
            'parameters.*.label'        => ['required', 'string', 'max:255'],
            'parameters.*.type'         => ['required', Rule::in(array_keys(WorkflowParameter::TYPES))],
            'parameters.*.options'      => ['nullable', 'array'],
            // Пустой вариант приходит как null (ConvertEmptyStringsToNull) — он просто отбрасывается при сохранении.
            'parameters.*.options.*'    => ['nullable', 'string', 'max:255'],
        ], [
            'parameters.*.key.regex' => 'Ключ параметра — латиница, цифры и подчёркивание.',
        ]);
    }

    private function scenarioAttributes(array $validated): array
    {
        return [
            'name'              => $validated['name'],
            'description'       => $validated['description'] ?? null,
            'process_type'      => $validated['process_type'],
            'icon'              => $validated['icon'] ?? 'document',
            'owner_id'          => $validated['owner_id'] ?? auth()->id(),
            'document_type_id'  => $validated['document_type_id'] ?? null,
            // Чекбокса нет, пока в сценарии нет бланков, — тогда файл разрешён по умолчанию.
            'allow_file_upload' => (bool) ($validated['allow_file_upload'] ?? true),
        ];
    }

    /** The scenario is bound to subtypes of one type — that binding is the routing key. */
    private function syncSubtypes(Workflow $scenario, array $subtypeIds): void
    {
        $scenario->subtypes()->sync($subtypeIds);
    }

    private function syncParameters(Workflow $scenario, array $parameters): void
    {
        $keptIds = [];

        foreach (array_values($parameters) as $i => $data) {
            $options = in_array($data['type'], WorkflowParameter::TYPES_WITH_OPTIONS, true)
                ? array_values(array_filter(array_map(fn ($option) => trim((string) $option), $data['options'] ?? [])))
                : null;

            $attributes = [
                'workflow_id'  => $scenario->id,
                'key'          => $data['key'],
                'label'        => $data['label'],
                'type'         => $data['type'],
                'options'      => $options,
                'is_required'  => !empty($data['is_required']),
                'sort_order'   => $i,
            ];

            $existing = !empty($data['id']) ? $scenario->parameters()->find($data['id']) : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = WorkflowParameter::create($attributes)->id;
            }
        }

        $scenario->parameters()->whereNotIn('id', $keptIds ?: [0])->delete();
    }
}
