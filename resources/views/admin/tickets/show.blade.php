@extends('layouts.admin')
@section('pageTitle', 'Ticket ' . $ticket->ticket_no)

@section('content')
    <a href="{{ route('admin.tickets.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Tickets</a>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-xl font-bold">{{ $ticket->subject }}</h1>
            <p class="text-sm text-ink-500">{{ $ticket->ticket_no }} · {{ $ticket->name }} ({{ $ticket->email }}) · Priority: {{ ucfirst($ticket->priority) }}</p>
        </div>
        <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST" class="flex items-end gap-2">
            @csrf
            <div>
                <label class="label">Status</label>
                <select name="status" class="input !py-1.5">
                    @foreach (['open', 'in_progress', 'waiting', 'resolved', 'closed'] as $s)
                        <option value="{{ $s }}" @selected($ticket->status === $s)>{{ label_case($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Assign To</label>
                <select name="assigned_to" class="input !py-1.5">
                    <option value="">—</option>
                    @foreach (\App\Models\Admin::where('status', 'active')->get() as $admin)
                        <option value="{{ $admin->id }}" @selected($ticket->assigned_to === $admin->id)>{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-primary btn-sm">Update</button>
        </form>
    </div>

    {{-- Conversation --}}
    <div class="mt-4 space-y-3">
        @foreach ($ticket->allMessages as $message)
            <div class="admin-card max-w-3xl {{ $message->sender_type === 'admin' ? 'ml-auto border-l-4 border-brand-500' : '' }}">
                <div class="flex items-center justify-between text-xs text-ink-500">
                    <span class="font-bold uppercase {{ $message->sender_type === 'admin' ? 'text-brand-600' : 'text-ink-700' }}">
                        {{ $message->sender_type === 'admin' ? 'Staff' : 'Customer' }}
                        @if ($message->is_internal_note) · <span class="text-amber-600">Internal Note</span> @endif
                    </span>
                    <span>{{ $message->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-ink-700">{{ $message->message }}</p>
            </div>
        @endforeach
    </div>

    <form action="{{ route('admin.tickets.reply', $ticket) }}" method="POST" class="admin-card mt-4 max-w-3xl">
        @csrf
        <textarea name="message" rows="4" class="input" placeholder="Write a reply…" required></textarea>
        <div class="mt-3 flex items-center justify-between">
            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" name="internal_note" value="1" class="accent-brand-600"> Internal note (not visible to customer)
            </label>
            <button class="btn-primary btn-md">Send Reply</button>
        </div>
    </form>
@endsection
