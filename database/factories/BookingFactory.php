<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'booking_reference' => 'VNH' . strtoupper(substr(uniqid(), -6)) . random_int(10, 99),
            'product_type' => 'package',
            'status' => 'payment_pending',
            'supplier_cost' => 20000,
            'subtotal' => 21000,
            'markup_amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 21000,
            'currency' => 'INR',
            'contact' => ['email' => $this->faker->email, 'phone' => '9876543210', 'first_name' => 'Test'],
            'price_breakdown' => ['total' => 21000],
            'booked_at' => now(),
        ];
    }
}
