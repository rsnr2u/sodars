<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingEngine;
use Illuminate\Console\Command;

class ExpireHolds extends Command
{
    protected $signature = 'sodars:expire-holds';

    protected $description = 'Release expired 30-minute temporary booking hold locks and calendar slots';

    public function handle(BookingEngine $engine): int
    {
        $expiredBookings = Booking::where('booking_status', 'Temporary Reserved')
            ->where('reservation_expires_at', '<=', now())
            ->get();

        if ($expiredBookings->isEmpty()) {
            $this->info('No expired bookings to release.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expiredBookings as $booking) {
            try {
                $engine->expireHold($booking->id);
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to expire booking hold ID {$booking->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully released {$count} expired booking hold(s).");
        return Command::SUCCESS;
    }
}
