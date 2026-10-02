<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AgentSeeder extends Seeder
{
    /**
     * Idempotent — a demo approved agent for the B2B portal.
     * Login: agent@leemroztravels.com / password
     */
    public function run(): void
    {
        Agent::updateOrCreate(
            ['email' => 'agent@leemroztravels.com'],
            [
                'agency_name' => 'Demo Travel Partners',
                'agency_code' => 'AG-DEMO-0001',
                'contact_person' => 'Demo Agent',
                'password' => Hash::make('password'),
                'phone' => '+91 90000 00001',
                'city' => 'Srinagar',
                'state' => 'Jammu & Kashmir',
                'pincode' => '190001',
                'status' => 'approved',
                'wallet_balance' => 100000.00,
                'credit_limit' => 50000.00,
                'credit_balance' => 0.00,
                'commission_rate' => 10.00,
                'markup_rate' => 0.00,
                'approved_at' => now(),
                'applied_at' => now(),
            ]
        );
    }
}
