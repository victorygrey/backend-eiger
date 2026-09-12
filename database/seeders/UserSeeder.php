<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default SuperAdmin (Full access to User Management, Integrations, and all modules)
        User::updateOrCreate(
            ['email' => 'superadmin@eigeradventure.com'],
            [
                'name'      => 'SuperAdmin EIGER',
                'password'  => Hash::make('password'),
                'role'      => 'superadmin',
                'is_active' => true,
            ]
        );

        // 2. Default Store Admin (Access to catalog, RFID mappings, interactive displays)
        User::updateOrCreate(
            ['email' => 'admin@eigeradventure.com'],
            [
                'name'      => 'Admin Store EIGER',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );
    }
}
