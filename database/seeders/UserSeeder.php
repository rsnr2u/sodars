<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@sodars.local'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'SODARS Super Admin',
                'mobile' => '9999999999',
                'password' => Hash::make('password'),
                'status' => 'Active',
                'email_verified_at' => now(),
                'mobile_verified_at' => now(),
            ]
        );

        $user->assignRole('Super Admin');
    }
}
