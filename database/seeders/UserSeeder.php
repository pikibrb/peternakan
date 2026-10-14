<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@peternakan.com',
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'Pemilik Peternakan',
            'username' => 'pemilik',
            'email' => 'pemilik@peternakan.com',
            'password' => Hash::make('password123'),
        ]);
    }
}