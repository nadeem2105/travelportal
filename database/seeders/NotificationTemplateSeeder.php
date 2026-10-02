<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['welcome', 'Welcome on Board', 'Welcome to {name}!', "Hi {name},\n\nWelcome to Leemroz Travels! Your account is ready.\n\nExplore curated Kashmir packages, flights, hotels and cabs — all in one place.\n\nHappy travels!\nThe Leemroz Travels Team"],
            ['booking_confirmed', 'Booking Confirmed', 'Booking Confirmed — {booking_id}', "Hi {name},\n\nGreat news! Your booking {booking_id} is CONFIRMED.\n\nDestination: {destination}\nTravel Date: {travel_date}\nAmount Paid: {amount}\n\nView your ticket: {ticket_url}\nDownload invoice: {invoice_url}\n\nThank you for travelling with us.\nLeemroz Travels"],
            ['booking_processing', 'Payment Received (Processing)', 'Payment Received — {booking_id}', "Hi {name},\n\nWe have received your payment of {amount} for booking {booking_id}.\n\nYour booking is being confirmed with our supplier. You'll receive a confirmation shortly — no action needed.\n\nLeemroz Travels"],
            ['booking_cancelled', 'Booking Cancelled & Refund', 'Booking Cancelled — {booking_id}', "Hi {name},\n\nYour booking {booking_id} has been cancelled.\n\nRefund amount: {amount}\nYou'll receive it within {refund_days} working days.\n\nWe hope to host you again soon.\nLeemroz Travels"],
            ['otp', 'OTP Code', 'Your Leemroz Travels verification code', "Hi {name},\n\nYour verification code is: {otp}\n\nIt expires in 10 minutes. Never share this code with anyone.\n\nLeemroz Travels"],
            ['contact_received', 'Contact Form Received', 'We received your message — {subject}', "Hi {name},\n\nThank you for reaching out about \"{subject}\".\n\nOur travel experts will reply within 24 hours.\n\nLeemroz Travels"],
            ['admin_new_booking', 'Admin — New Booking', 'New {product} booking: {booking_id}', "New booking received!\n\nBooking: {booking_id}\nProduct: {product}\nCustomer: {customer}\nAmount: {amount}\n\nOpen the admin panel to review."],
        ];

        foreach ($templates as [$key, $name, $subject, $body]) {
            NotificationTemplate::updateOrCreate(
                ['key' => $key, 'channel' => 'email'],
                ['name' => $name, 'subject' => $subject, 'body' => $body, 'is_active' => true]
            );
        }
    }
}
