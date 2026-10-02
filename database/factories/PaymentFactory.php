<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'gateway' => 'mock',
            'gateway_order_id' => 'mock_order_' . strtoupper(substr(md5(microtime()), 0, 14)),
            'amount' => 21000,
            'currency' => 'INR',
            'status' => 'created',
        ];
    }
}
