@extends('layouts.site')

@section('page')
<x-account.shell :accountNav="'notifications'" :unread="auth('web')->user()->unreadNotificationsCount()">
    <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold">Notifications</h1>
        @if (auth('web')->user()->unreadNotificationsCount())
            <form action="{{ route('account.notifications.read_all') }}" method="POST">
                @csrf
                <button class="btn-ghost btn-sm">Mark all read</button>
            </form>
        @endif
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($notifications as $notification)
            <div class="card flex gap-4 p-5 {{ $notification->read_at ? 'opacity-70' : '' }}">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                    @if ($notification->type === 'booking') ✓
                    @elseif ($notification->type === 'cancellation') ✕
                    @else 🔔
                    @endif
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-ink-900">{{ $notification->title }}</p>
                    <p class="mt-0.5 text-sm text-ink-700">{{ $notification->body }}</p>
                    <p class="mt-1 text-xs text-ink-300">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
            </div>
        @empty
            <div class="card p-12 text-center text-sm text-ink-500">You're all caught up!</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $notifications->links() }}</div>
</x-account.shell>
@endsection
