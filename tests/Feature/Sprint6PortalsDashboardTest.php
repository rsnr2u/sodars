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
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Lead;
use App\Models\AnalyticsCache;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Services\AnalyticsService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Sprint6PortalsDashboardTest extends TestCase
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
            'provider_code' => 'PROV-6666',
            'company_name' => 'Metro Screen Group',
            'owner_name' => 'Metro Owner',
            'email' => 'owner@metroscreen.com',
            'mobile' => '9888988898',
            'gst_number' => 'GST666',
            'pan_number' => 'PAN666',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'address' => 'Metro Office',
            'status' => 'Approved',
            'marketplace_enabled' => true,
        ]);

        $inventory = Inventory::create([
            'provider_id' => $provider->id,
            'inventory_code' => 'INV-6601',
            'title' => 'Digital Billboard Metro Station',
            'media_type' => 'Digital Screen',
            'category' => 'Digital',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'latitude' => 12.9716,
            'longitude' => 77.5946,
            'width' => 15.0,
            'height' => 8.0,
            'facing_direction' => 'East',
            'lighting_type' => 'LED',
            'traffic_type' => 'Very High',
            'monthly_price' => 60000.00,
            'weekly_price' => 18000.00,
            'daily_price' => 3000.00,
            'status' => 'Available',
            'marketplace_enabled' => true,
        ]);

        return [$provider, $inventory];
    }

    public function test_dashboard_analytics_caching_and_metric_feeds(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();

        $headers = $this->getAuthHeaderForAgent();
        $agentUser = User::first();

        // 1. Seed some financial data
        $campaign = Campaign::create([
            'campaign_code' => 'CMP-6661',
            'title' => 'Coke Campaign',
            'advertiser_name' => 'Coca-Cola',
            'customer_name' => 'Coke Mgr',
            'customer_mobile' => '99998888',
            'customer_email' => 'coke@c.com',
            'budget' => 100000,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'status' => 'Draft',
            'created_by' => $agentUser->id,
        ]);

        $booking = Booking::create([
            'booking_code' => 'BKG-6661',
            'campaign_id' => $campaign->id,
            'provider_id' => $provider->id,
            'inventory_id' => $inventory->id,
            'booking_type' => 'Digital',
            'booking_source' => 'Admin',
            'booking_start_date' => '2026-10-01',
            'booking_end_date' => '2026-10-05',
            'total_days' => 5,
            'price' => 30000,
            'gst_percentage' => 18,
            'gst_amount' => 5400,
            'total_amount' => 35400,
            'provider_amount' => 27000,
            'commission_amount' => 3000,
            'booking_status' => 'Confirmed',
            'payment_status' => 'Paid',
            'created_by' => $agentUser->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-6661',
            'booking_id' => $booking->id,
            'campaign_id' => $booking->campaign_id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => 30000,
            'gst_amount' => 5400,
            'total_amount' => 35400,
            'payment_status' => 'Paid',
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'payment_mode' => 'Gateway',
            'transaction_id' => 'TXN-6661',
            'amount' => 35400,
            'payment_date' => now(),
            'payment_status' => 'Paid',
        ]);

        // Seed a Lead
        Lead::create([
            'agent_id' => $agentUser->id,
            'name' => 'Zara Retail',
            'company_name' => 'Zara India',
            'mobile' => '9900990099',
            'email' => 'zara@zara.in',
            'lead_source' => 'Marketplace',
            'status' => 'New',
        ]);

        // 2. Trigger analytics service compilations
        $service = app(AnalyticsService::class);
        $service->compileAdminDashboard();
        $service->compileProviderDashboard($provider->id);
        $service->compileAgentDashboard($agentUser->id);

        $this->assertDatabaseHas('analytics_cache', ['analytics_type' => 'admin_dashboard']);
        $this->assertDatabaseHas('analytics_cache', ['analytics_type' => "provider_dashboard_{$provider->id}"]);
        $this->assertDatabaseHas('analytics_cache', ['analytics_type' => "agent_dashboard_{$agentUser->id}"]);

        // 3. Test API fetches serve cached counts correctly
        $this->getJson('/api/dashboard/admin', $headers)
            ->assertOk()
            ->assertJsonPath('data.metrics.total_revenue', 35400)
            ->assertJsonPath('data.metrics.confirmed_bookings', 1)
            ->assertJsonPath('data.metrics.total_leads', 1);

        $this->getJson('/api/dashboard/provider?provider_id=' . $provider->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.metrics.total_revenue', 27000)
            ->assertJsonPath('data.metrics.total_bookings', 1);

        $this->getJson('/api/dashboard/agent', $headers)
            ->assertOk()
            ->assertJsonPath('data.metrics.total_leads', 1);
    }

    public function test_notifications_feeds_read_states_and_broadcasting(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAgent();
        $agentUser = User::first();

        $service = app(NotificationService::class);

        // 1. Dispatch in-app alert notification
        $service->send($agentUser, 'New Lead Assigned', 'Zara India lead is assigned to you.', 'Push');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $agentUser->id,
            'title' => 'New Lead Assigned',
            'is_read' => false,
        ]);

        // 2. Index Feed and unread counts
        $resFeed = $this->getJson('/api/notifications', $headers)
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.data.0.title', 'New Lead Assigned');

        $notificationId = $resFeed->json('data.notifications.data.0.id');

        // 3. Mark read state
        $this->putJson('/api/notifications/' . $notificationId . '/read', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.notification.is_read', true);

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'is_read' => true,
        ]);

        // 4. Test admin broadcasting
        $this->postJson('/api/notifications/broadcast', [
            'title' => 'Emergency System DownTime',
            'message' => 'The server will be undergoing maintenance tonight.',
            'type' => 'Email',
        ], $headers)
            ->assertOk();

        // Check logs dispatch telemetries exist
        $this->assertDatabaseHas('notification_logs', [
            'notification_type' => 'Email',
            'status' => 'Sent',
        ]);
    }

    public function test_crm_lead_conversions_auto_drafting_campaigns(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAgent();
        $agentUser = User::first();

        // 1. Seed CRM lead
        $lead = Lead::create([
            'agent_id' => $agentUser->id,
            'name' => 'Nike Sports manager',
            'company_name' => 'Nike India Corp',
            'mobile' => '9988998899',
            'email' => 'nike@corp.com',
            'lead_source' => 'Marketplace',
            'status' => 'Interested',
        ]);

        // 2. Hit update status to Converted
        $this->putJson('/api/crm/leads/' . $lead->id, [
            'status' => 'Converted',
            'remarks' => 'Signed outline MoU for outdoor campaign planning.',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.lead.status', 'Converted');

        // 3. Verify Campaign is automatically drafted matching contacts!
        $this->assertDatabaseHas('campaigns', [
            'advertiser_name' => 'Nike India Corp',
            'customer_name' => 'Nike Sports manager',
            'customer_email' => 'nike@corp.com',
            'status' => 'Draft',
            'created_by' => $agentUser->id,
        ]);
    }

    public function test_revenue_and_occupancy_reporting_ledgers(): void
    {
        $this->seed();
        [$provider, $inventory] = $this->setupProviderAndInventory();
        $headers = $this->getAuthHeaderForAgent();

        // Hit revenue and occupancy reports
        $this->getJson('/api/reports/revenue', $headers)
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['payments', 'report_summary']]);

        $this->getJson('/api/reports/occupancy', $headers)
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['occupancy_reports']]);
    }
}
