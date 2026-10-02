<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@leemroztravels.com')],
            [
                'name' => env('ADMIN_NAME', 'Leemroz Travels'),
                'password' => env('ADMIN_PASSWORD', 'Admin@12345'),
                'is_super_admin' => true,
                'status' => 'active',
            ]
        );
    }
}
