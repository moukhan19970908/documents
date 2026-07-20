<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcedureChecklistItem;
use App\Models\ProcedureStage;
use App\Models\ProcedureTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcedureTemplateController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index()
    {
        return view('admin.procedures.index', [
            'templates' => ProcedureTemplate::withCount(['stages', 'checklistItems', 'procedures'])
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $template = ProcedureTemplate::create($data);
        $this->audit->log('procedure_template_created', $template);

        return redirect()->route('admin.procedures.edit', $template)->with('success', 'Шаблон создан. Добавьте этапы сценария.');
    }

    public function edit(ProcedureTemplate $template)
    {
        return view('admin.procedures.edit', [
            'template'      => $template->load('stages.executorUser', 'checklistItems.executorUser'),
            'users'         => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'roles'         => Role::orderBy('name')->get(['code', 'name']),
            'stageTypes'    => ProcedureStage::TYPES,
            'stageModes'    => ProcedureStage::EXECUTOR_MODES,
            'fieldTypes'    => ProcedureChecklistItem::FIELD_TYPES,
            'itemModes'     => ProcedureChecklistItem::EXECUTOR_MODES,
        ]);
    }

    public function update(Request $request, ProcedureTemplate $template)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $template->update($data);
        $this->audit->log('procedure_template_updated', $template);

        return back()->with('success', 'Шаблон обновлён.');
    }

    public function destroy(ProcedureTemplate $template)
    {
        if ($template->procedures()->exists()) {
            return back()->with('error', 'Нельзя удалить шаблон, по которому уже есть процедуры.');
        }

        $template->delete();
        $this->audit->log('procedure_template_deleted', $template);

        return redirect()->route('admin.procedures.index')->with('success', 'Шаблон удалён.');
    }

    // --- Этапы сценария ---

    public function storeStage(Request $request, ProcedureTemplate $template)
    {
        $data = $this->validateStage($request);
        $data['position'] = (int) $template->stages()->max('position') + 1;

        $template->stages()->create($this->normalizeStage($data));

        return back()->with('success', 'Этап добавлен.');
    }

    public function updateStage(Request $request, ProcedureTemplate $template, ProcedureStage $stage)
    {
        abort_unless($stage->procedure_template_id === $template->id, 404);

        $data = $this->validateStage($request);
        $data['position'] = $request->integer('position') ?: $stage->position;

        $stage->update($this->normalizeStage($data));

        return back()->with('success', 'Этап обновлён.');
    }

    public function destroyStage(ProcedureTemplate $template, ProcedureStage $stage)
    {
        abort_unless($stage->procedure_template_id === $template->id, 404);
        $stage->delete();

        return back()->with('success', 'Этап удалён.');
    }

    private function validateStage(Request $request): array
    {
        return $request->validate([
            'type'                => ['required', Rule::in(array_keys(ProcedureStage::TYPES))],
            'title'               => ['required', 'string', 'max:255'],
            'executor_mode'       => ['required', Rule::in(array_keys(ProcedureStage::EXECUTOR_MODES))],
            'executor_role'       => ['nullable', 'string', 'max:50'],
            'executor_user_id'    => ['nullable', 'exists:users,id'],
            'require_attachments' => ['nullable', 'boolean'],
            'position'            => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /** Обнулить исполнителя по режиму: авто-этапам исполнитель не нужен, initiator тоже. */
    private function normalizeStage(array $data): array
    {
        $data['require_attachments'] = (bool) ($data['require_attachments'] ?? false);

        if (in_array($data['type'], ProcedureStage::AUTO_TYPES, true) || $data['executor_mode'] === 'initiator') {
            $data['executor_mode']    = in_array($data['type'], ProcedureStage::AUTO_TYPES, true) ? 'initiator' : $data['executor_mode'];
            $data['executor_user_id'] = null;
            $data['executor_role']    = null;
        }
        if ($data['executor_mode'] === 'user') {
            $data['executor_role'] = null;
        }
        if ($data['executor_mode'] === 'role') {
            $data['executor_user_id'] = null;
        }

        return $data;
    }

    // --- Пункты чек-листа (ЧАСТЬ 1, пресеты) ---

    public function storeItem(Request $request, ProcedureTemplate $template)
    {
        $data = $this->validateItem($request);
        $data['position'] = (int) $template->checklistItems()->max('position') + 1;

        $template->checklistItems()->create($this->normalizeItem($data));

        return back()->with('success', 'Пункт чек-листа добавлен.');
    }

    public function updateItem(Request $request, ProcedureTemplate $template, ProcedureChecklistItem $item)
    {
        abort_unless($item->procedure_template_id === $template->id, 404);

        $data = $this->validateItem($request);
        $data['position'] = $request->integer('position') ?: $item->position;

        $item->update($this->normalizeItem($data));

        return back()->with('success', 'Пункт чек-листа обновлён.');
    }

    public function destroyItem(ProcedureTemplate $template, ProcedureChecklistItem $item)
    {
        abort_unless($item->procedure_template_id === $template->id, 404);
        $item->delete();

        return back()->with('success', 'Пункт чек-листа удалён.');
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'department'       => ['nullable', 'string', 'max:255'],
            'title'            => ['required', 'string', 'max:255'],
            'field_type'       => ['required', Rule::in(array_keys(ProcedureChecklistItem::FIELD_TYPES))],
            'options_raw'      => ['nullable', 'string', 'max:2000'],
            'executor_mode'    => ['required', Rule::in(array_keys(ProcedureChecklistItem::EXECUTOR_MODES))],
            'executor_user_id' => ['nullable', 'exists:users,id'],
            'spawns_task'      => ['nullable', 'boolean'],
            'position'         => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /** Варианты списка вводятся построчно; исполнитель нужен только режиму user. */
    private function normalizeItem(array $data): array
    {
        $data['spawns_task'] = (bool) ($data['spawns_task'] ?? false);

        $data['options'] = null;
        if (($data['field_type'] ?? null) === 'select' && ! empty($data['options_raw'])) {
            $data['options'] = array_values(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', $data['options_raw'])
            )));
        }
        unset($data['options_raw']);

        if (($data['executor_mode'] ?? null) !== 'user') {
            $data['executor_user_id'] = null;
        }

        return $data;
    }
}
