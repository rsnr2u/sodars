<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCalendar;
use App\Models\BookingLog;
use App\Models\Campaign;
use App\Models\Inventory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class BookingEngine
{
    protected AvailabilityEngine $availability;

    public function __construct(AvailabilityEngine $availability)
    {
        $this->availability = $availability;
    }

    public function createHold(Campaign $campaign, int $inventoryId, string $startDate, string $endDate, string $source, $user): Booking
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // 1. Double check availability
        if (! $this->availability->check($inventoryId, $startDate, $endDate)) {
            throw new RuntimeException('Selected dates or inventory are not available.');
        }

        return DB::transaction(function () use ($campaign, $inventoryId, $start, $end, $source, $user) {
            $inventory = Inventory::findOrFail($inventoryId);

            // 2. Set Hold Locks
            $period = CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                $key = sprintf('inventory_hold:%d:%s', $inventoryId, $date->toDateString());
                Cache::put($key, '1', 1800); // 30-minute Hold Lock
            }

            // 3. Generate Booking Code
            $yearMonthDay = now()->format('Ymd');
            $count = Booking::whereDate('created_at', now()->toDateString())->count() + 1;
            $bookingCode = sprintf('BKG-%s-%04d', $yearMonthDay, $count);

            // 4. Snapshots & Calculations
            $totalDays = $start->diffInDays($end) + 1;
            $price = $inventory->daily_price * $totalDays;
            
            // Assume 18% GST default, 10% Platform Commission
            $gstPercentage = 18.00;
            $gstAmount = ($price * $gstPercentage) / 100.00;
            $totalAmount = $price + $gstAmount;
            
            $commissionAmount = ($price * 10.00) / 100.00;
            $providerAmount = $price - $commissionAmount;

            // 5. Create Booking
            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'campaign_id' => $campaign->id,
                'provider_id' => $inventory->provider_id,
                'inventory_id' => $inventoryId,
                'booking_type' => $inventory->media_type === 'Digital Screen' ? 'Digital' : 'Static',
                'booking_source' => $source,
                'priority_level' => 'Medium',
                'booking_start_date' => $start->toDateString(),
                'booking_end_date' => $end->toDateString(),
                'total_days' => $totalDays,
                'reservation_expires_at' => now()->addMinutes(30),
                'price' => $price,
                'gst_percentage' => $gstPercentage,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'provider_amount' => $providerAmount,
                'commission_amount' => $commissionAmount,
                'booking_inventory_snapshot' => $inventory->toArray(),
                'booking_pricing_snapshot' => $inventory->pricing->toArray(),
                'booking_status' => 'Temporary Reserved',
                'payment_status' => 'Pending',
                'approved_by_provider' => false,
                'created_by' => $user->id,
            ]);

            // 6. Write Booking Calendar
            foreach ($period as $date) {
                BookingCalendar::create([
                    'inventory_id' => $inventoryId,
                    'booking_id' => $booking->id,
                    'date' => $date->toDateString(),
                    'status' => 'Temporary Reserved',
                ]);
            }

            // 7. Log status change
            BookingLog::create([
                'booking_id' => $booking->id,
                'old_status' => 'None',
                'new_status' => 'Temporary Reserved',
                'remarks' => 'Atomic hold lock and reservation created successfully.',
                'portal_source' => $source,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_by' => $user->id,
            ]);

            return $booking;
        });
    }

    public function confirmBooking(int $bookingId, $user): Booking
    {
        return DB::transaction(function () use ($bookingId, $user) {
            $booking = Booking::findOrFail($bookingId);
            $oldStatus = $booking->booking_status;

            // 1. Release hold locks
            $start = Carbon::parse($booking->booking_start_date);
            $end = Carbon::parse($booking->booking_end_date);
            $period = CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $key = sprintf('inventory_hold:%d:%s', $booking->inventory_id, $date->toDateString());
                Cache::forget($key);
            }

            // 2. Update booking and calendar
            $booking->update([
                'booking_status' => 'Confirmed',
                'payment_status' => 'Paid',
            ]);

            BookingCalendar::where('booking_id', $bookingId)->update([
                'status' => 'Booked',
            ]);

            // 3. Log
            BookingLog::create([
                'booking_id' => $booking->id,
                'old_status' => $oldStatus,
                'new_status' => 'Confirmed',
                'remarks' => 'Booking payment completed, locks released, calendar finalized.',
                'portal_source' => 'System',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_by' => $user->id,
            ]);

            return $booking;
        });
    }

    public function expireHold(int $bookingId): void
    {
        DB::transaction(function () use ($bookingId): void {
            $booking = Booking::findOrFail($bookingId);

            if ($booking->booking_status !== 'Temporary Reserved') {
                return;
            }

            $oldStatus = $booking->booking_status;

            // 1. Release hold locks
            $start = Carbon::parse($booking->booking_start_date);
            $end = Carbon::parse($booking->booking_end_date);
            $period = CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $key = sprintf('inventory_hold:%d:%s', $booking->inventory_id, $date->toDateString());
                Cache::forget($key);
            }

            // 2. Delete booking calendar rows
            BookingCalendar::where('booking_id', $bookingId)->delete();

            // 3. Update status to Expired
            $booking->update([
                'booking_status' => 'Expired',
            ]);

            // 4. Log
            BookingLog::create([
                'booking_id' => $booking->id,
                'old_status' => $oldStatus,
                'new_status' => 'Expired',
                'remarks' => '30-minute hold lock window passed. Reservation expired.',
                'portal_source' => 'System Scheduler',
                'ip_address' => '127.0.0.1',
                'created_by' => $booking->created_by,
            ]);
        });
    }
}
