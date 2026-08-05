<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlankTemplate;
use App\Models\Department;
use App\Models\Role;
use App\Models\DocumentApproval;
use App\Models\DocumentType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowParameter;
use App\Services\AuditService;
use App\Services\RouteGraphService;
use App\Services\ScenarioPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScenarioController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function index(Request $request)
    {
        $directions  = Department::whereNull('parent_id')->orderBy('name')->get();
        $directionId = $request->integer('direction') ?: null;
        $departmentId = $request->integer('department') ?: null;

        // Отделы выбранного направления — для доп. фильтра. Без направления фильтр по отделу не имеет смысла.
        $departments = collect();
        if ($directionId) {
            $childIds = array_values(array_diff(Department::getDescendantIds($directionId), [$directionId]));
            $departments = Department::whereIn('id', $childIds)->orderBy('name')->get();

            // Отсекаем отдел, не относящийся к выбранному направлению.
            if ($departmentId && ! in_array($departmentId, $childIds, true)) {
                $departmentId = null;
            }
        } else {
            $departmentId = null;
        }

        $query = Workflow::with(['owner', 'parameters', 'subtypes.type', 'versions'])
            ->withCount('stages')
            ->where('is_system', false)
            ->where('is_version', false)   // версии-копии — это история публикаций, а не отдельные сценарии
            ->orderByDesc('id');

        // Фильтр по направлению/отделу: сценарии, запускаемые нужными отделами.
        if ($directionId) {
            // Выбран конкретный отдел — он и его подотделы; иначе все отделы направления.
            $deptIds = $departmentId
                ? array_map('intval', Department::getDescendantIds($departmentId))
                : array_map('intval', Department::getDescendantIds($directionId));

            $matchingIds = Workflow::where('is_system', false)->where('is_version', false)
                ->whereNotNull('allowed_departments')
                ->pluck('allowed_departments', 'id')
                ->filter(fn ($depts) => array_intersect($deptIds, array_map('intval', (array) $depts)) !== [])
                ->keys()
                ->all();

            $query->whereIn('id', $matchingIds ?: [0]);
        }

        $scenarios = $query->paginate(9)->withQueryString();

        return view('admin.scenarios.index', compact('scenarios', 'directions', 'directionId', 'departments', 'departmentId'));
    }

    public function create()
    {
        return view('admin.scenarios.wizard', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validateScenario($request);

        // composed-сценарий не публикуется (маршрут собирается при запуске), поэтому
        // становится доступным для запуска сразу после сохранения — без шага «Маршрут».
        $composed = ($validated['launch_mode'] ?? 'fixed') === 'composed';

        $scenario = DB::transaction(function () use ($request, $validated, $composed) {
            $scenario = Workflow::create($this->scenarioAttributes($validated) + [
                'created_by'     => auth()->id(),
                'is_system'      => false,
                'is_active'      => $composed,               // a fixed draft is not offered for launch yet
                'status'         => $composed ? 'published' : 'draft',
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
        $scenario->load(['parameters', 'subtypes.type', 'owner', 'nodes', 'blankTemplates']);

        return view('admin.scenarios.wizard', $this->formData($scenario));
    }

    public function update(Request $request, Workflow $scenario)
    {
        $validated = $this->validateScenario($request, $scenario);

        DB::transaction(function () use ($request, $validated, $scenario) {
            $attributes = $this->scenarioAttributes($validated);

            // composed-сценарий живёт без публикации — держим его активным и опубликованным.
            if (($attributes['launch_mode'] ?? 'fixed') === 'composed') {
                $attributes['is_active'] = true;
                $attributes['status']   = 'published';
            }

            $scenario->update($attributes);

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

        return in_array($step, ['basic', 'classifier', 'parameters', 'route', 'rights'], true) ? $step : 'basic';
    }

    /**
     * Шаг 4 — маршрут. Конструктор присылает схему целиком, поэтому граф
     * переписывается заново: шаблон — это не то, по чему идут запущенные процессы.
     */
    public function updateRoute(Request $request, Workflow $scenario, RouteGraphService $graph)
    {
        $validated = $request->validate([
            'graph' => ['required', 'string'],
        ]);

        $tree = json_decode($validated['graph'], true);

        if (! is_array($tree)) {
            return back()->with('error', 'Схема маршрута повреждена — сохранить не удалось.');
        }

        $graph->save($scenario, $tree);

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
            'users'          => User::where('is_active', true)->where('role', '!=', 'external')
                                    ->with('department')->orderBy('name')->get(),
            'departments'    => Department::orderBy('name')->get(),
            // «Отделы» в резолвере берём как направления (корневые департаменты).
            'directions'     => Department::whereNull('parent_id')->orderBy('name')->get(),
            // «Роль / группа» берём из реальных ролей (Роли и доступы).
            'roles'          => Role::orderByDesc('level')->orderBy('name')->get(),
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
            'launch_mode'           => ['nullable', Rule::in(['fixed', 'composed'])],
            'icon'                  => ['nullable', Rule::in(Workflow::ICONS)],
            'owner_id'              => ['nullable', 'exists:users,id'],

            'document_type_id'      => ['nullable', 'exists:document_types,id'],
            'subtypes'              => ['nullable', 'array'],
            'subtypes.*'            => ['integer', 'exists:document_subtypes,id'],

            'blank_template_ids'    => ['nullable', 'array'],
            'blank_template_ids.*'  => ['integer', 'exists:blank_templates,id'],
            'allow_file_upload'     => ['nullable', 'boolean'],

            // Права запуска: доступ выдаётся отделам либо отдельным сотрудникам дополнительно.
            'allowed_departments'   => ['nullable', 'array'],
            'allowed_departments.*' => ['integer', 'exists:departments,id'],
            'allowed_users'         => ['nullable', 'array'],
            'allowed_users.*'       => ['integer', 'exists:users,id'],

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
            'launch_mode'       => $validated['launch_mode'] ?? 'fixed',
            'icon'              => $validated['icon'] ?? 'document',
            'owner_id'          => $validated['owner_id'] ?? auth()->id(),
            'document_type_id'  => $validated['document_type_id'] ?? null,
            // Чекбокса нет, пока в сценарии нет бланков, — тогда файл разрешён по умолчанию.
            'allow_file_upload' => (bool) ($validated['allow_file_upload'] ?? true),
            // Пустой список прав = без ограничений, поэтому храним null.
            'allowed_departments' => ($validated['allowed_departments'] ?? []) ?: null,
            'allowed_users'       => ($validated['allowed_users'] ?? []) ?: null,
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
