<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $countryId = DB::table('countries')->updateOrInsert(
            ['iso_code' => 'IN'],
            [
                'name' => 'India',
                'code' => '+91',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $country = DB::table('countries')->where('iso_code', 'IN')->first();

        DB::table('states')->updateOrInsert(
            ['country_id' => $country->id, 'code' => 'TS'],
            [
                'name' => 'Telangana',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $state = DB::table('states')->where('country_id', $country->id)->where('code', 'TS')->first();

        DB::table('districts')->updateOrInsert(
            ['state_id' => $state->id, 'name' => 'Hyderabad'],
            ['status' => 'Active', 'created_at' => now(), 'updated_at' => now()]
        );

        $district = DB::table('districts')->where('state_id', $state->id)->where('name', 'Hyderabad')->first();

        DB::table('cities')->updateOrInsert(
            ['district_id' => $district->id, 'name' => 'Hyderabad'],
            [
                'latitude' => 17.38504400,
                'longitude' => 78.48667100,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $city = DB::table('cities')->where('district_id', $district->id)->where('name', 'Hyderabad')->first();

        DB::table('areas')->updateOrInsert(
            ['city_id' => $city->id, 'name' => 'Banjara Hills'],
            [
                'pincode' => '500034',
                'latitude' => 17.41262700,
                'longitude' => 78.44828900,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $area = DB::table('areas')->where('city_id', $city->id)->where('name', 'Banjara Hills')->first();

        DB::table('landmarks')->updateOrInsert(
            ['area_id' => $area->id, 'name' => 'Road No. 12 Junction'],
            [
                'type' => 'Junction',
                'latitude' => 17.41560000,
                'longitude' => 78.43470000,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('roads')->updateOrInsert(
            ['city_id' => $city->id, 'area_id' => $area->id, 'name' => 'Road No. 12'],
            [
                'road_type' => 'Arterial',
                'traffic_score' => 82.50,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
