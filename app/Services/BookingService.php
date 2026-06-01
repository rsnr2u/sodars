<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCalendar;
use App\Models\BookingLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingService
{
    public function __construct(private readonly BookingEngine $engine) {}

    // ─── Query / Fetch ─────────────────────────────────────────────────────────

    public function paginateForCampaign(int $campaignId, int $userId, array $filters = []): LengthAwarePaginator
    {
        return Booking::where('campaign_id', $campaignId)
            ->where('created_by', $userId)
            ->when($filters['booking_status'] ?? null, fn ($q, $v) => $q->where('booking_status', $v))
            ->with('inventory:id,title,media_type,city_id')
            ->latest()
            ->paginate(25);
    }

    public function paginateAll(array $filters = []): LengthAwarePaginator
    {
        return Booking::query()
            ->when($filters['booking_status'] ?? null, fn ($q, $v) => $q->where('booking_status', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['provider_id'] ?? null, fn ($q, $v) => $q->where('provider_id', $v))
            ->when($filters['campaign_id'] ?? null, fn ($q, $v) => $q->where('campaign_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('booking_code', 'like', "%{$v}%"))
            ->with(['campaign:id,campaign_code,title', 'inventory:id,title,media_type', 'creator:id,name'])
            ->latest()
            ->paginate(25);
    }

    public function findOrFail(int $id): Booking
    {
        return Booking::with([
            'campaign:id,campaign_code,title,customer_name',
            'inventory:id,title,media_type,city_id',
            'artworks',
            'calendar',
            'logs',
            'proofs',
        ])->findOrFail($id);
    }

    // ─── Cancellation ──────────────────────────────────────────────────────────

    /**
     * Cancel a booking in any cancellable state.
     * Frees calendar slots and cache locks.
     */
    public function cancel(Booking $booking, string $reason, int $actorId, string $portal = 'Admin Portal'): Booking
    {
        $cancellable = ['Temporary Reserved', 'Approval Pending', 'Confirmed'];

        if (! in_array($booking->booking_status, $cancellable)) {
            throw new RuntimeException("Booking in status '{$booking->booking_status}' cannot be cancelled.");
        }

        return DB::transaction(function () use ($booking, $reason, $actorId, $portal): Booking {
            $oldStatus = $booking->booking_status;

            // 1. Release Redis/Cache hold locks
            $start  = Carbon::parse($booking->booking_start_date);
            $end    = Carbon::parse($booking->booking_end_date);
            $period = CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $key = sprintf('inventory_hold:%d:%s', $booking->inventory_id, $date->toDateString());
                Cache::forget($key);
            }

            // 2. Remove/free calendar rows
            BookingCalendar::where('booking_id', $booking->id)->delete();

            // 3. Update status
            $booking->update(['booking_status' => 'Cancelled']);

            // 4. Audit log
            BookingLog::create([
                'booking_id'    => $booking->id,
                'old_status'    => $oldStatus,
                'new_status'    => 'Cancelled',
                'remarks'       => 'Booking cancelled. Reason: ' . $reason,
                'portal_source' => $portal,
                'ip_address'    => request()->ip() ?? '127.0.0.1',
                'created_by'    => $actorId,
            ]);

            return $booking->fresh();
        });
    }

    // ─── Admin Booking Confirmation (post-payment override) ────────────────────

    public function adminConfirm(Booking $booking, int $actorId): Booking
    {
        if (! in_array($booking->booking_status, ['Approval Pending', 'Temporary Reserved'])) {
            throw new RuntimeException("Booking cannot be confirmed from status '{$booking->booking_status}'.");
        }

        return $this->engine->confirmBooking($booking->id, (object) ['id' => $actorId]);
    }
}
