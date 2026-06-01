<?php

namespace Tests;

use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Inventory;
use App\Models\Provider;
use App\Models\State;

/**
 * Shared fixture builder for tests that require location + provider + inventory hierarchy.
 * Supplies all NOT NULL columns required by the MySQL schema (replicated in SQLite during tests).
 */
trait CreatesTestFixtures
{
    protected function createCountry(array $overrides = []): Country
    {
        return Country::create(array_merge([
            'name'     => 'India',
            'code'     => 'IN',
            'iso_code' => 'IND',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
        ], $overrides));
    }

    protected function createState(Country $country, array $overrides = []): State
    {
        return State::create(array_merge([
            'name'       => 'Maharashtra',
            'code'       => 'MH',
            'country_id' => $country->id,
        ], $overrides));
    }

    protected function createDistrict(State $state, array $overrides = []): District
    {
        return District::create(array_merge([
            'name'     => 'Mumbai',
            'state_id' => $state->id,
        ], $overrides));
    }

    protected function createCity(District $district, State $state, array $overrides = []): City
    {
        return City::create(array_merge([
            'name'        => 'Mumbai City',
            'district_id' => $district->id,
            'state_id'    => $state->id,
            'latitude'    => 19.0760,
            'longitude'   => 72.8777,
        ], $overrides));
    }

    protected function createProvider(Country $country, State $state, District $district, City $city, array $overrides = []): Provider
    {
        return Provider::create(array_merge([
            'provider_code' => 'PVD-TEST-001',
            'company_name'  => 'Test Media Co',
            'owner_name'    => 'John Doe',
            'email'         => 'provider@mediaco.com',
            'mobile'        => '9999999999',
            'gst_number'    => '27AAAAA0000A1Z5',
            'pan_number'    => 'AAAAA0000A',
            'country_id'    => $country->id,
            'state_id'      => $state->id,
            'district_id'   => $district->id,
            'city_id'       => $city->id,
            'address'       => '123 Test Street, Mumbai',
            'status'        => 'Approved',
        ], $overrides));
    }

    protected function createInventory(Provider $provider, Country $country, State $state, District $district, City $city, array $overrides = []): Inventory
    {
        static $seq = 0;
        $seq++;

        return Inventory::create(array_merge([
            'provider_id'         => $provider->id,
            'inventory_code'      => "INV-TEST-{$seq}",
            'title'               => "Test Billboard {$seq}",
            'media_type'          => 'Hoarding',
            'category'            => 'Outdoor',
            'country_id'          => $country->id,
            'state_id'            => $state->id,
            'district_id'         => $district->id,
            'city_id'             => $city->id,
            'latitude'            => 19.0760,
            'longitude'           => 72.8777,
            'width'               => 20.00,
            'height'              => 10.00,
            'facing_direction'    => 'North',
            'lighting_type'       => 'Frontlit',
            'traffic_type'        => 'High',
            'monthly_price'       => 15000.00,
            'weekly_price'        => 4000.00,
            'daily_price'         => 500.00,
            'status'              => 'Available',
            'marketplace_enabled' => true,
        ], $overrides));
    }

    /**
     * Build the complete geo + provider + inventory chain in one call.
     * Returns an array: ['country', 'state', 'district', 'city', 'provider', 'inventory']
     */
    protected function buildInventoryFixture(array $providerOverrides = [], array $inventoryOverrides = []): array
    {
        $country  = $this->createCountry();
        $state    = $this->createState($country);
        $district = $this->createDistrict($state);
        $city     = $this->createCity($district, $state);
        $provider = $this->createProvider($country, $state, $district, $city, $providerOverrides);
        $inventory = $this->createInventory($provider, $country, $state, $district, $city, $inventoryOverrides);

        return compact('country', 'state', 'district', 'city', 'provider', 'inventory');
    }
}
