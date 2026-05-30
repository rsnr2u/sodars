<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    use RespondsWithApi;

    private array $models = [
        'countries' => \App\Models\Country::class,
        'states' => \App\Models\State::class,
        'districts' => \App\Models\District::class,
        'cities' => \App\Models\City::class,
        'areas' => \App\Models\Area::class,
        'landmarks' => \App\Models\Landmark::class,
        'roads' => \App\Models\Road::class,
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        $model = $this->resolveModel($resource);
        $query = $model::query()->where('status', $request->query('status', 'Active'));

        foreach (['country_id', 'state_id', 'district_id', 'city_id', 'area_id'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->integer($column));
            }
        }

        return $this->success([
            'items' => $query->orderBy('name')->paginate($request->integer('per_page', 50)),
        ], ucfirst($resource).' fetched successfully.');
    }

    public function children(Request $request, int|string $parentId, string $resource, string $parentColumn): JsonResponse
    {
        $model = $this->resolveModel($resource);

        return $this->success([
            'items' => $model::query()
                ->where($parentColumn, $parentId)
                ->where('status', $request->query('status', 'Active'))
                ->orderBy('name')
                ->get(),
        ], ucfirst($resource).' fetched successfully.');
    }

    private function resolveModel(string $resource): string
    {
        abort_unless(isset($this->models[$resource]), 404, 'Unknown location resource.');

        /** @var class-string<Model> $model */
        $model = $this->models[$resource];

        return $model;
    }
}
