<?php

namespace App\Services;

use App\Models\BookingCalendar;
use App\Models\InventoryMaintenance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AvailabilityEngine
{
    public function check(int $inventoryId, string $startDate, string $endDate): bool
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($start->gt($end)) {
            return false;
        }

        // 1. Check database booking calendar for non-available days
        $hasCalendarConflict = BookingCalendar::where('inventory_id', $inventoryId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'Available')
            ->exists();

        if ($hasCalendarConflict) {
            return false;
        }

        // 2. Check database inventory maintenance periods
        $hasMaintenanceConflict = InventoryMaintenance::where('inventory_id', $inventoryId)
            ->where('status', 'Active')
            ->where(function ($query) use ($start, $end): void {
                $query->where('start_date', '<=', $end->toDateString())
                      ->where('end_date', '>=', $start->toDateString());
            })
            ->exists();

        if ($hasMaintenanceConflict) {
            return false;
        }

        // 3. Check hold locks for each date in the period
        $period = CarbonPeriod::create($start, $end);
        foreach ($period as $date) {
            $key = sprintf('inventory_hold:%d:%s', $inventoryId, $date->toDateString());
            if (\Illuminate\Support\Facades\Cache::has($key)) {
                return false;
            }
        }

        return true;
    }
}
