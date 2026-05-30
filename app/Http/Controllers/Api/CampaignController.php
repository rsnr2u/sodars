<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request): JsonResponse
    {
        $query = Campaign::where('created_by', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $this->success([
            'campaigns' => $query->orderBy('id', 'desc')->paginate($request->integer('per_page', 25)),
        ], 'Campaigns fetched successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'advertiser_name' => ['required', 'string', 'max:255'],
            'agency_name' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_mobile' => ['required', 'string', 'max:20'],
            'customer_email' => ['required', 'email', 'max:255'],
            'budget' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
            'locations' => ['required', 'array'],
            'locations.*.country_id' => ['required', 'integer', 'exists:countries,id'],
            'locations.*.state_id' => ['required', 'integer', 'exists:states,id'],
            'locations.*.district_id' => ['required', 'integer', 'exists:districts,id'],
            'locations.*.city_id' => ['required', 'integer', 'exists:cities,id'],
            'locations.*.area_id' => ['nullable', 'integer', 'exists:areas,id'],
        ]);

        return DB::transaction(function () use ($request, $payload) {
            $yearMonthDay = now()->format('Ymd');
            $count = Campaign::whereDate('created_at', now()->toDateString())->count() + 1;
            $campaignCode = sprintf('CMP-%s-%04d', $yearMonthDay, $count);

            $campaign = Campaign::create([
                'campaign_code' => $campaignCode,
                'title' => $payload['title'],
                'advertiser_name' => $payload['advertiser_name'],
                'agency_name' => $payload['agency_name'] ?? null,
                'customer_name' => $payload['customer_name'],
                'customer_mobile' => $payload['customer_mobile'],
                'customer_email' => $payload['customer_email'],
                'budget' => $payload['budget'],
                'start_date' => $payload['start_date'],
                'end_date' => $payload['end_date'],
                'notes' => $payload['notes'] ?? null,
                'status' => 'Draft',
                'created_by' => $request->user()->id,
            ]);

            foreach ($payload['locations'] as $loc) {
                CampaignLocation::create([
                    'campaign_id' => $campaign->id,
                    'country_id' => $loc['country_id'],
                    'state_id' => $loc['state_id'],
                    'district_id' => $loc['district_id'],
                    'city_id' => $loc['city_id'],
                    'area_id' => $loc['area_id'] ?? null,
                ]);
            }

            return $this->success(['campaign' => $campaign->load('locations')], 'Campaign created successfully.', 211);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('created_by', $request->user()->id)
            ->with(['locations', 'bookings.inventory'])
            ->findOrFail($id);

        return $this->success(['campaign' => $campaign], 'Campaign details fetched successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('created_by', $request->user()->id)->findOrFail($id);

        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'advertiser_name' => ['sometimes', 'string', 'max:255'],
            'agency_name' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_mobile' => ['sometimes', 'string', 'max:20'],
            'customer_email' => ['sometimes', 'email', 'max:255'],
            'budget' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['Draft', 'Pending', 'Processing', 'Approval Pending', 'Confirmed', 'Active', 'Completed', 'Cancelled', 'Expired'])],
        ]);

        $campaign->update($payload);

        return $this->success(['campaign' => $campaign], 'Campaign updated successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('created_by', $request->user()->id)->findOrFail($id);
        $campaign->delete();

        return $this->success(message: 'Campaign soft-deleted successfully.');
    }
}
