<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Inventory;
use App\Models\Provider;
use App\Models\State;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Booking;
use App\Models\BookingCalendar;
use App\Models\BookingProof;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProviderPayout;
use App\Models\Commission;
use App\Models\Refund;
use App\Models\TaxSetting;
use App\Services\FinanceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Sprint5FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function getAuthHeaderForAgent(): array
    {
        $this->app['auth']->forgetGuards();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@sodars.local',
            'password' => 'password',
            'device_name' => 'agent-test',
        ])->assertOk();

        return ['Authorization' => 'Bearer ' . $login->json('data.access_token')];
    }

    private function setupProviderAndInventory(): array
    {
        $country = Country::first();
        $state = State::first();
        $district = District::first();
        $city = City::first();
        $area = Area::first();

        $provider = Provider::create([
            'provider_code' => 'PROV-5555',
            'company_name' => 'Finance Media Ltd',
            'owner_name' => 'Fin Owner',
            'email' => 'owner@finmedia.com',
            'mobile' => '9876987698',
            'gst_number' => 'GST555',
            'pan_number' => 'PAN555',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'address' => 'Fin Media Office',
            'status' => 'Approved',
            'marketplace_enabled' => true,
        ]);

        $inventory = Inventory::create([
            'provider_id' => $provider->id,
            'inventory_code' => 'INV-5501',
            'title' => 'Flagship Billboard Residency Road',
            'media_type' => 'Hoarding',
            'category' => 'Billboard',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'latitude' => 12.9716,
            'longitude' => 77.5946,
            'width' => 20.0,
            'height' => 10.0,
            'facing_direction' => 'West',
            'lighting_type' => 'Frontlit',
            'traffic_type' => 'High',
            'monthly_price' => 40000.00,
            'weekly_price' => 11000.00,
            'daily_price' => 1800.00,
            'status' => 'Available',
            'marketplace_enabled' => true,
        ]);

        return [$provider, $inventory];
    }

    public function test_invoicing_payment_checkout_and_escrow_compilation(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();

        $headers = $this->getAuthHeaderForAgent();

        $campaign = Campaign::create([
            'campaign_code' => 'CMP-5555',
            'title' => 'Pepsi Campaign 2026',
            'advertiser_name' => 'PepsiCo',
            'customer_name' => 'Pepsi Manager',
            'customer_mobile' => '99998888',
            'customer_email' => 'pepsi@p.com',
            'budget' => 100000,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'status' => 'Draft',
            'created_by' => User::first()->id,
        ]);

        // 1. Create Hold Reservation to simulate checkout start
        $holdRes = $this->postJson('/api/bookings/hold', [
            'campaign_id' => $campaign->id,
            'inventory_id' => $inventory->id,
            'booking_start_date' => '2026-09-01',
            'booking_end_date' => '2026-09-05',
            'booking_source' => 'Admin',
        ], $headers)
            ->assertStatus(211);

        $bookingId = $holdRes->json('data.booking.id');

        $engine = app(FinanceEngine::class);
        $booking = Booking::findOrFail($bookingId);

        // 2. Generate Invoice
        $invoice = $engine->generateInvoice($booking);
        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking->id,
            'campaign_id' => $booking->campaign_id,
            'payment_status' => 'Pending',
        ]);

        $this->getJson('/api/finance/invoices/' . $invoice->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.invoice.payment_status', 'Pending');

        // 3. Process Checkout Payment Simulation
        $checkoutRes = $this->postJson('/api/finance/invoices/' . $invoice->id . '/pay', [
            'payment_mode' => 'Gateway',
            'transaction_id' => 'TXN-55555-ABC',
        ], $headers)
            ->assertStatus(211);

        // Verify status moved to Paid/Confirmed
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'payment_status' => 'Paid',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);

        // Check availability calendar slots transitioned to Booked
        $this->assertDatabaseHas('booking_calendar', [
            'booking_id' => $booking->id,
            'date' => '2026-09-03 00:00:00',
            'status' => 'Booked',
        ]);

        // 4. Assert Payout and Agent Commission are compiled as Pending
        $this->assertDatabaseHas('provider_payouts', [
            'booking_id' => $booking->id,
            'payment_status' => 'Pending',
        ]);

        $this->assertDatabaseHas('commissions', [
            'booking_id' => $booking->id,
            'status' => 'Pending',
        ]);

        // Verify escrow reports list payout/commission
        $this->getJson('/api/finance/payouts', $headers)
            ->assertOk()
            ->assertJsonPath('data.payouts.data.0.payment_status', 'Pending');

        $this->getJson('/api/finance/commissions', $headers)
            ->assertOk()
            ->assertJsonPath('data.commissions.data.0.status', 'Pending');
    }

    public function test_proof_gated_escrow_settlement_releases(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();

        $headers = $this->getAuthHeaderForAgent();

        $campaign = Campaign::create([
            'campaign_code' => 'CMP-5556',
            'title' => 'Pepsi Campaign 2',
            'advertiser_name' => 'PepsiCo',
            'customer_name' => 'Pepsi Manager',
            'customer_mobile' => '99998888',
            'customer_email' => 'pepsi@p.com',
            'budget' => 100000,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-15',
            'status' => 'Draft',
            'created_by' => User::first()->id,
        ]);

        $booking = Booking::create([
            'booking_code' => 'BKG-5556',
            'campaign_id' => $campaign->id,
            'provider_id' => $provider->id,
            'inventory_id' => $inventory->id,
            'booking_type' => 'Static',
            'booking_source' => 'Admin',
            'booking_start_date' => '2026-09-10',
            'booking_end_date' => '2026-09-15',
            'total_days' => 5,
            'price' => 20000,
            'gst_percentage' => 18,
            'gst_amount' => 3600,
            'total_amount' => 23600,
            'provider_amount' => 18000,
            'commission_amount' => 2000,
            'booking_status' => 'Confirmed',
            'payment_status' => 'Paid',
            'created_by' => User::first()->id,
        ]);

        $engine = app(FinanceEngine::class);

        // Pre-compile Payout & Commission
        ProviderPayout::create([
            'provider_id' => $provider->id,
            'booking_id' => $booking->id,
            'amount' => 18000,
            'gst_deduction' => 3240,
            'tds_amount' => 360,
            'final_amount' => 17640,
            'payment_status' => 'Pending',
        ]);

        Commission::create([
            'agent_id' => User::first()->id,
            'booking_id' => $booking->id,
            'commission_percentage' => 10,
            'commission_amount' => 2000,
            'status' => 'Pending',
        ]);

        // Try manually release escrow without uploads -> stays Pending
        $this->postJson('/api/bookings/' . $booking->id . '/verify-settlements', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.booking.payout.payment_status', 'Pending')
            ->assertJsonPath('data.booking.commission.status', 'Pending');

        // 1. Upload proof 1: Mounting Photo
        $file1 = UploadedFile::fake()->create('mounting.jpg', 100);
        $this->postJson('/api/bookings/' . $booking->id . '/proofs', [
            'proof_type' => 'Mounting Photo',
            'proof_file' => $file1,
            'remarks' => 'Billboard mounted successfully.',
        ], $headers)
            ->assertStatus(211)
            ->assertJsonPath('data.booking.payout.payment_status', 'Pending'); // Still pending, night photo missing

        // 2. Upload proof 2: Night Illumination
        $file2 = UploadedFile::fake()->create('night.jpg', 100);
        $this->postJson('/api/bookings/' . $booking->id . '/proofs', [
            'proof_type' => 'Night Illumination',
            'proof_file' => $file2,
            'remarks' => 'Night lights are completely active.',
        ], $headers)
            ->assertStatus(211)
            ->assertJsonPath('data.booking.payout.payment_status', 'Paid') // Both present -> released!
            ->assertJsonPath('data.booking.commission.status', 'Approved');

        // Verify databases status updated
        $this->assertDatabaseHas('provider_payouts', [
            'booking_id' => $booking->id,
            'payment_status' => 'Paid',
        ]);

        $this->assertDatabaseHas('commissions', [
            'booking_id' => $booking->id,
            'status' => 'Approved',
        ]);
    }

    public function test_refunds_and_ledger_reversals(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();

        $campaign = Campaign::create([
            'campaign_code' => 'CMP-5557',
            'title' => 'Pepsi Campaign 3',
            'advertiser_name' => 'PepsiCo',
            'customer_name' => 'Pepsi Manager',
            'customer_mobile' => '99998888',
            'customer_email' => 'pepsi@p.com',
            'budget' => 100000,
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-25',
            'status' => 'Draft',
            'created_by' => User::first()->id,
        ]);

        $booking = Booking::create([
            'booking_code' => 'BKG-5557',
            'campaign_id' => $campaign->id,
            'provider_id' => $provider->id,
            'inventory_id' => $inventory->id,
            'booking_type' => 'Static',
            'booking_source' => 'Admin',
            'booking_start_date' => '2026-09-20',
            'booking_end_date' => '2026-09-25',
            'total_days' => 5,
            'price' => 20000,
            'gst_percentage' => 18,
            'gst_amount' => 3600,
            'total_amount' => 23600,
            'provider_amount' => 18000,
            'commission_amount' => 2000,
            'booking_status' => 'Confirmed',
            'payment_status' => 'Paid',
            'created_by' => User::first()->id,
        ]);

        BookingCalendar::create([
            'inventory_id' => $inventory->id,
            'booking_id' => $booking->id,
            'date' => '2026-09-22',
            'status' => 'Booked',
        ]);

        $engine = app(FinanceEngine::class);

        // Process refund
        $engine->triggerRefund($booking, 23600.00, 'Campaign cancelled by customer.');

        // Verify booking status cancelled & payment_status refunded
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => 'Cancelled',
            'payment_status' => 'Refunded',
        ]);

        // Verify refund recorded
        $this->assertDatabaseHas('refunds', [
            'booking_id' => $booking->id,
            'refund_amount' => 23600.00,
            'refund_status' => 'Approved',
        ]);

        // Check slots returned back to Available
        $this->assertDatabaseHas('booking_calendar', [
            'booking_id' => $booking->id,
            'date' => '2026-09-22 00:00:00',
            'status' => 'Available',
        ]);
    }
}
