<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint1FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_uses_standard_api_envelope(): void
    {
        $this->seed();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.app', 'SODARS');
    }

    public function test_seeded_super_admin_can_login_and_fetch_profile(): void
    {
        $this->seed();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@sodars.local',
            'password' => 'password',
            'device_name' => 'feature-test',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.roles.0', 'Super Admin');

        $token = $login->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@sodars.local');
    }

    public function test_location_lookup_chain_returns_seeded_hierarchy(): void
    {
        $this->seed();

        $country = $this->getJson('/api/locations/countries')
            ->assertOk()
            ->json('data.items.data.0');

        $state = $this->getJson('/api/locations/states-by-country/'.$country['id'])
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Telangana')
            ->json('data.items.0');

        $district = $this->getJson('/api/locations/districts-by-state/'.$state['id'])
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Hyderabad')
            ->json('data.items.0');

        $this->getJson('/api/locations/cities-by-district/'.$district['id'])
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Hyderabad');
    }
}
