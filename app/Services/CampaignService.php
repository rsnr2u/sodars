<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\CampaignLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    // ─── Query / Fetch ─────────────────────────────────────────────────────────

    public function paginateForUser(int $userId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Campaign::where('created_by', $userId)
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('title', 'like', "%{$v}%")
                  ->orWhere('campaign_code', 'like', "%{$v}%")
                  ->orWhere('customer_name', 'like', "%{$v}%");
            }))
            ->when($filters['start_date'] ?? null, fn ($q, $v) => $q->whereDate('start_date', '>=', $v))
            ->when($filters['end_date'] ?? null, fn ($q, $v) => $q->whereDate('end_date', '<=', $v))
            ->withCount('bookings')
            ->latest()
            ->paginate($perPage);
    }

    public function paginateAll(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Campaign::query()
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('title', 'like', "%{$v}%")
                  ->orWhere('campaign_code', 'like', "%{$v}%")
                  ->orWhere('customer_name', 'like', "%{$v}%");
            }))
            ->when($filters['created_by'] ?? null, fn ($q, $v) => $q->where('created_by', $v))
            ->with('creator:id,name,email')
            ->withCount('bookings')
            ->latest()
            ->paginate($perPage);
    }

    public function findForUser(int $campaignId, int $userId): Campaign
    {
        return Campaign::where('created_by', $userId)
            ->with(['locations.city', 'locations.state', 'bookings.inventory', 'creator'])
            ->findOrFail($campaignId);
    }

    public function findAny(int $campaignId): Campaign
    {
        return Campaign::with(['locations.city', 'locations.state', 'bookings.inventory', 'creator'])
            ->findOrFail($campaignId);
    }

    // ─── Create ────────────────────────────────────────────────────────────────

    public function create(array $data, int $userId): Campaign
    {
        return DB::transaction(function () use ($data, $userId): Campaign {
            $campaignCode = $this->generateCode();

            $campaign = Campaign::create([
                'campaign_code'  => $campaignCode,
                'title'          => $data['title'],
                'advertiser_name' => $data['advertiser_name'],
                'agency_name'    => $data['agency_name'] ?? null,
                'customer_name'  => $data['customer_name'],
                'customer_mobile' => $data['customer_mobile'],
                'customer_email' => $data['customer_email'],
                'budget'         => $data['budget'],
                'start_date'     => $data['start_date'],
                'end_date'       => $data['end_date'],
                'notes'          => $data['notes'] ?? null,
                'status'         => 'Draft',
                'created_by'     => $userId,
            ]);

            if (! empty($data['locations'])) {
                $this->syncLocations($campaign, $data['locations']);
            }

            return $campaign->load('locations');
        });
    }

    // ─── Update ────────────────────────────────────────────────────────────────

    public function update(Campaign $campaign, array $data): Campaign
    {
        return DB::transaction(function () use ($campaign, $data): Campaign {
            $campaign->update(array_filter([
                'title'           => $data['title'] ?? null,
                'advertiser_name' => $data['advertiser_name'] ?? null,
                'agency_name'     => $data['agency_name'] ?? null,
                'customer_name'   => $data['customer_name'] ?? null,
                'customer_mobile' => $data['customer_mobile'] ?? null,
                'customer_email'  => $data['customer_email'] ?? null,
                'budget'          => $data['budget'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => $data['status'] ?? null,
            ], fn ($v) => ! is_null($v)));

            if (isset($data['locations'])) {
                $this->syncLocations($campaign, $data['locations']);
            }

            return $campaign->fresh('locations');
        });
    }

    // ─── Delete ────────────────────────────────────────────────────────────────

    public function delete(Campaign $campaign): void
    {
        // Only allow delete if no confirmed bookings
        $hasConfirmed = $campaign->bookings()
            ->whereIn('booking_status', ['Confirmed', 'Active'])
            ->exists();

        if ($hasConfirmed) {
            throw new \RuntimeException('Cannot delete a campaign with active or confirmed bookings.');
        }

        $campaign->delete();
    }

    // ─── Status Management ─────────────────────────────────────────────────────

    public function updateStatus(Campaign $campaign, string $status): Campaign
    {
        $campaign->update(['status' => $status]);
        return $campaign->fresh();
    }

    // ─── Budget Reconciliation ─────────────────────────────────────────────────

    /**
     * Recalculate and update campaign financial totals from confirmed bookings.
     */
    public function reconcileBudget(Campaign $campaign): Campaign
    {
        $aggregates = Booking::where('campaign_id', $campaign->id)
            ->whereIn('booking_status', ['Confirmed', 'Active', 'Completed'])
            ->selectRaw('
                SUM(total_amount) as total_amount,
                SUM(gst_amount) as gst_amount,
                SUM(provider_amount) as provider_amount,
                SUM(commission_amount) as commission_amount
            ')
            ->first();

        $campaign->update([
            'campaign_total_amount'      => $aggregates->total_amount ?? 0,
            'campaign_gst_amount'        => $aggregates->gst_amount ?? 0,
            'campaign_provider_amount'   => $aggregates->provider_amount ?? 0,
            'campaign_commission_amount' => $aggregates->commission_amount ?? 0,
        ]);

        return $campaign->fresh();
    }

    // ─── Booking Summary ───────────────────────────────────────────────────────

    public function getBookingsSummary(Campaign $campaign): array
    {
        $bookings = $campaign->bookings()->with('inventory:id,title,media_type,provider_id')->get();

        $statusBreakdown = $bookings->groupBy('booking_status')
            ->map(fn ($group) => [
                'count'        => $group->count(),
                'total_amount' => $group->sum('total_amount'),
            ]);

        return [
            'total_bookings'    => $bookings->count(),
            'status_breakdown'  => $statusBreakdown,
            'budget'            => $campaign->budget,
            'spent'             => $campaign->campaign_total_amount,
            'remaining_budget'  => max(0, $campaign->budget - $campaign->campaign_total_amount),
            'bookings'          => $bookings,
        ];
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function generateCode(): string
    {
        $yearMonthDay = now()->format('Ymd');
        $count        = Campaign::whereDate('created_at', now()->toDateString())->count() + 1;

        return sprintf('CMP-%s-%04d', $yearMonthDay, $count);
    }

    private function syncLocations(Campaign $campaign, array $locations): void
    {
        // Remove old locations and re-insert
        $campaign->locations()->delete();

        foreach ($locations as $loc) {
            CampaignLocation::create([
                'campaign_id' => $campaign->id,
                'country_id'  => $loc['country_id'],
                'state_id'    => $loc['state_id'],
                'district_id' => $loc['district_id'],
                'city_id'     => $loc['city_id'],
                'area_id'     => $loc['area_id'] ?? null,
            ]);
        }
    }
}
