<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        PaymentGateway::updateOrCreate(
            ['code' => 'mock'],
            [
                'name' => 'Mock Gateway (Sandbox)',
                'is_enabled' => true,
                'mode' => 'test',
                'config' => ['secret' => 'mock-secret', 'webhook_secret' => 'mock-webhook-secret'],
                'currency' => 'INR',
                'sort_order' => 1,
            ]
        );

        PaymentGateway::updateOrCreate(
            ['code' => 'razorpay'],
            [
                'name' => 'Razorpay',
                'is_enabled' => false,
                'mode' => 'test',
                'config' => [],
                'currency' => 'INR',
                'sort_order' => 2,
            ]
        );
    }
}
