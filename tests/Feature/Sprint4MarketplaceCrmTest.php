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
use App\Models\BookingCalendar;
use App\Models\InventoryMaintenance;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\MarketplaceInquiry;
use App\Models\MarketplaceSearchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Sprint4MarketplaceCrmTest extends TestCase
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
            'provider_code' => 'PROV-4444',
            'company_name' => 'AdSpace Outdoor Ltd',
            'owner_name' => 'Vendor Owner',
            'email' => 'owner@adspace.com',
            'mobile' => '9900990099',
            'gst_number' => 'GST444',
            'pan_number' => 'PAN444',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'address' => 'AdSpace Office',
            'status' => 'Approved',
            'marketplace_enabled' => true,
        ]);

        $inventory = Inventory::create([
            'provider_id' => $provider->id,
            'inventory_code' => 'INV-4401',
            'title' => 'Prime Hoarding Banjara Circle',
            'media_type' => 'Hoarding',
            'category' => 'Billboard',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'latitude' => 17.41262700,
            'longitude' => 78.44828900,
            'width' => 25.0,
            'height' => 12.0,
            'facing_direction' => 'North',
            'lighting_type' => 'Backlit',
            'traffic_type' => 'Very High',
            'traffic_score' => 85.50,
            'monthly_price' => 50000.00,
            'weekly_price' => 15000.00,
            'daily_price' => 2500.00,
            'status' => 'Available',
            'marketplace_enabled' => true,
            'featured' => true,
        ]);

        return [$provider, $inventory];
    }

    public function test_public_marketplace_listing_details_featured_providers_cities(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();

        // 1. Test search listings listing
        $resListings = $this->getJson('/api/marketplace/inventory')
            ->assertOk()
            ->assertJsonPath('data.listings.data.0.title', 'Prime Hoarding Banjara Circle');

        $this->assertCount(1, $resListings->json('data.listings.data'));

        // 2. Test show detail listing
        $this->getJson('/api/marketplace/inventory/' . $inventory->id)
            ->assertOk()
            ->assertJsonPath('data.listing.title', 'Prime Hoarding Banjara Circle')
            ->assertJsonPath('data.listing.provider.company_name', 'AdSpace Outdoor Ltd');

        // 3. Test featured listings
        $this->getJson('/api/marketplace/featured')
            ->assertOk()
            ->assertJsonPath('data.listings.0.title', 'Prime Hoarding Banjara Circle');

        // 4. Test providers directory
        $this->getJson('/api/marketplace/providers')
            ->assertOk()
            ->assertJsonPath('data.providers.data.0.company_name', 'AdSpace Outdoor Ltd');

        // 5. Test active cities summary
        $this->getJson('/api/marketplace/cities')
            ->assertOk()
            ->assertJsonPath('data.cities.0.name', City::first()->name)
            ->assertJsonPath('data.cities.0.inventory_count', 1);
    }

    public function test_marketplace_search_filters_and_logs(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();

        $city = City::first();

        // Test filtering by location cascade & specs
        $this->getJson('/api/marketplace/inventory?' . http_build_query([
            'city_id' => $city->id,
            'media_type' => 'Hoarding',
            'price_max' => 60000.00,
            'traffic_score_min' => 80.00,
            'search_keyword' => 'Banjara',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.listings.data');

        // Assert search telemetry is logged
        $this->assertDatabaseHas('marketplace_search_logs', [
            'search_keyword' => 'Banjara',
            'city_id' => $city->id,
            'media_type' => 'Hoarding',
        ]);
    }

    public function test_marketplace_search_excludes_by_availability_and_maintenance(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();

        // 1. Exclude: Active Booking Conflict in Calendar
        BookingCalendar::create([
            'inventory_id' => $inventory->id,
            'date' => '2026-08-15',
            'status' => 'Booked',
        ]);

        $this->getJson('/api/marketplace/inventory?' . http_build_query([
            'availability_start' => '2026-08-10',
            'availability_end' => '2026-08-20',
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data.listings.data');

        BookingCalendar::query()->delete();

        // 2. Exclude: Active Maintenance window
        InventoryMaintenance::create([
            'inventory_id' => $inventory->id,
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-16',
            'status' => 'Active',
        ]);

        $this->getJson('/api/marketplace/inventory?' . http_build_query([
            'availability_start' => '2026-08-10',
            'availability_end' => '2026-08-20',
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data.listings.data');
    }

    public function test_marketplace_nearby_geoproximity_radius_lookup(): void
    {
        $this->seed();
        $country = Country::first();
        $state = State::first();
        $district = District::first();
        $city = City::first();
        $area = Area::first();

        $provider = Provider::create([
            'provider_code' => 'PROV-4445',
            'company_name' => 'GeoSpaces Ltd',
            'owner_name' => 'Owner',
            'email' => 'owner@geo.com',
            'mobile' => '9900112233',
            'gst_number' => 'GST445',
            'pan_number' => 'PAN445',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'address' => 'Geo Office',
            'status' => 'Approved',
            'marketplace_enabled' => true,
        ]);

        // Center search point is: 17.412627, 78.448289

        // 1. Item within 1km (17.412000, 78.448000)
        $itemClose = Inventory::create([
            'provider_id' => $provider->id,
            'inventory_code' => 'INV-CLOSE',
            'title' => 'Close Billboard',
            'media_type' => 'Hoarding',
            'category' => 'Billboard',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'latitude' => 17.412000,
            'longitude' => 78.448000,
            'width' => 20.0,
            'height' => 10.0,
            'facing_direction' => 'East',
            'lighting_type' => 'Lit',
            'traffic_type' => 'High',
            'monthly_price' => 30000.00,
            'weekly_price' => 9000.00,
            'daily_price' => 1500.00,
            'status' => 'Available',
            'marketplace_enabled' => true,
        ]);

        // 2. Item far away (~100km away at 18.500000, 79.500000)
        $itemFar = Inventory::create([
            'provider_id' => $provider->id,
            'inventory_code' => 'INV-FAR',
            'title' => 'Far Billboard',
            'media_type' => 'Hoarding',
            'category' => 'Billboard',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'latitude' => 18.500000,
            'longitude' => 79.500000,
            'width' => 20.0,
            'height' => 10.0,
            'facing_direction' => 'East',
            'lighting_type' => 'Lit',
            'traffic_type' => 'High',
            'monthly_price' => 30000.00,
            'weekly_price' => 9000.00,
            'daily_price' => 1500.00,
            'status' => 'Available',
            'marketplace_enabled' => true,
        ]);

        // Query radius 5km from center
        $res = $this->getJson('/api/marketplace/inventory?' . http_build_query([
            'latitude' => 17.412627,
            'longitude' => 78.448289,
            'radius_km' => 5,
        ]))
            ->assertOk();

        // Verify Close item is returned, Far item is excluded
        $titles = collect($res->json('data.listings.data'))->pluck('title');
        $this->assertTrue($titles->contains('Close Billboard'));
        $this->assertFalse($titles->contains('Far Billboard'));
    }

    public function test_marketplace_inquiry_creation_and_auto_lead_crm_routing(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();

        $inquiryPayload = [
            'inventory_id' => $inventory->id,
            'name' => 'John Advertiser',
            'company_name' => 'Soda Pop Corp',
            'mobile' => '9988776655',
            'email' => 'john@sodapop.com',
            'message' => 'Interested in booking this circle billboard for September.',
        ];

        // 1. Submit Inquiry
        $this->postJson('/api/marketplace/inquiries', $inquiryPayload)
            ->assertStatus(211)
            ->assertJsonPath('data.inquiry.name', 'John Advertiser')
            ->assertJsonPath('data.lead.name', 'John Advertiser');

        // 2. Assert Inquiries Database records exist
        $this->assertDatabaseHas('marketplace_inquiries', [
            'inventory_id' => $inventory->id,
            'name' => 'John Advertiser',
            'company_name' => 'Soda Pop Corp',
        ]);

        // 3. Assert CRM Lead routing works with correct matching fields
        $this->assertDatabaseHas('leads', [
            'name' => 'John Advertiser',
            'company_name' => 'Soda Pop Corp',
            'mobile' => '9988776655',
            'email' => 'john@sodapop.com',
            'city_id' => $inventory->city_id, // Geolocation mapped automatically
            'lead_source' => 'Marketplace Inquiry',
            'status' => 'New',
        ]);
    }

    public function test_crm_leads_operations_and_agent_followups(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAgent();

        $city = City::first();

        // 1. Manual Lead Registration
        $res = $this->postJson('/api/crm/leads', [
            'name' => 'Ravi Retailer',
            'company_name' => 'Ravi Garments',
            'mobile' => '9876543210',
            'email' => 'ravi@retailer.com',
            'city_id' => $city->id,
            'remarks' => 'Looking for seasonal bus shelters hoarding plans.',
        ], $headers)
            ->assertStatus(211)
            ->assertJsonPath('data.lead.name', 'Ravi Retailer');

        $leadId = $res->json('data.lead.id');

        // 2. Fetch/List Leads
        $this->getJson('/api/crm/leads', $headers)
            ->assertOk()
            ->assertJsonPath('data.leads.data.0.name', 'Ravi Retailer');

        // 3. Detailed View
        $this->getJson('/api/crm/leads/' . $leadId, $headers)
            ->assertOk()
            ->assertJsonPath('data.lead.company_name', 'Ravi Garments');

        // 4. Update Status
        $this->putJson('/api/crm/leads/' . $leadId, [
            'status' => 'Contacted',
            'remarks' => 'Contacted on mobile, positive initial response.',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.lead.status', 'Contacted');

        // 5. Lead Assignment
        $agent = User::first(); // First seeded user is Super Admin/Agent
        $this->putJson("/api/crm/leads/{$leadId}/assign", [
            'agent_id' => $agent->id,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.lead.agent_id', $agent->id);

        // 6. Log Follow-up record
        $this->postJson("/api/crm/leads/{$leadId}/followups", [
            'followup_date' => '2026-09-01 10:00:00',
            'remarks' => 'Scheduled meeting at clothing showroom.',
            'status' => 'Pending',
        ], $headers)
            ->assertStatus(211)
            ->assertJsonPath('data.followup.remarks', 'Scheduled meeting at clothing showroom.');

        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $leadId,
            'remarks' => 'Scheduled meeting at clothing showroom.',
            'status' => 'Pending',
        ]);
    }
}
