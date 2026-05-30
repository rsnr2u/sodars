<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'platform.name' => 'SODARS',
            'platform.currency' => 'INR',
            'booking.hold_minutes' => '30',
            'storage.default_disk' => 'local',
        ] as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        DB::table('tax_settings')->updateOrInsert(
            ['id' => 1],
            [
                'gst_percentage' => 18.00,
                'cgst_percentage' => 9.00,
                'sgst_percentage' => 9.00,
                'igst_percentage' => 18.00,
                'tds_percentage' => 1.00,
                'updated_at' => now(),
            ]
        );
    }
}
