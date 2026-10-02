<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function index()
    {
        return view('support.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('support', null, ['title' => 'Support']),
            'tickets' => auth('web')->check() ? auth('web')->user()->supportTickets()->with('messages')->paginate(10) : collect(),
            'bookings' => auth('web')->check() ? auth('web')->user()->bookings()->limit(20)->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email',
            'booking_reference' => 'nullable|string|max:30',
            'subject' => 'required|string|max:150',
            'priority' => 'required|in:low,medium,high,urgent',
            'message' => 'required|string|min:10|max:3000',
        ]);

        $booking = Booking::where('booking_reference', $validated['booking_reference'] ?? '')->first();

        $ticket = SupportTicket::create([
            'ticket_no' => 'TKT-' . strtoupper(substr(uniqid(), -7)),
            'user_id' => auth('web')->id() ?? optional($booking)->user_id,
            'booking_id' => $booking?->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        $ticket->allMessages()->create([
            'sender_type' => 'user',
            'sender_id' => auth('web')->id(),
            'message' => $validated['message'],
        ]);

        return back()->with('success', "Ticket {$ticket->ticket_no} created. We'll respond within 24 hours.");
    }
}
