<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingArtwork;
use App\Models\BookingCalendar;
use App\Models\BookingLog;
use App\Models\Campaign;
use App\Models\ProviderStaff;
use App\Services\BookingEngine;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    use RespondsWithApi;

    protected BookingEngine $engine;

    public function __construct(BookingEngine $engine)
    {
        $this->engine = $engine;
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'inventory_id' => ['required', 'integer', 'exists:inventory,id'],
            'booking_start_date' => ['required', 'date'],
            'booking_end_date' => ['required', 'date', 'after_or_equal:booking_start_date'],
            'booking_source' => ['required', Rule::in(['Website', 'Admin', 'Agent', 'Business Portal', 'API'])],
        ]);

        $campaign = Campaign::where('created_by', $request->user()->id)->findOrFail($payload['campaign_id']);

        try {
            $booking = $this->engine->createHold(
                $campaign,
                $payload['inventory_id'],
                $payload['booking_start_date'],
                $payload['booking_end_date'],
                $payload['booking_source'],
                $request->user()
            );

            return $this->success(['booking' => $booking], 'Inventory temporary hold created successfully.', 211);
        } catch (Exception $e) {
            return $this->failure($e->getMessage(), ['conflict' => 'Selected dates or inventory are not available.'], 422);
        }
    }

    public function providerApprove(Request $request, int $id): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        abort_unless($staff instanceof ProviderStaff && $staff->provider_id, 403, 'Only provider staff can perform this action.');

        $booking = Booking::where('provider_id', $staff->provider_id)->findOrFail($id);

        DB::transaction(function () use ($booking, $staff): void {
            $booking->update([
                'approved_by_provider' => true,
                'approved_at' => now(),
                'booking_status' => 'Approval Pending', // Awaits admin validation or payment release
            ]);

            BookingCalendar::where('booking_id', $booking->id)->update([
                'status' => 'Reserved',
            ]);

            BookingLog::create([
                'booking_id' => $booking->id,
                'old_status' => 'Temporary Reserved',
                'new_status' => 'Approval Pending',
                'remarks' => 'Provider approved the booking hold. final payment release pending.',
                'portal_source' => 'Business Portal',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_by' => $staff->id,
            ]);
        });

        return $this->success(['booking' => $booking], 'Booking hold approved successfully.');
    }

    public function providerReject(Request $request, int $id): JsonResponse
    {
        /** @var ProviderStaff $staff */
        $staff = $request->user();
        abort_unless($staff instanceof ProviderStaff && $staff->provider_id, 403, 'Only provider staff can perform this action.');

        $booking = Booking::where('provider_id', $staff->provider_id)->findOrFail($id);

        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($booking, $staff, $payload): void {
            // 1. Release Redis locks
            $start = Carbon::parse($booking->booking_start_date);
            $end = Carbon::parse($booking->booking_end_date);
            $period = CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $key = sprintf('inventory_hold:%d:%s', $booking->inventory_id, $date->toDateString());
                Redis::del($key);
            }

            // 2. Remove calendar blocks
            BookingCalendar::where('booking_id', $booking->id)->delete();

            // 3. Update status
            $booking->update([
                'booking_status' => 'Rejected',
            ]);

            // 4. Log
            BookingLog::create([
                'booking_id' => $booking->id,
                'old_status' => 'Temporary Reserved',
                'new_status' => 'Rejected',
                'remarks' => 'Rejected by provider. Reason: ' . $payload['reason'],
                'portal_source' => 'Business Portal',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_by' => $staff->id,
            ]);
        });

        return $this->success(['booking' => $booking], 'Booking hold rejected successfully.');
    }

    public function uploadArtwork(Request $request, int $id): JsonResponse
    {
        $booking = Booking::where('created_by', $request->user()->id)->findOrFail($id);

        $payload = $request->validate([
            'artwork_file' => ['required', 'file', 'max:10240'], // max 10MB
        ]);

        $path = $request->file('artwork_file')->store('artworks', 'local');

        $artwork = BookingArtwork::create([
            'booking_id' => $booking->id,
            'artwork_file' => $path,
            'artwork_status' => 'Pending',
            'uploaded_by' => $request->user()->id,
        ]);

        return $this->success(['artwork' => $artwork], 'Artwork uploaded successfully.');
    }

    public function approveArtwork(Request $request, int $id, int $artId): JsonResponse
    {
        abort_unless(
            $request->user() && $request->user()->hasAnyRole(['Super Admin', 'Branch Manager']),
            403,
            'Only admins can approve artworks.'
        );

        $artwork = BookingArtwork::where('booking_id', $id)->findOrFail($artId);

        $payload = $request->validate([
            'status' => ['required', Rule::in(['Approved', 'Rejected'])],
        ]);

        DB::transaction(function () use ($id, $artwork, $payload, $request): void {
            $artwork->update([
                'artwork_status' => $payload['status'],
            ]);

            if ($payload['status'] === 'Approved') {
                $booking = Booking::findOrFail($id);
                $booking->update([
                    'booking_status' => 'Active',
                ]);

                BookingLog::create([
                    'booking_id' => $id,
                    'old_status' => $booking->booking_status,
                    'new_status' => 'Active',
                    'remarks' => 'Campaign artwork approved by Admin. Booking active.',
                    'portal_source' => 'Admin Portal',
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return $this->success(['artwork' => $artwork], 'Artwork verification status updated.');
    }

    public function logs(Request $request, int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        
        // Scope checks
        if ($request->user() instanceof ProviderStaff) {
            abort_unless($booking->provider_id === $request->user()->provider_id, 403);
        } else {
            // Standard User
            if (! $request->user()->hasAnyRole(['Super Admin', 'Branch Manager'])) {
                abort_unless($booking->created_by === $request->user()->id, 403);
            }
        }

        return $this->success([
            'logs' => BookingLog::where('booking_id', $id)->orderBy('id', 'desc')->get(),
        ], 'Booking transition logs fetched.');
    }
}
