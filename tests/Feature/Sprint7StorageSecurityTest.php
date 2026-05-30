<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Inventory;
use App\Models\Provider;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Sprint7StorageSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function getAuthHeaderForAdmin(): array
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@sodars.local',
            'password' => 'password',
            'device_name' => 'admin-test',
        ])->assertOk();

        return ['Authorization' => 'Bearer ' . $login->json('data.access_token')];
    }

    private function setupBookingAndInventory(): array
    {
        $country = Country::first() ?? Country::create(['name' => 'India', 'iso_code' => 'IN']);
        $state = State::first() ?? State::create(['country_id' => $country->id, 'name' => 'Telangana']);
        $district = District::first() ?? District::create(['state_id' => $state->id, 'name' => 'Hyderabad']);
        $city = City::first() ?? City::create(['district_id' => $district->id, 'name' => 'Hyderabad']);
        $area = Area::first() ?? Area::create(['city_id' => $city->id, 'name' => 'Banjara Hills']);

        $provider = Provider::create([
            'provider_code' => 'PROV-7777',
            'company_name' => 'Test Provider Ltd',
            'owner_name' => 'Owner',
            'email' => 'owner@testprovider.com',
            'mobile' => '9988776655',
            'gst_number' => 'GST777',
            'pan_number' => 'PAN777',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'city_id' => $city->id,
            'address' => 'Test Office Address',
            'status' => 'Approved',
            'marketplace_enabled' => true,
        ]);

        $inventory = Inventory::create([
            'provider_id' => $provider->id,
            'inventory_code' => 'INV-7777',
            'title' => 'Test Billboard Banjara Hills',
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

        $user = User::where('email', 'admin@sodars.local')->first();

        $campaign = Campaign::create([
            'campaign_code' => 'CMP-' . uniqid(),
            'title' => 'Test Campaign',
            'advertiser_name' => 'Test Client',
            'customer_name' => 'Test Customer',
            'customer_mobile' => '99887766',
            'customer_email' => 'customer@test.com',
            'budget' => 50000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => 'Draft',
            'created_by' => $user->id,
        ]);

        $booking = Booking::create([
            'booking_code' => 'B-' . uniqid(),
            'campaign_id' => $campaign->id,
            'inventory_id' => $inventory->id,
            'provider_id' => $provider->id,
            'booking_type' => 'Digital',
            'booking_start_date' => now()->toDateString(),
            'booking_end_date' => now()->addDays(5)->toDateString(),
            'total_days' => 5,
            'price' => 10000.00,
            'gst_percentage' => 18.00,
            'gst_amount' => 1800.00,
            'total_amount' => 11800.00,
            'provider_amount' => 9000.00,
            'commission_amount' => 1000.00,
            'booking_status' => 'Temporary Reserved',
            'payment_status' => 'Pending',
            'created_by' => $user->id,
            'booking_source' => 'Website',
        ]);

        return [$booking, $inventory];
    }

    /**
     * Test custom auth rate limiting (5 requests/minute).
     */
    public function test_auth_rate_limiter_blocks_on_the_sixth_failed_attempt(): void
    {
        $this->seed();

        // Perform 5 consecutive attempts (should return 422 for bad credentials)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'admin@sodars.local',
                'password' => 'wrongpassword',
                'device_name' => 'rate-limit-test',
            ])->assertStatus(422);
        }

        // The 6th attempt should trigger 429 Too Many Requests
        $this->postJson('/api/auth/login', [
            'email' => 'admin@sodars.local',
            'password' => 'wrongpassword',
            'device_name' => 'rate-limit-test',
        ])->assertStatus(429);
    }

    /**
     * Test upload validation rejects file size > 10MB.
     */
    public function test_upload_validation_rejects_heavy_file(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAdmin();
        [$booking] = $this->setupBookingAndInventory();

        // 11MB file
        $heavyFile = UploadedFile::fake()->create('heavy_image.jpg', 11 * 1024);

        $this->postJson("/api/bookings/{$booking->id}/artwork", [
            'artwork_file' => $heavyFile,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['artwork_file']);
    }

    /**
     * Test upload validation rejects invalid file extensions.
     */
    public function test_upload_validation_rejects_invalid_extension(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAdmin();
        [$booking] = $this->setupBookingAndInventory();

        // .exe executable file
        $exeFile = UploadedFile::fake()->create('exploit.exe', 100);

        $this->postJson("/api/bookings/{$booking->id}/artwork", [
            'artwork_file' => $exeFile,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['artwork_file']);
    }

    /**
     * Test upload validation rejects images with low resolutions (dimensions < 800x600).
     */
    public function test_upload_validation_rejects_low_resolution_image(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAdmin();
        [$booking] = $this->setupBookingAndInventory();

        // 400x300 PNG image faked without GD extension
        $lowResImage = UploadedFile::fake()->createWithContent('small.png', $this->generateFakePng(400, 300));

        $this->postJson("/api/bookings/{$booking->id}/artwork", [
            'artwork_file' => $lowResImage,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['artwork_file'])
            ->assertJsonFragment([
                'artwork_file' => ['Image dimensions must be at least 800x600 pixels. Got 400x300.']
            ]);
    }

    /**
     * Test upload validation rejects potential malware files explicitly named 'eicar.txt'.
     */
    public function test_upload_validation_rejects_malware_file_by_name(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAdmin();
        [$booking] = $this->setupBookingAndInventory();

        // eicar.txt mock file
        $eicarFile = UploadedFile::fake()->create('eicar.txt', 1);

        $this->postJson("/api/bookings/{$booking->id}/artwork", [
            'artwork_file' => $eicarFile,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['artwork_file'])
            ->assertJsonFragment([
                'artwork_file' => ['Potential security threat detected: Malware scan failed.']
            ]);
    }

    /**
     * Test upload validation rejects files containing the EICAR test signature content.
     */
    public function test_upload_validation_rejects_malware_file_by_content(): void
    {
        $this->seed();
        $headers = $this->getAuthHeaderForAdmin();
        [$booking] = $this->setupBookingAndInventory();

        // Mock file containing the custom test string
        $maliciousContent = 'MOCK-MALWARE-SIGNATURE-TEST';
        $eicarFile = UploadedFile::fake()->createWithContent('benign_looking_document.pdf', $maliciousContent);

        $this->postJson("/api/bookings/{$booking->id}/artwork", [
            'artwork_file' => $eicarFile,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['artwork_file'])
            ->assertJsonFragment([
                'artwork_file' => ['Potential security threat detected: Malware scan failed.']
            ]);
    }

    /**
     * Test upload validation accepts valid high-resolution image.
     */
    public function test_upload_validation_accepts_valid_file(): void
    {
        Storage::fake('local');

        $this->seed();
        $headers = $this->getAuthHeaderForAdmin();
        [$booking] = $this->setupBookingAndInventory();

        // 800x600 PNG image faked without GD extension
        $validImage = UploadedFile::fake()->createWithContent('safe_artwork.png', $this->generateFakePng(800, 600));

        $this->postJson("/api/bookings/{$booking->id}/artwork", [
            'artwork_file' => $validImage,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.artwork.artwork_status', 'Pending');
    }

    /**
     * Verify all DevOps and CI pipeline configuration files exist in the repository.
     */
    public function test_devops_configurations_files_exist(): void
    {
        $this->assertTrue(file_exists(base_path('docker/Dockerfile')), 'Dockerfile does not exist');
        $this->assertTrue(file_exists(base_path('docker-compose.yml')), 'docker-compose.yml does not exist');
        $this->assertTrue(file_exists(base_path('docker/nginx.conf')), 'nginx.conf does not exist');
        $this->assertTrue(file_exists(base_path('docker/supervisor.conf')), 'supervisor.conf does not exist');
        $this->assertTrue(file_exists(base_path('docker/opcache.ini')), 'opcache.ini does not exist');
        $this->assertTrue(file_exists(base_path('.github/workflows/ci.yml')), 'ci.yml does not exist');
    }

    /**
     * Helper to generate a PNG header with custom width and height.
     * Parses cleanly via getimagesize() without requiring GD extension.
     */
    private function generateFakePng(int $width, int $height): string
    {
        $ihdrData = pack('N', $width) . pack('N', $height) . pack('C5', 8, 2, 0, 0, 0);
        $ihdrCrc = pack('N', crc32('IHDR' . $ihdrData));
        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdrData . $ihdrCrc;
    }
}
