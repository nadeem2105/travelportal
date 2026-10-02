@extends('layouts.admin')
@section('pageTitle', 'Contact Messages')

@section('content')
    <h1 class="font-display text-xl font-bold">Contact Messages</h1>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Status</th><th class="text-right">Date</th></tr></thead>
            <tbody>
                @forelse ($messages as $message)
                    <tr>
                        <td class="font-bold">{{ $message->name }}</td>
                        <td>{{ $message->email }}</td>
                        <td>{{ $message->subject }}</td>
                        <td class="max-w-[280px] truncate text-ink-500">{{ $message->message }}</td>
                        <td>
                            <span class="status-pill {{ $message->is_read ? 'bg-slate-100 text-slate-600' : 'bg-brand-100 text-brand-700' }}">
                                {{ $message->is_read ? 'Read' : 'New' }}
                            </span>
                        </td>
                        <td class="text-right text-xs text-ink-500">{{ $message->created_at->format('d M, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-ink-500">No messages</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $messages->links() }}</div>
@endsection
