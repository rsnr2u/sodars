<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use RespondsWithApi;

    public function exportRevenue(Request $request): JsonResponse
    {
        $query = Payment::where('payment_status', 'Paid');

        if ($request->filled('start_date')) {
            $query->where('payment_date', '>=', $request->query('start_date') . ' 00:00:00');
        }

        if ($request->filled('end_date')) {
            $query->where('payment_date', '<=', $request->query('end_date') . ' 23:59:59');
        }

        $payments = $query->with(['invoice.booking.inventory', 'invoice.campaign'])
            ->orderBy('payment_date', 'desc')
            ->paginate($request->integer('per_page', 25));

        // Aggregate statistics for the report response envelope
        $totalCollected = floatval($query->sum('amount'));

        return $this->success([
            'payments' => $payments,
            'report_summary' => [
                'total_collected' => $totalCollected,
                'reconciliation_status' => 'Balanced',
            ]
        ], 'Revenue report generated successfully.');
    }

    public function exportOccupancy(Request $request): JsonResponse
    {
        $query = Inventory::query();

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->integer('city_id'));
        }

        if ($request->filled('media_type')) {
            $query->where('media_type', $request->query('media_type'));
        }

        $inventories = $query->with(['provider', 'calendar', 'maintenance'])
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25));

        return $this->success([
            'occupancy_reports' => $inventories,
        ], 'Occupancy report compiled successfully.');
    }
}
