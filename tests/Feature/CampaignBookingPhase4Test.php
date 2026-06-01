<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestFixtures;
use Tests\TestCase;

class CampaignBookingPhase4Test extends TestCase
{
    use RefreshDatabase, CreatesTestFixtures;

    private User $agent;
    private User $admin;
    private Campaign $campaign;
    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'name'     => 'Agent User',
            'email'    => 'agent@sodars.com',
            'password' => bcrypt('Password@123'),
            'status'   => 'Active',
        ]);

        $this->admin = User::factory()->create([
            'name'     => 'Admin User',
            'email'    => 'admin@sodars.com',
            'password' => bcrypt('Password@123'),
            'status'   => 'Active',
        ]);

        // Build full geo + provider + inventory hierarchy
        $fixtures = $this->buildInventoryFixture();
        $this->inventory = $fixtures['inventory'];

        $this->campaign = Campaign::create([
            'campaign_code'   => 'CMP-TEST-0001',
            'title'           => 'Test Campaign',
            'advertiser_name' => 'Advertiser Co',
            'customer_name'   => 'Customer Name',
            'customer_mobile' => '9000000000',
            'customer_email'  => 'customer@test.com',
            'budget'          => 50000.00,
            'start_date'      => now()->addDays(5)->toDateString(),
            'end_date'        => now()->addDays(35)->toDateString(),
            'status'          => 'Draft',
            'created_by'      => $this->agent->id,
        ]);
    }

    // ─── Campaign CRUD ─────────────────────────────────────────────────────────

    public function test_agent_can_list_own_campaigns(): void
    {
        $response = $this->actingAs($this->agent, 'sanctum')
            ->getJson('/api/campaigns');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['campaigns', 'pagination']]);
    }

    public function test_agent_cannot_see_other_agents_campaigns(): void
    {
        $other = User::factory()->create(['status' => 'Active']);

        $response = $this->actingAs($other, 'sanctum')
            ->getJson('/api/campaigns');

        $response->assertOk();

        $ids = collect($response->json('data.campaigns'))->pluck('id');
        $this->assertNotContains($this->campaign->id, $ids);
    }

    public function test_campaign_creation_requires_at_least_one_location(): void
    {
        $response = $this->actingAs($this->agent, 'sanctum')
            ->postJson('/api/campaigns', [
                'title'           => 'Test',
                'advertiser_name' => 'Adv',
                'customer_name'   => 'Cust',
                'customer_mobile' => '9000000000',
                'customer_email'  => 'c@test.com',
                'budget'          => 10000,
                'start_date'      => now()->addDay()->toDateString(),
                'end_date'        => now()->addDays(10)->toDateString(),
                'locations'       => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_agent_can_update_own_campaign(): void
    {
        $response = $this->actingAs($this->agent, 'sanctum')
            ->putJson("/api/campaigns/{$this->campaign->id}", [
                'notes' => 'Updated notes',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('campaigns', ['id' => $this->campaign->id, 'notes' => 'Updated notes']);
    }

    public function test_agent_can_delete_draft_campaign(): void
    {
        $response = $this->actingAs($this->agent, 'sanctum')
            ->deleteJson("/api/campaigns/{$this->campaign->id}");

        $response->assertOk();
        $this->assertSoftDeleted('campaigns', ['id' => $this->campaign->id]);
    }

    public function test_cannot_delete_campaign_with_confirmed_booking(): void
    {
        Booking::create([
            'booking_code'               => 'BKG-TEST-001',
            'campaign_id'                => $this->campaign->id,
            'provider_id'                => $this->inventory->provider_id,
            'inventory_id'               => $this->inventory->id,
            'booking_type'               => 'Static',
            'booking_source'             => 'Admin',
            'priority_level'             => 'Medium',
            'booking_start_date'         => now()->addDays(5)->toDateString(),
            'booking_end_date'           => now()->addDays(10)->toDateString(),
            'total_days'                 => 6,
            'reservation_expires_at'     => now()->addMinutes(30),
            'price'                      => 3000.00,
            'gst_percentage'             => 18.00,
            'gst_amount'                 => 540.00,
            'total_amount'               => 3540.00,
            'provider_amount'            => 2700.00,
            'commission_amount'          => 300.00,
            'booking_inventory_snapshot' => '{}',
            'booking_pricing_snapshot'   => '{}',
            'booking_status'             => 'Confirmed',
            'payment_status'             => 'Paid',
            'approved_by_provider'       => true,
            'created_by'                 => $this->agent->id,
        ]);

        $response = $this->actingAs($this->agent, 'sanctum')
            ->deleteJson("/api/campaigns/{$this->campaign->id}");

        $response->assertStatus(422);
    }

    // ─── Campaign Booking Summary ──────────────────────────────────────────────

    public function test_agent_can_get_campaign_booking_summary(): void
    {
        $response = $this->actingAs($this->agent, 'sanctum')
            ->getJson("/api/campaigns/{$this->campaign->id}/bookings");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['total_bookings', 'budget', 'spent', 'remaining_budget']]);
    }

    // ─── Campaign Budget Reconciliation ───────────────────────────────────────

    public function test_reconcile_updates_campaign_totals(): void
    {
        Booking::create([
            'booking_code'               => 'BKG-TEST-002',
            'campaign_id'                => $this->campaign->id,
            'provider_id'                => $this->inventory->provider_id,
            'inventory_id'               => $this->inventory->id,
            'booking_type'               => 'Static',
            'booking_source'             => 'Admin',
            'priority_level'             => 'Medium',
            'booking_start_date'         => now()->addDays(5)->toDateString(),
            'booking_end_date'           => now()->addDays(7)->toDateString(),
            'total_days'                 => 3,
            'reservation_expires_at'     => now()->addMinutes(30),
            'price'                      => 1500.00,
            'gst_percentage'             => 18.00,
            'gst_amount'                 => 270.00,
            'total_amount'               => 1770.00,
            'provider_amount'            => 1350.00,
            'commission_amount'          => 150.00,
            'booking_inventory_snapshot' => '{}',
            'booking_pricing_snapshot'   => '{}',
            'booking_status'             => 'Confirmed',
            'payment_status'             => 'Paid',
            'approved_by_provider'       => true,
            'created_by'                 => $this->agent->id,
        ]);

        $response = $this->actingAs($this->agent, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/reconcile");

        $response->assertOk();
        $this->assertDatabaseHas('campaigns', [
            'id'                    => $this->campaign->id,
            'campaign_total_amount' => '1770.00',
        ]);
    }

    // ─── Admin Campaign Endpoints ──────────────────────────────────────────────

    public function test_admin_can_list_all_campaigns(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/campaigns');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_show_any_campaign(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/campaigns/{$this->campaign->id}");

        $response->assertOk()
            ->assertJsonPath('data.campaign.id', $this->campaign->id);
    }

    public function test_admin_can_update_campaign_status(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/campaigns/{$this->campaign->id}/status", [
                'status' => 'Active',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('campaigns', ['id' => $this->campaign->id, 'status' => 'Active']);
    }

    // ─── Booking Cancel ────────────────────────────────────────────────────────

    public function test_user_can_cancel_temporary_hold(): void
    {
        $booking = Booking::create([
            'booking_code'               => 'BKG-HOLD-001',
            'campaign_id'                => $this->campaign->id,
            'provider_id'                => $this->inventory->provider_id,
            'inventory_id'               => $this->inventory->id,
            'booking_type'               => 'Static',
            'booking_source'             => 'Admin',
            'priority_level'             => 'Medium',
            'booking_start_date'         => now()->addDays(5)->toDateString(),
            'booking_end_date'           => now()->addDays(7)->toDateString(),
            'total_days'                 => 3,
            'reservation_expires_at'     => now()->addMinutes(30),
            'price'                      => 1500.00,
            'gst_percentage'             => 18.00,
            'gst_amount'                 => 270.00,
            'total_amount'               => 1770.00,
            'provider_amount'            => 1350.00,
            'commission_amount'          => 150.00,
            'booking_inventory_snapshot' => '{}',
            'booking_pricing_snapshot'   => '{}',
            'booking_status'             => 'Temporary Reserved',
            'payment_status'             => 'Pending',
            'approved_by_provider'       => false,
            'created_by'                 => $this->agent->id,
        ]);

        $response = $this->actingAs($this->agent, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'reason' => 'Customer changed mind.',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => 'Cancelled']);
    }

    public function test_cannot_cancel_already_cancelled_booking(): void
    {
        $booking = Booking::create([
            'booking_code'               => 'BKG-CAN-001',
            'campaign_id'                => $this->campaign->id,
            'provider_id'                => $this->inventory->provider_id,
            'inventory_id'               => $this->inventory->id,
            'booking_type'               => 'Static',
            'booking_source'             => 'Admin',
            'priority_level'             => 'Medium',
            'booking_start_date'         => now()->addDays(5)->toDateString(),
            'booking_end_date'           => now()->addDays(7)->toDateString(),
            'total_days'                 => 3,
            'reservation_expires_at'     => now()->addMinutes(30),
            'price'                      => 1500.00,
            'gst_percentage'             => 18.00,
            'gst_amount'                 => 270.00,
            'total_amount'               => 1770.00,
            'provider_amount'            => 1350.00,
            'commission_amount'          => 150.00,
            'booking_inventory_snapshot' => '{}',
            'booking_pricing_snapshot'   => '{}',
            'booking_status'             => 'Cancelled',
            'payment_status'             => 'Pending',
            'approved_by_provider'       => false,
            'created_by'                 => $this->agent->id,
        ]);

        $response = $this->actingAs($this->agent, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'reason' => 'Trying again',
            ]);

        $response->assertStatus(422);
    }

    // ─── Admin Booking List ────────────────────────────────────────────────────

    public function test_admin_can_list_all_bookings(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/bookings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['bookings', 'pagination']]);
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $booking = Booking::create([
            'booking_code'               => 'BKG-ADM-001',
            'campaign_id'                => $this->campaign->id,
            'provider_id'                => $this->inventory->provider_id,
            'inventory_id'               => $this->inventory->id,
            'booking_type'               => 'Static',
            'booking_source'             => 'Admin',
            'priority_level'             => 'Medium',
            'booking_start_date'         => now()->addDays(5)->toDateString(),
            'booking_end_date'           => now()->addDays(7)->toDateString(),
            'total_days'                 => 3,
            'reservation_expires_at'     => now()->addMinutes(30),
            'price'                      => 1500.00,
            'gst_percentage'             => 18.00,
            'gst_amount'                 => 270.00,
            'total_amount'               => 1770.00,
            'provider_amount'            => 1350.00,
            'commission_amount'          => 150.00,
            'booking_inventory_snapshot' => '{}',
            'booking_pricing_snapshot'   => '{}',
            'booking_status'             => 'Approval Pending',
            'payment_status'             => 'Pending',
            'approved_by_provider'       => true,
            'created_by'                 => $this->agent->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/bookings/{$booking->id}/cancel", [
                'reason' => 'Admin override cancellation.',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => 'Cancelled']);
    }
}
