<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\MarketplaceSearchLog;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchEngine
{
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Inventory::where('marketplace_enabled', true)
            ->whereHas('provider', function ($q) {
                $q->where('status', 'Approved')->where('marketplace_enabled', true);
            })
            ->whereNotIn('status', ['Inactive', 'Blocked']);

        // Geolocation cascading filters
        foreach (['country_id', 'state_id', 'district_id', 'city_id', 'area_id', 'landmark_id', 'road_id'] as $column) {
            if (!empty($filters[$column])) {
                $query->where($column, intval($filters[$column]));
            }
        }

        // Categorical / specifications filters
        if (!empty($filters['media_type'])) {
            $query->where('media_type', $filters['media_type']);
        }
        if (!empty($filters['lighting_type'])) {
            $query->where('lighting_type', $filters['lighting_type']);
        }
        if (!empty($filters['facing_direction'])) {
            $query->where('facing_direction', $filters['facing_direction']);
        }

        // Price range filtering (on monthly price)
        if (isset($filters['price_min'])) {
            $query->where('monthly_price', '>=', floatval($filters['price_min']));
        }
        if (isset($filters['price_max'])) {
            $query->where('monthly_price', '<=', floatval($filters['price_max']));
        }

        // Dimensions range filtering
        if (isset($filters['width_min'])) {
            $query->where('width', '>=', floatval($filters['width_min']));
        }
        if (isset($filters['width_max'])) {
            $query->where('width', '<=', floatval($filters['width_max']));
        }
        if (isset($filters['height_min'])) {
            $query->where('height', '>=', floatval($filters['height_min']));
        }
        if (isset($filters['height_max'])) {
            $query->where('height', '<=', floatval($filters['height_max']));
        }

        // Traffic score
        if (isset($filters['traffic_score_min'])) {
            $query->where('traffic_score', '>=', floatval($filters['traffic_score_min']));
        }

        // Geo-proximity / Radius Search (Haversine via bounding box)
        if (!empty($filters['latitude']) && !empty($filters['longitude']) && !empty($filters['radius_km'])) {
            $latitude = floatval($filters['latitude']);
            $longitude = floatval($filters['longitude']);
            $radiusKm = floatval($filters['radius_km']);

            $latDelta = $radiusKm / 111.0;
            $cosLat = cos(deg2rad($latitude));
            $lngDelta = $radiusKm / (111.0 * ($cosLat > 0 ? $cosLat : 0.0001));

            $query->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
                  ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta]);
        }

        // Availability date range filtering
        if (!empty($filters['availability_start']) && !empty($filters['availability_end'])) {
            $start = Carbon::parse($filters['availability_start'])->toDateString();
            $end = Carbon::parse($filters['availability_end'])->toDateString();

            // 1. Exclude if calendar has non-Available row in range
            $query->whereDoesntHave('calendar', function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start, $end])
                  ->where('status', '!=', 'Available');
            });

            // 2. Exclude if maintenance overlap in range
            $query->whereDoesntHave('maintenance', function ($q) use ($start, $end) {
                $q->where('status', 'Active')
                  ->where('start_date', '<=', $end)
                  ->where('end_date', '>=', $start);
            });
        }

        // Logging Search Telemetry
        if (!empty($filters['search_keyword']) || !empty($filters['city_id'])) {
            MarketplaceSearchLog::create([
                'search_keyword' => $filters['search_keyword'] ?? '',
                'city_id' => !empty($filters['city_id']) ? intval($filters['city_id']) : null,
                'media_type' => $filters['media_type'] ?? null,
                'searched_at' => now(),
            ]);
        }

        $perPage = intval($filters['per_page'] ?? 24);

        return $query->with(['gallery' => function ($q) {
            $q->orderBy('is_primary', 'desc')->orderBy('sort_order', 'asc');
        }])->orderBy('featured', 'desc')->orderBy('id', 'desc')->paginate($perPage);
    }
}
