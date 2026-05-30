<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Inventory;
use App\Models\Provider;
use App\Models\ProviderStaff;
use App\Models\State;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Booking;
use App\Models\BookingCalendar;
use App\Models\InventoryMaintenance;
use App\Services\AvailabilityEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Sprint3CampaignBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Flush Cache between tests to ensure isolation
        Cache::flush();
    }

    private function getAuthHeaderForAgent(): array
    {
        $this->app['auth']->forgetGuards();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@sodars.local', // Standard Super Admin handles agent actions here too
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
            'provider_code' => 'PROV-3333',
            'company_name' => 'City Lights Ltd',
            'owner_name' => 'Owner Name',
            'email' => 'owner@citylights.com',
            'mobile' => '9988776655',
            'gst_number' => 'GST333',
            'pan_number' => 'PAN333',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'address' => 'City Lights Office',
            'status' => 'Approved',
            'marketplace_enabled' => true,
        ]);

        $staff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'name' => 'Owner Name',
            'email' => 'owner@citylights.com',
            'mobile' => '9988776655',
            'role' => 'Owner',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);

        $inventory = Inventory::create([
            'provider_id' => $provider->id,
            'inventory_code' => 'INV-3333',
            'title' => 'Flagship Billboard Banjara Hills',
            'media_type' => 'Hoarding',
            'category' => 'Billboard',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'latitude' => 17.41262700,
            'longitude' => 78.44828900,
            'width' => 20.0,
            'height' => 10.0,
            'facing_direction' => 'East',
            'lighting_type' => 'Frontlit',
            'traffic_type' => 'High',
            'monthly_price' => 45000.00,
            'weekly_price' => 12000.00,
            'daily_price' => 2000.00,
            'status' => 'Available',
            'marketplace_enabled' => true,
        ]);

        return [$provider, $staff, $inventory];
    }

    public function test_campaign_crud_endpoints(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAgent();

        $country = Country::first();
        $state = State::first();
        $district = District::first();
        $city = City::first();

        // 1. Create Campaign
        $campaignPayload = [
            'title' => 'Pepsi Summer Launch 2026',
            'advertiser_name' => 'PepsiCo India',
            'customer_name' => 'Pepsi Brand Manager',
            'customer_mobile' => '9100000000',
            'customer_email' => 'pepsi@brand.com',
            'budget' => 500000.00,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'notes' => 'Primary hoardings campaign.',
            'locations' => [
                [
                    'country_id' => $country->id,
                    'state_id' => $state->id,
                    'district_id' => $district->id,
                    'city_id' => $city->id,
                    'area_id' => null,
                ]
            ],
        ];

        $res = $this->postJson('/api/campaigns', $campaignPayload, $headers)
            ->assertStatus(211)
            ->assertJsonPath('data.campaign.title', 'Pepsi Summer Launch 2026');

        $campaignId = $res->json('data.campaign.id');
        $this->assertNotNull($res->json('data.campaign.campaign_code'));

        // 2. Index Campaigns
        $this->getJson('/api/campaigns', $headers)
            ->assertOk()
            ->assertJsonPath('data.campaigns.data.0.title', 'Pepsi Summer Launch 2026');

        // 3. Show Details
        $this->getJson('/api/campaigns/' . $campaignId, $headers)
            ->assertOk()
            ->assertJsonPath('data.campaign.title', 'Pepsi Summer Launch 2026');

        // 4. Update
        $this->putJson('/api/campaigns/' . $campaignId, [
            'title' => 'Pepsi Summer Smash 2026',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.campaign.title', 'Pepsi Summer Smash 2026');

        // 5. Delete
        $this->deleteJson('/api/campaigns/' . $campaignId, [], $headers)
            ->assertOk();

        $this->getJson('/api/campaigns/' . $campaignId, $headers)
            ->assertStatus(404);
    }

    public function test_availability_engine_detects_conflicts(): void
    {
        $this->seed();
        [$provider, $staff, $inventory] = $this->setupProviderAndInventory();

        $engine = app(AvailabilityEngine::class);

        // 1. Available initially
        $this->assertTrue($engine->check($inventory->id, '2026-07-01', '2026-07-05'));

        // 2. Conflict: Active Hold Lock
        Cache::put("inventory_hold:{$inventory->id}:2026-07-02", '1', 100);
        $this->assertFalse($engine->check($inventory->id, '2026-07-01', '2026-07-05'));
        Cache::flush();

        // 3. Conflict: Active Booking Calendar Block
        BookingCalendar::create([
            'inventory_id' => $inventory->id,
            'date' => '2026-07-03',
            'status' => 'Booked',
        ]);
        $this->assertFalse($engine->check($inventory->id, '2026-07-01', '2026-07-05'));
        BookingCalendar::query()->delete();

        // 4. Conflict: Active Inventory Maintenance Period
        InventoryMaintenance::create([
            'inventory_id' => $inventory->id,
            'start_date' => '2026-07-04',
            'end_date' => '2026-07-10',
            'status' => 'Active',
        ]);
        $this->assertFalse($engine->check($inventory->id, '2026-07-01', '2026-07-05'));
    }

    public function test_booking_holds_concurrency_and_expiry_command(): void
    {
        $this->seed();
        [$provider, $staff, $inventory] = $this->setupProviderAndInventory();

        // Create Campaign
        $headers = $this->getAuthHeaderForAgent();
        $campaign = Campaign::create([
            'campaign_code' => 'CMP-9999',
            'title' => 'Campaign Title',
            'advertiser_name' => 'Adv',
            'customer_name' => 'Cust',
            'customer_mobile' => '999',
            'customer_email' => 'c@c.com',
            'budget' => 100000,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'status' => 'Draft',
            'created_by' => User::first()->id,
        ]);

        // 1. Request hold lock
        $holdRes = $this->postJson('/api/bookings/hold', [
            'campaign_id' => $campaign->id,
            'inventory_id' => $inventory->id,
            'booking_start_date' => '2026-07-01',
            'booking_end_date' => '2026-07-05',
            'booking_source' => 'Admin',
        ], $headers)
            ->assertStatus(211)
            ->assertJsonPath('data.booking.booking_status', 'Temporary Reserved');

        $bookingId = $holdRes->json('data.booking.id');

        // Verify lock exists
        $this->assertTrue(Cache::has("inventory_hold:{$inventory->id}:2026-07-03"));

        // 2. Concurrent/parallel request on same dates fails
        $this->postJson('/api/bookings/hold', [
            'campaign_id' => $campaign->id,
            'inventory_id' => $inventory->id,
            'booking_start_date' => '2026-07-02',
            'booking_end_date' => '2026-07-03',
            'booking_source' => 'Admin',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // 3. Expiration command handles expired holds
        // Force reservation expires to be in the past
        Booking::where('id', $bookingId)->update([
            'reservation_expires_at' => now()->subMinutes(5),
        ]);

        Artisan::call('sodars:expire-holds');

        // Check locks released & booking expired
        $this->assertFalse(Cache::has("inventory_hold:{$inventory->id}:2026-07-03"));
        $this->getJson('/api/bookings/' . $bookingId . '/logs', $headers)
            ->assertOk()
            ->assertJsonPath('data.logs.0.new_status', 'Expired');
    }

    public function test_booking_provider_approval_rejection_and_artwork_activations(): void
    {
        $this->seed();
        [$provider, $staff, $inventory] = $this->setupProviderAndInventory();

        $headers = $this->getAuthHeaderForAgent();
        $campaign = Campaign::create([
            'campaign_code' => 'CMP-8888',
            'title' => 'Campaign Title',
            'advertiser_name' => 'Adv',
            'customer_name' => 'Cust',
            'customer_mobile' => '999',
            'customer_email' => 'c@c.com',
            'budget' => 100000,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'status' => 'Draft',
            'created_by' => User::first()->id,
        ]);

        $holdRes = $this->postJson('/api/bookings/hold', [
            'campaign_id' => $campaign->id,
            'inventory_id' => $inventory->id,
            'booking_start_date' => '2026-07-01',
            'booking_end_date' => '2026-07-05',
            'booking_source' => 'Admin',
        ], $headers)->assertStatus(211);

        $bookingId = $holdRes->json('data.booking.id');

        // 1. Provider Approves hold
        $this->app['auth']->forgetGuards();
        $token = $staff->createToken('staff-token')->plainTextToken;
        $staffHeaders = ['Authorization' => 'Bearer ' . $token];

        $this->postJson('/api/provider/bookings/' . $bookingId . '/provider-approve', [], $staffHeaders)
            ->assertOk()
            ->assertJsonPath('data.booking.booking_status', 'Approval Pending')
            ->assertJsonPath('data.booking.approved_by_provider', true);

        // Verify calendar status changed to Reserved
        $this->assertEquals('Reserved', BookingCalendar::where('booking_id', $bookingId)->first()->status);

        // 2. Upload Artwork
        $this->app['auth']->forgetGuards();
        $file = UploadedFile::fake()->create('artwork.jpg', 1200);
        $artRes = $this->postJson('/api/bookings/' . $bookingId . '/artwork', [
            'artwork_file' => $file,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.artwork.artwork_status', 'Pending');

        $artId = $artRes->json('data.artwork.id');

        // 3. Admin Approves Artwork -> Moves booking to Active!
        $this->postJson('/api/bookings/' . $bookingId . '/artwork/' . $artId . '/approve', [
            'status' => 'Approved',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.artwork.artwork_status', 'Approved');

        $this->getJson('/api/bookings/' . $bookingId . '/logs', $headers)
            ->assertOk()
            ->assertJsonPath('data.logs.0.new_status', 'Active');
    }
}
