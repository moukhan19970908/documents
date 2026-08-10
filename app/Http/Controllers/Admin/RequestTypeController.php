<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRoute;
use App\Models\RequestFlow;
use App\Models\RequestFlowNode;
use App\Models\Role;
use App\Models\TripTaskSetting;
use App\Models\User;
use App\Services\RequestFlowService;
use Illuminate\Http\Request;

/**
 * «Виды заявок» — единая страница настройки заявок бесшовного стека (Отпуск / Командировка / Иное).
 * Не отдельный движок: сводит в одно место то, что настраивается — маршруты согласования,
 * исполнителей заданий, поля формы, реестр/замещение — со ссылками на конкретные редакторы.
 */
class RequestTypeController extends Controller
{
    public function index()
    {
        $routesByType = ApprovalRoute::with(['steps', 'department'])
            ->where('is_ephemeral', false)
            ->orderBy('department_id')->get()->groupBy('request_type');

        $ts = TripTaskSetting::current();
        $names = User::whereIn('id', array_filter([
            $ts->hr_user_id, $ts->office_manager_id, $ts->logistics_id, $ts->transport_id,
        ]))->pluck('name', 'id');

        $types = [
            [
                'key'      => 'vacation',
                'name'     => 'Отпуск',
                'color'    => 'emerald',
                'desc'     => 'Ежегодный, за свой счёт, больничный, отгул',
                'fields'   => ['Вид отпуска', 'Дата начала', 'Дата окончания', 'Комментарий', 'Замещающий'],
                'subtypes' => ['Ежегодный', 'За свой счёт', 'Больничный', 'Иное'],
                'routes'   => $this->mapRoutes($routesByType->get('vacation')),
                'reg_routes' => $this->mapRoutes($routesByType->get('vacation_registry')),
                'executors' => null,
                'registry' => true,
                'substitution' => true,
            ],
            [
                'key'      => 'trip',
                'name'     => 'Командировка',
                'color'    => 'blue',
                'desc'     => 'Служебные поездки: транспорт, проживание, суточные',
                'fields'   => ['Город', 'Даты', 'Цель', 'Суточные', 'Проживание', 'Транспорт', 'Комментарий', 'Замещающий'],
                'subtypes' => [],
                'routes'   => $this->mapRoutes($routesByType->get('trip')),
                'reg_routes' => $this->mapRoutes($routesByType->get('trip_registry')),
                'executors' => [
                    ['role' => 'Отдел кадров',           'name' => $names[$ts->hr_user_id] ?? null],
                    ['role' => 'Офис-менеджер',          'name' => $names[$ts->office_manager_id] ?? null],
                    ['role' => 'Директор по логистике',  'name' => $names[$ts->logistics_id] ?? null],
                    ['role' => 'Транспортный отдел',     'name' => $names[$ts->transport_id] ?? null],
                ],
                'registry' => true,
                'substitution' => true,
            ],
        ];

        return view('admin.request-types.index', compact('types'));
    }

    /** Граф-конструктор вида заявки. */
    public function edit(string $type, RequestFlowService $service)
    {
        abort_unless(isset(RequestFlow::REQUEST_TYPES[$type]), 404);

        $flow = RequestFlow::forType($type);

        $roleLevels = [1 => 'Сотрудник', 2 => 'Рук. отдела', 3 => 'Рук. департамента', 4 => 'Директор', 5 => 'Генеральный'];

        // Поля формы вида заявки — для узла «Условие по полю».
        $fields = [
            'trip' => [
                ['key' => 'transport_type', 'label' => 'Транспорт', 'options' => ['own' => 'Свой', 'company' => 'Организации']],
                ['key' => 'location_type',  'label' => 'Локация',   'options' => ['moscow' => 'Москва', 'spb' => 'СПб', 'sochi' => 'Сочи', 'other_rf' => 'Другой РФ', 'abroad' => 'За рубеж']],
            ],
            'vacation' => [
                ['key' => 'vacation_type', 'label' => 'Вид отпуска', 'options' => ['annual' => 'Ежегодный', 'unpaid' => 'За свой счёт', 'sick_leave' => 'Больничный', 'other' => 'Иное']],
            ],
        ][$type] ?? [];

        // Исполнители заданий (ключи каталога TripTask).
        $executors = [
            ['key' => 'hr', 'label' => 'Отдел кадров'],
            ['key' => 'office_manager', 'label' => 'Офис-менеджер'],
            ['key' => 'logistics', 'label' => 'Директор по логистике'],
            ['key' => 'transport', 'label' => 'Транспортный отдел'],
        ];

        $data = [
            'flow'       => ['id' => $flow->id, 'name' => $flow->name, 'status' => $flow->status],
            'types'      => RequestFlowNode::TYPES,
            'groups'     => RequestFlowNode::GROUPS,
            'nodes'      => $service->tree($flow),
            'roles'       => Role::orderBy('id')->get(['code', 'name']),
            'roleLevels'  => $roleLevels,
            'fields'      => $fields,
            'executors'   => $executors,
            'departments' => \App\Models\Department::orderBy('name')->get(['id', 'name']),
        ];

        return view('admin.request-types.edit', [
            'flow'      => $flow,
            'typeLabel' => RequestFlow::REQUEST_TYPES[$type],
            'type'      => $type,
            'data'      => $data,
        ]);
    }

    /** Сохранить нарисованный граф. */
    public function updateFlow(Request $request, string $type, RequestFlowService $service)
    {
        abort_unless(isset(RequestFlow::REQUEST_TYPES[$type]), 404);

        $validated = $request->validate(['graph' => ['required', 'string']]);
        $tree = json_decode($validated['graph'], true);

        abort_if(! is_array($tree), 422, 'Некорректный граф.');

        $service->save(RequestFlow::forType($type), $tree);

        return redirect()->route('admin.request-types.edit', $type)->with('success', 'Граф сохранён.');
    }

    /** Опубликовать граф — новые заявки этого вида пойдут по нему. */
    public function publishFlow(string $type)
    {
        abort_unless(isset(RequestFlow::REQUEST_TYPES[$type]), 404);

        $flow = RequestFlow::forType($type);
        abort_if($flow->nodes()->count() === 0, 422, 'Пустой граф публиковать нельзя.');

        $flow->update(['status' => 'published', 'version' => $flow->version + 1]);

        return redirect()->route('admin.request-types.edit', $type)->with('success', 'Граф опубликован — новые заявки пойдут по нему.');
    }

    /** Краткая сводка по маршрутам одного типа для карточки. */
    private function mapRoutes($routes)
    {
        return collect($routes ?? [])->map(fn (ApprovalRoute $r) => [
            'id'     => $r->id,
            'name'   => $r->name,
            'dept'   => $r->department?->name ?? 'Все отделы',
            'level'  => $r->applies_to_role_level ? 'уровень ' . $r->applies_to_role_level : 'любой уровень',
            'steps'  => $r->steps->count(),
            'active' => (bool) $r->is_active,
        ])->values();
    }
}
