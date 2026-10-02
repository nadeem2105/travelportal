@extends('layouts.admin')
@section('pageTitle', 'Support Tickets')

@section('content')
    <h1 class="font-display text-xl font-bold">Support Tickets</h1>

    <x-admin.filters
        :action="route('admin.tickets.index')"
        search-placeholder="Search subject…"
        :filters="[['name' => 'status', 'label' => 'Status', 'options' => ['open' => 'Open', 'in_progress' => 'In Progress', 'waiting' => 'Waiting', 'resolved' => 'Resolved', 'closed' => 'Closed']]]"
        :count="$tickets->total()" />

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Priority</th><th>Status</th><th>Assigned</th><th class="text-right">Date</th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td><a href="{{ route('admin.tickets.show', $ticket) }}" class="font-bold text-brand-600">{{ $ticket->ticket_no }}</a></td>
                        <td class="max-w-[240px] truncate">{{ $ticket->subject }}</td>
                        <td>{{ $ticket->name }}</td>
                        <td>
                            <span class="status-pill {{ match ($ticket->priority) { 'urgent' => 'bg-rose-100 text-rose-700', 'high' => 'bg-orange-100 text-orange-700', 'medium' => 'bg-amber-100 text-amber-700', default => 'bg-slate-100 text-slate-600' } }}">{{ ucfirst($ticket->priority) }}</span>
                        </td>
                        <td><span class="status-pill {{ status_pill_class($ticket->status) }}">{{ label_case($ticket->status) }}</span></td>
                        <td>{{ $ticket->assignee?->name ?? '—' }}</td>
                        <td class="text-right text-xs text-ink-500">{{ $ticket->created_at->format('d M, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No tickets</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>
@endsection
