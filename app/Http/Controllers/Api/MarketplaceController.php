<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Inventory;
use App\Models\Lead;
use App\Models\MarketplaceInquiry;
use App\Models\Provider;
use App\Services\SearchEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    use RespondsWithApi;

    protected SearchEngine $searchEngine;

    public function __construct(SearchEngine $searchEngine)
    {
        $this->searchEngine = $searchEngine;
    }

    public function index(Request $request): JsonResponse
    {
        $listings = $this->searchEngine->search($request->all());

        return $this->success([
            'listings' => $listings,
        ], 'Marketplace listings fetched successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $listing = Inventory::where('marketplace_enabled', true)
            ->whereHas('provider', function ($q) {
                $q->where('status', 'Approved')->where('marketplace_enabled', true);
            })
            ->whereNotIn('status', ['Inactive', 'Blocked'])
            ->with(['gallery', 'pricing', 'provider' => function ($q) {
                $q->select('id', 'company_name', 'logo');
            }])
            ->findOrFail($id);

        return $this->success([
            'listing' => $listing,
        ], 'Listing details fetched successfully.');
    }

    public function featured(Request $request): JsonResponse
    {
        $listings = Inventory::where('marketplace_enabled', true)
            ->where('featured', true)
            ->whereHas('provider', function ($q) {
                $q->where('status', 'Approved')->where('marketplace_enabled', true);
            })
            ->whereNotIn('status', ['Inactive', 'Blocked'])
            ->with(['gallery' => function ($q) {
                $q->orderBy('is_primary', 'desc')->orderBy('sort_order', 'asc');
            }])
            ->orderBy('id', 'desc')
            ->limit($request->integer('limit', 10))
            ->get();

        return $this->success([
            'listings' => $listings,
        ], 'Featured listings fetched successfully.');
    }

    public function providers(Request $request): JsonResponse
    {
        $providers = Provider::where('status', 'Approved')
            ->where('marketplace_enabled', true)
            ->orderBy('company_name', 'asc')
            ->paginate($request->integer('per_page', 24));

        return $this->success([
            'providers' => $providers,
        ], 'Providers directory fetched successfully.');
    }

    public function cities(): JsonResponse
    {
        $cities = City::join('inventory', 'cities.id', '=', 'inventory.city_id')
            ->join('providers', 'inventory.provider_id', '=', 'providers.id')
            ->where('inventory.marketplace_enabled', true)
            ->where('providers.status', 'Approved')
            ->where('providers.marketplace_enabled', true)
            ->whereNotIn('inventory.status', ['Inactive', 'Blocked'])
            ->select('cities.id', 'cities.name', 'cities.latitude', 'cities.longitude')
            ->selectRaw('count(inventory.id) as inventory_count')
            ->groupBy('cities.id', 'cities.name', 'cities.latitude', 'cities.longitude')
            ->orderBy('inventory_count', 'desc')
            ->get();

        return $this->success([
            'cities' => $cities,
        ], 'Active cities fetched successfully.');
    }

    public function inquire(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'inventory_id' => ['nullable', 'integer', 'exists:inventory,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($payload) {
            $inquiry = MarketplaceInquiry::create(array_merge($payload, [
                'status' => 'Pending',
            ]));

            // Automatic lead routing to CRM
            $cityId = null;
            if (!empty($payload['inventory_id'])) {
                $inventory = Inventory::find($payload['inventory_id']);
                if ($inventory) {
                    $cityId = $inventory->city_id;
                }
            }

            $lead = Lead::create([
                'name' => $payload['name'],
                'company_name' => $payload['company_name'],
                'mobile' => $payload['mobile'],
                'email' => $payload['email'],
                'city_id' => $cityId,
                'lead_source' => 'Marketplace Inquiry',
                'status' => 'New',
                'remarks' => $payload['message'] ?? 'Created from public marketplace inquiry.',
            ]);

            return $this->success([
                'inquiry' => $inquiry,
                'lead' => $lead,
            ], 'Inquiry submitted successfully and routed to CRM.', 211);
        });
    }
}
