<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Landmark;
use App\Models\Provider;
use App\Models\ProviderBankAccount;
use App\Models\ProviderDocument;
use App\Models\ProviderStaff;
use App\Models\Road;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Sprint2ProviderInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function getAuthHeaderForAdmin(): array
    {
        $this->app['auth']->forgetGuards();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@sodars.local',
            'password' => 'password',
            'device_name' => 'admin-test',
        ])->assertOk();

        return ['Authorization' => 'Bearer ' . $login->json('data.access_token')];
    }

    public function test_provider_registration_onboarding_and_auth_flow(): void
    {
        $this->seed();

        $country = Country::firstOrFail();
        $state = State::firstOrFail();
        $district = District::firstOrFail();
        $city = City::firstOrFail();

        // 1. Public Registration
        $registrationPayload = [
            'company_name' => 'Acme Outdoor Media Ltd',
            'owner_name' => 'John Doe',
            'email' => 'john@acmeoutdoor.com',
            'mobile' => '9876543210',
            'gst_number' => '36AAAAA1111A1Z1',
            'pan_number' => 'AAAAA1111A',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'address' => '123 Banjara Hills, Road No. 12, Hyderabad',
            'password' => 'password123',
        ];

        $registerRes = $this->postJson('/api/providers/register', $registrationPayload)
            ->assertStatus(211)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider.status', 'Pending');

        $providerId = $registerRes->json('data.provider.id');
        $this->assertNotNull($registerRes->json('data.provider.provider_code'));

        // 2. Login fails while Pending
        $this->postJson('/api/provider/auth/login', [
            'email' => 'john@acmeoutdoor.com',
            'password' => 'password123',
        ])->assertStatus(403)
          ->assertJsonFragment(['success' => false]);

        // 3. Admin views and Approves Provider
        $adminHeaders = $this->getAuthHeaderForAdmin();

        $this->getJson('/api/admin/providers', $adminHeaders)
            ->assertOk()
            ->assertJsonPath('data.providers.data.0.company_name', 'Acme Outdoor Media Ltd');

        $this->getJson('/api/admin/providers/' . $providerId, $adminHeaders)
            ->assertOk()
            ->assertJsonPath('data.provider.company_name', 'Acme Outdoor Media Ltd');

        $this->postJson('/api/admin/providers/' . $providerId . '/status', [
            'status' => 'Approved',
        ], $adminHeaders)
            ->assertOk()
            ->assertJsonPath('data.provider.status', 'Approved');

        // 4. Login succeeds after Approval
        $this->app['auth']->forgetGuards();
        $loginRes = $this->postJson('/api/provider/auth/login', [
            'email' => 'john@acmeoutdoor.com',
            'password' => 'password123',
            'device_name' => 'staff-test-device',
        ])->assertOk()
          ->assertJsonPath('success', true);

        $providerHeaders = ['Authorization' => 'Bearer ' . $loginRes->json('data.access_token')];

        // 5. Fetch Profile
        $this->getJson('/api/provider/auth/profile', $providerHeaders)
            ->assertOk()
            ->assertJsonPath('data.staff.email', 'john@acmeoutdoor.com');
    }

    public function test_kyc_and_bank_details_workflows(): void
    {
        $this->seed();

        // Setup Approved Provider
        $provider = Provider::create([
            'provider_code' => 'PROV-1111',
            'company_name' => 'Glow Advertising',
            'owner_name' => 'Owner Name',
            'email' => 'owner@glow.com',
            'mobile' => '9988776655',
            'gst_number' => 'GST111',
            'pan_number' => 'PAN111',
            'country_id' => Country::first()->id,
            'state_id' => State::first()->id,
            'district_id' => District::first()->id,
            'city_id' => City::first()->id,
            'address' => 'Glow Office',
            'status' => 'Approved',
        ]);

        $staff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'name' => 'Owner Name',
            'email' => 'owner@glow.com',
            'mobile' => '9988776655',
            'role' => 'Owner',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);

        $token = $staff->createToken('test')->plainTextToken;
        $providerHeaders = ['Authorization' => 'Bearer ' . $token];

        // 1. Upload Document
        $file = UploadedFile::fake()->create('gst.pdf', 1000);
        $docUploadRes = $this->postJson('/api/provider/documents', [
            'document_type' => 'GST Registration Certificate',
            'document_file' => $file,
        ], $providerHeaders)
            ->assertOk()
            ->assertJsonPath('data.document.verification_status', 'Pending');

        $docId = $docUploadRes->json('data.document.id');

        $this->getJson('/api/provider/documents', $providerHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data.documents');

        // 2. Admin Verifies Document
        $this->app['auth']->forgetGuards();
        $adminHeaders = $this->getAuthHeaderForAdmin();
        $this->postJson('/api/admin/providers/' . $provider->id . '/documents/' . $docId . '/verify', [
            'status' => 'Approved',
        ], $adminHeaders)
            ->assertOk()
            ->assertJsonPath('data.document.verification_status', 'Approved');

        // 3. Bank Account CRUD
        $this->app['auth']->forgetGuards();
        $bankRes = $this->postJson('/api/provider/bank-accounts', [
            'bank_name' => 'State Bank of India',
            'account_holder_name' => 'Glow Advertising',
            'account_number' => '1234567890',
            'ifsc_code' => 'SBIN0001234',
            'upi_id' => 'glow@upi',
            'is_primary' => true,
        ], $providerHeaders)->assertOk();

        $bankId = $bankRes->json('data.bank_account.id');

        $this->getJson('/api/provider/bank-accounts', $providerHeaders)
            ->assertOk()
            ->assertJsonPath('data.bank_accounts.0.bank_name', 'State Bank of India');

        $this->putJson('/api/provider/bank-accounts/' . $bankId, [
            'bank_name' => 'SBI Corporate Branch',
        ], $providerHeaders)
            ->assertOk()
            ->assertJsonPath('data.bank_account.bank_name', 'SBI Corporate Branch');

        $this->deleteJson('/api/provider/bank-accounts/' . $bankId, [], $providerHeaders)
            ->assertOk();

        $this->getJson('/api/provider/bank-accounts', $providerHeaders)
            ->assertOk()
            ->assertJsonCount(0, 'data.bank_accounts');
    }

    public function test_inventory_crud_gallery_pricing_maintenance_and_marketplace_flow(): void
    {
        $this->seed();

        $country = Country::first();
        $state = State::first();
        $district = District::first();
        $city = City::first();
        $area = Area::first();
        $landmark = Landmark::first();
        $road = Road::first();

        // Setup Approved & Marketplace Enabled Provider
        $provider = Provider::create([
            'provider_code' => 'PROV-2222',
            'company_name' => 'Apex Media',
            'owner_name' => 'Apex Owner',
            'email' => 'owner@apex.com',
            'mobile' => '9988776650',
            'gst_number' => 'GST222',
            'pan_number' => 'PAN222',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'address' => 'Apex Office',
            'status' => 'Approved',
            'marketplace_enabled' => true,
        ]);

        $staff = ProviderStaff::create([
            'provider_id' => $provider->id,
            'name' => 'Apex Owner',
            'email' => 'owner@apex.com',
            'mobile' => '9988776650',
            'role' => 'Owner',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);

        $token = $staff->createToken('test')->plainTextToken;
        $providerHeaders = ['Authorization' => 'Bearer ' . $token];

        // 1. Create Inventory
        $inventoryPayload = [
            'title' => 'Premium Digital Hoarding - Banjara Hills Road 12',
            'media_type' => 'Digital Screen',
            'category' => 'Billboard',
            'description' => 'A prime digital hoarding targeting premium heavy traffic.',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'landmark_id' => $landmark->id,
            'road_id' => $road->id,
            'latitude' => 17.41262700,
            'longitude' => 78.44828900,
            'width' => 20.5,
            'height' => 10.0,
            'facing_direction' => 'East',
            'lighting_type' => 'Frontlit',
            'traffic_type' => 'High',
            'visibility_score' => 95.0,
            'traffic_score' => 88.0,
            'monthly_price' => 50000.00,
            'weekly_price' => 15000.00,
            'daily_price' => 2500.00,
            'marketplace_enabled' => true,
            'featured' => true,
        ];

        $invRes = $this->postJson('/api/provider/inventory', $inventoryPayload, $providerHeaders)
            ->assertStatus(211)
            ->assertJsonPath('data.inventory.title', 'Premium Digital Hoarding - Banjara Hills Road 12');

        $invId = $invRes->json('data.inventory.id');
        $this->assertNotNull($invRes->json('data.inventory.inventory_code'));

        // 2. Upload Media to Gallery
        $file = UploadedFile::fake()->create('hoarding.jpg', 100);
        $galleryRes = $this->postJson('/api/provider/inventory/' . $invId . '/gallery', [
            'file_type' => 'Image',
            'file_path' => $file,
            'is_primary' => true,
            'sort_order' => 1,
        ], $providerHeaders)
            ->assertOk()
            ->assertJsonPath('data.media.is_primary', true);

        // 3. Set pricing rule
        $this->postJson('/api/provider/inventory/' . $invId . '/pricing', [
            'price_type' => 'Festival',
            'amount' => 3500.00,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
        ], $providerHeaders)
            ->assertOk()
            ->assertJsonPath('data.pricing.amount', '3500.00');

        // 4. Set maintenance block
        $this->postJson('/api/provider/inventory/' . $invId . '/maintenance', [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
            'reason' => 'Routine screen cleaning and hardware checkup',
        ], $providerHeaders)
            ->assertOk()
            ->assertJsonPath('data.maintenance.reason', 'Routine screen cleaning and hardware checkup');

        // 5. Verify Marketplace Index and Show
        $this->getJson('/api/marketplace/inventory')
            ->assertOk()
            ->assertJsonPath('data.listings.data.0.title', 'Premium Digital Hoarding - Banjara Hills Road 12');

        $this->getJson('/api/marketplace/inventory/' . $invId)
            ->assertOk()
            ->assertJsonPath('data.listing.title', 'Premium Digital Hoarding - Banjara Hills Road 12');
    }
}
