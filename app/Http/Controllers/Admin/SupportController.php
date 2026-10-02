<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $tickets = SupportTicket::with('assignee')
            ->when($request->input('q'), fn ($q, $v) => $q->where('subject', 'like', "%{$v}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.tickets.index', compact('tickets'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'assignee', 'allMessages']);

        return view('admin.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'message' => 'required|string|min:2|max:3000',
            'internal_note' => 'nullable|boolean',
        ]);

        $ticket->allMessages()->create([
            'sender_type' => 'admin',
            'sender_id' => auth('admin')->id(),
            'message' => $validated['message'],
            'is_internal_note' => $request->boolean('internal_note'),
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        if (! $request->boolean('internal_note') && $ticket->user) {
            $this->notifications->sendUserNotification(
                $ticket->user->id,
                'Support Reply',
                "Our team replied to your ticket {$ticket->ticket_no}.",
                'support',
                ['ticket_no' => $ticket->ticket_no]
            );
        }

        return back()->with('success', $request->boolean('internal_note') ? 'Internal note added.' : 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,waiting,resolved,closed',
            'assigned_to' => 'nullable|integer|exists:admins,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $ticket->update($validated);

        ActivityLogger::log('update', 'support', "Ticket {$ticket->ticket_no} → {$ticket->status}");

        return back()->with('success', 'Ticket updated.');
    }

    public function messages()
    {
        $messages = ContactMessage::latest()->paginate(25);

        return view('admin.messages.index', compact('messages'));
    }
}
