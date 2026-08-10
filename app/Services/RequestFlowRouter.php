<?php

namespace App\Services;

use App\Models\ApprovalRoute;
use App\Models\ApprovalRouteStep;
use App\Models\RequestFlow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Маршрут для заявки: если для вида заявки опубликован граф-процесс — прогоняем его
 * исполнителем и материализуем результат в одноразовый маршрут (шаги с конкретными
 * согласующими). Иначе — обычный автоподбор ApprovalRoute. Так граф встраивается в
 * существующий движок согласования без его переписывания.
 */
class RequestFlowRouter
{
    public function __construct(
        private ApprovalService $approvals,
        private RequestFlowExecutor $executor,
    ) {}

    /**
     * @return array{route: ?ApprovalRoute, tasks: array, via_graph: bool}
     */
    public function routeFor(User $user, string $type, array $fields = []): array
    {
        $flow = RequestFlow::where('request_type', $type)->where('status', 'published')->first();

        if ($flow) {
            $plan  = $this->executor->resolve($flow, $user, $fields);
            $route = $this->materialize($flow, $type, $plan);

            if ($route) {
                return ['route' => $route, 'tasks' => $plan['tasks'], 'via_graph' => true];
            }
            // Граф без разрешённых согласующих — не блокируем подачу, идём в автоподбор.
        }

        return ['route' => $this->approvals->findRoute($user, $type), 'tasks' => [], 'via_graph' => false];
    }

    /** Собрать одноразовый маршрут из цепочки согласующих плана (только с определённым человеком). */
    private function materialize(RequestFlow $flow, string $type, array $plan): ?ApprovalRoute
    {
        $steps = collect($plan['approvals'])->filter(fn ($a) => ! empty($a['approver_id']))->values();

        if ($steps->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($flow, $type, $steps) {
            $route = ApprovalRoute::create([
                'name'                  => 'Граф: ' . $flow->name,
                'request_type'          => $type,
                'department_id'         => null,
                'applies_to_role_level' => null,
                'is_active'             => true,
                'is_ephemeral'          => true,
            ]);

            $order = 1;
            foreach ($steps as $a) {
                ApprovalRouteStep::create([
                    'route_id'         => $route->id,
                    'step_order'       => $order++,
                    'approver_user_id' => $a['approver_id'],
                ]);
            }

            return $route->load('steps');
        });
    }
}
