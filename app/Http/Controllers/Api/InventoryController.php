<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryGallery;
use App\Models\InventoryPricing;
use App\Models\InventoryMaintenance;
use App\Models\ProviderStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use RespondsWithApi;

    private function getProviderId(Request $request): int
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        abort_unless($staff && $staff->provider_id, 403, 'Unauthorized. Not a provider staff member.');
        return $staff->provider_id;
    }

    public function index(Request $request): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $query = Inventory::where('provider_id', $providerId);

        if ($request->filled('media_type')) {
            $query->where('media_type', $request->query('media_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $this->success([
            'inventory' => $query->orderBy('id', 'desc')->paginate($request->integer('per_page', 25)),
        ], 'Inventory fetched successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $providerId = $this->getProviderId($request);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'media_type' => ['required', Rule::in(['Hoarding', 'Digital Screen', 'Transit Media', 'Bus Shelter', 'Mall Media', 'Airport Media'])],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'landmark_id' => ['nullable', 'integer', 'exists:landmarks,id'],
            'road_id' => ['nullable', 'integer', 'exists:roads,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'width' => ['required', 'numeric', 'min:0.1'],
            'height' => ['required', 'numeric', 'min:0.1'],
            'facing_direction' => ['required', 'string', 'max:100'],
            'lighting_type' => ['required', 'string', 'max:100'],
            'traffic_type' => ['required', 'string', 'max:100'],
            'visibility_score' => ['nullable', 'numeric', 'between:0,100'],
            'traffic_score' => ['nullable', 'numeric', 'between:0,100'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'weekly_price' => ['required', 'numeric', 'min:0'],
            'daily_price' => ['required', 'numeric', 'min:0'],
            'marketplace_enabled' => ['boolean'],
            'featured' => ['boolean'],
            'status' => ['sometimes', Rule::in(['Available', 'Reserved', 'Booked', 'Maintenance', 'Inactive', 'Blocked'])],
        ]);

        return DB::transaction(function () use ($providerId, $payload) {
            $yearMonthDay = now()->format('Ymd');
            $count = Inventory::whereDate('created_at', now()->toDateString())->count() + 1;
            $inventoryCode = sprintf('INV-%s-%04d', $yearMonthDay, $count);

            $inventory = Inventory::create(array_merge($payload, [
                'provider_id' => $providerId,
                'inventory_code' => $inventoryCode,
                'status' => $payload['status'] ?? 'Available',
                'marketplace_enabled' => $payload['marketplace_enabled'] ?? false,
                'featured' => $payload['featured'] ?? false,
            ]));

            return $this->success(['inventory' => $inventory], 'Inventory created successfully.', 211);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->with(['gallery', 'pricing', 'maintenance'])->findOrFail($id);

        return $this->success(['inventory' => $inventory], 'Inventory item fetched successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'media_type' => ['sometimes', Rule::in(['Hoarding', 'Digital Screen', 'Transit Media', 'Bus Shelter', 'Mall Media', 'Airport Media'])],
            'category' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'state_id' => ['sometimes', 'integer', 'exists:states,id'],
            'district_id' => ['sometimes', 'integer', 'exists:districts,id'],
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
            'area_id' => ['sometimes', 'integer', 'exists:areas,id'],
            'landmark_id' => ['nullable', 'integer', 'exists:landmarks,id'],
            'road_id' => ['nullable', 'integer', 'exists:roads,id'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'width' => ['sometimes', 'numeric', 'min:0.1'],
            'height' => ['sometimes', 'numeric', 'min:0.1'],
            'facing_direction' => ['sometimes', 'string', 'max:100'],
            'lighting_type' => ['sometimes', 'string', 'max:100'],
            'traffic_type' => ['sometimes', 'string', 'max:100'],
            'visibility_score' => ['nullable', 'numeric', 'between:0,100'],
            'traffic_score' => ['nullable', 'numeric', 'between:0,100'],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'weekly_price' => ['sometimes', 'numeric', 'min:0'],
            'daily_price' => ['sometimes', 'numeric', 'min:0'],
            'marketplace_enabled' => ['boolean'],
            'featured' => ['boolean'],
            'status' => ['sometimes', Rule::in(['Available', 'Reserved', 'Booked', 'Maintenance', 'Inactive', 'Blocked'])],
        ]);

        $inventory->update($payload);

        return $this->success(['inventory' => $inventory], 'Inventory updated successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);
        $inventory->delete();

        return $this->success(message: 'Inventory item soft-deleted successfully.');
    }

    // ==========================================
    // GALLERY ATTACHMENTS
    // ==========================================

    public function uploadMedia(Request $request, int $id): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        $payload = $request->validate([
            'file_type' => ['required', Rule::in(['Image', 'Video', 'Drone'])],
            'file_path' => ['required', 'file', 'max:20480'], // max 20MB
            'is_primary' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $path = $request->file('file_path')->store('gallery', 'local');
        $isPrimary = $payload['is_primary'] ?? false;

        return DB::transaction(function () use ($inventory, $payload, $path, $isPrimary) {
            if ($isPrimary) {
                InventoryGallery::where('inventory_id', $inventory->id)->update(['is_primary' => false]);
            }

            $media = InventoryGallery::create([
                'inventory_id' => $inventory->id,
                'file_type' => $payload['file_type'],
                'file_path' => $path,
                'is_primary' => $isPrimary,
                'sort_order' => $payload['sort_order'] ?? 0,
            ]);

            return $this->success(['media' => $media], 'Media file uploaded to gallery successfully.');
        });
    }

    public function deleteMedia(Request $request, int $id, int $mediaId): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        $media = InventoryGallery::where('inventory_id', $inventory->id)->where('id', $mediaId)->firstOrFail();
        $media->delete();

        return $this->success(message: 'Media deleted from gallery successfully.');
    }

    public function setPrimaryMedia(Request $request, int $id, int $mediaId): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        $media = InventoryGallery::where('inventory_id', $inventory->id)->where('id', $mediaId)->firstOrFail();

        DB::transaction(function () use ($inventory, $media) {
            InventoryGallery::where('inventory_id', $inventory->id)->update(['is_primary' => false]);
            $media->update(['is_primary' => true]);
        });

        return $this->success(['media' => $media], 'Set primary media successfully.');
    }

    // ==========================================
    // PRICING RULES
    // ==========================================

    public function getPricing(Request $request, int $id): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        return $this->success(['pricing' => $inventory->pricing], 'Pricing rules fetched.');
    }

    public function storePricing(Request $request, int $id): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        $payload = $request->validate([
            'price_type' => ['required', Rule::in(['Daily', 'Weekly', 'Monthly', 'Festival', 'Special'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $pricing = InventoryPricing::create(array_merge($payload, [
            'inventory_id' => $inventory->id,
        ]));

        return $this->success(['pricing' => $pricing], 'Custom pricing rule added successfully.');
    }

    public function deletePricing(Request $request, int $id, int $pricingId): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        $pricing = InventoryPricing::where('inventory_id', $inventory->id)->where('id', $pricingId)->firstOrFail();
        $pricing->delete();

        return $this->success(message: 'Custom pricing rule deleted successfully.');
    }

    // ==========================================
    // MAINTENANCE RULES
    // ==========================================

    public function getMaintenance(Request $request, int $id): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        return $this->success(['maintenance' => $inventory->maintenance], 'Maintenance windows fetched.');
    }

    public function storeMaintenance(Request $request, int $id): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        $payload = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['Active', 'Completed'])],
        ]);

        $maintenance = InventoryMaintenance::create(array_merge($payload, [
            'inventory_id' => $inventory->id,
            'status' => $payload['status'] ?? 'Active',
        ]));

        // Optionally update inventory status if maintenance is currently active
        $today = now()->toDateString();
        if ($payload['start_date'] <= $today && $payload['end_date'] >= $today && ($payload['status'] ?? 'Active') === 'Active') {
            $inventory->update(['status' => 'Maintenance']);
        }

        return $this->success(['maintenance' => $maintenance], 'Maintenance block added successfully.');
    }

    public function deleteMaintenance(Request $request, int $id, int $maintenanceId): JsonResponse
    {
        $providerId = $this->getProviderId($request);
        $inventory = Inventory::where('provider_id', $providerId)->findOrFail($id);

        $maintenance = InventoryMaintenance::where('inventory_id', $inventory->id)->where('id', $maintenanceId)->firstOrFail();
        $maintenance->delete();

        // Revert status to Available if it was in maintenance and is no longer blocked
        if ($inventory->status === 'Maintenance') {
            $inventory->update(['status' => 'Available']);
        }

        return $this->success(message: 'Maintenance block deleted successfully.');
    }

    // ==========================================
    // PUBLIC / MARKETPLACE ENDPOINTS
    // ==========================================

    public function publicIndex(Request $request): JsonResponse
    {
        // Only return listings that are marketplace enabled AND their provider is approved and enabled
        $query = Inventory::where('marketplace_enabled', true)
            ->whereHas('provider', function ($q) {
                $q->where('status', 'Approved')->where('marketplace_enabled', true);
            })
            ->whereNotIn('status', ['Inactive', 'Blocked']);

        // Filtering
        foreach (['country_id', 'state_id', 'district_id', 'city_id', 'area_id'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->integer($column));
            }
        }

        if ($request->filled('media_type')) {
            $query->where('media_type', $request->query('media_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $items = $query->with(['gallery' => function ($q) {
            $q->where('is_primary', true)->orWhere('sort_order', 0);
        }])->orderBy('featured', 'desc')->orderBy('id', 'desc')->paginate($request->integer('per_page', 24));

        return $this->success([
            'listings' => $items,
        ], 'Marketplace listings fetched successfully.');
    }

    public function publicShow(int $id): JsonResponse
    {
        $inventory = Inventory::where('marketplace_enabled', true)
            ->whereHas('provider', function ($q) {
                $q->where('status', 'Approved')->where('marketplace_enabled', true);
            })
            ->whereNotIn('status', ['Inactive', 'Blocked'])
            ->with(['gallery', 'pricing', 'provider' => function ($q) {
                $q->select('id', 'company_name', 'logo');
            }])
            ->findOrFail($id);

        return $this->success(['listing' => $inventory], 'Listing detail fetched successfully.');
    }
}
