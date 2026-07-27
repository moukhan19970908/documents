<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DocumentType;
use App\Services\AnalyticsMetricService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /** Группы метрик — готовые дашборды (ТЗ 27.1). */
    public const GROUPS = [
        'operational' => 'Операционная картина',
        'sla'         => 'Узкие места и SLA',
        'throughput'  => 'Пропускная способность',
        'load'        => 'Нагрузка на людей',
        'domain'      => 'Предметная',
    ];

    public function __construct(private AnalyticsMetricService $metrics) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->canSeeMenu('menu.analytics'), 403, 'Нет доступа к аналитике.');

        $group = array_key_exists($request->get('group'), self::GROUPS) ? $request->get('group') : 'operational';

        $filters = [
            'direction'  => $request->integer('direction') ?: null,
            'department' => $request->integer('department') ?: null,
            'type'       => $request->integer('type') ?: null,
            'from'       => $request->get('from') ?: null,
            'to'         => $request->get('to') ?: null,
            'dimension'  => $request->get('dimension'),
        ];

        $data = match ($group) {
            'operational' => $this->metrics->operational($filters),
            'sla'         => $this->metrics->sla($filters),
            'throughput'  => $this->metrics->throughput($filters),
            'load'        => $this->metrics->load($filters),
            'domain'      => $this->metrics->domain($filters),
        };

        $directions = Department::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);
        $types      = DocumentType::orderBy('name')->get(['id', 'name']);

        return view('analytics.index', [
            'groups'     => self::GROUPS,
            'group'      => $group,
            'data'       => $data,
            'filters'    => $filters,
            'directions' => $directions,
            'types'      => $types,
        ]);
    }
}
