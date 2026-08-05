<?php

namespace App\Http\Controllers;

use App\Models\RoutePreset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Личные заготовки маршрута для composed-сценариев — читаются и сохраняются
 * прямо из формы запуска документа (fetch). Каждый видит и меняет только свои.
 */
class RoutePresetController extends Controller
{
    public function index()
    {
        return RoutePreset::where('user_id', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'config']);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePreset($request);

        $preset = RoutePreset::create([
            'user_id' => auth()->id(),
            'name'    => $validated['name'],
            'config'  => $this->normalizeConfig($validated['config']),
        ]);

        return response()->json($preset->only('id', 'name', 'config'), 201);
    }

    public function destroy(RoutePreset $routePreset)
    {
        abort_unless($routePreset->user_id === auth()->id(), 403);

        $routePreset->delete();

        return response()->noContent();
    }

    private function validatePreset(Request $request): array
    {
        return $request->validate([
            'name'                    => ['required', 'string', 'max:120'],
            'config'                  => ['required', 'array', 'min:1'],
            'config.*.phase'          => ['required', Rule::in(['approval', 'approve', 'ack', 'intake'])],
            'config.*.participants'   => ['required', 'array', 'min:1'],
            'config.*.participants.*' => ['integer', 'exists:users,id'],
        ]);
    }

    /** Хранить только фазу и id участников — состав людей заготовки от документа не зависит. */
    private function normalizeConfig(array $config): array
    {
        return array_values(array_map(fn ($block) => [
            'phase'        => $block['phase'],
            'participants' => array_values(array_unique(array_map('intval', $block['participants']))),
        ], $config));
    }
}
