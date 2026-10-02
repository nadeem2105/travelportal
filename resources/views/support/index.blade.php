@extends('layouts.site')

@section('page')
<section class="shell max-w-5xl pt-28">
    <h1 class="font-display text-3xl font-bold">Support Center</h1>
    <p class="section-sub mt-1">We're always here for you</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
        <form action="{{ route('support.store') }}" method="POST" class="card space-y-4 p-6">
            @csrf
            <h2 class="font-display text-lg font-bold">Raise a Ticket</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Name</label>
                    <input type="text" name="name" class="input" value="{{ old('name', auth('web')->user()?->name) }}" required>
                </div>
                <div>
                    <label class="label">Email</label>
                    <input type="email" name="email" class="input" value="{{ old('email', auth('web')->user()?->email) }}" required>
                </div>
                <div>
                    <label class="label">Booking Reference (optional)</label>
                    <input type="text" name="booking_reference" class="input uppercase" value="{{ old('booking_reference') }}">
                </div>
                <div>
                    <label class="label">Priority</label>
                    <select name="priority" class="input">
                        @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $k => $label)
                            <option value="{{ $k }}" @selected(old('priority', 'medium'))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="label">Subject</label>
                <input type="text" name="subject" class="input" value="{{ old('subject') }}" required>
            </div>
            <div>
                <label class="label">Describe your issue</label>
                <textarea name="message" rows="5" class="input" required>{{ old('message') }}</textarea>
            </div>
            <button class="btn-primary btn-lg">Submit Ticket</button>
        </form>

        @if (auth('web')->check())
            <div class="card h-fit p-6">
                <h2 class="font-display text-lg font-bold">My Tickets</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($tickets as $ticket)
                        <div class="rounded-xl border border-slate-100 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-bold text-ink-900">{{ $ticket->ticket_no }}</p>
                                <span class="status-pill {{ status_pill_class($ticket->status) }}">{{ label_case($ticket->status) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-ink-500">{{ $ticket->subject }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500">No tickets yet.</p>
                    @endforelse
                </div>
            </div>
        @else
            <div class="card h-fit p-6">
                <h2 class="font-display text-lg font-bold">Quick Answers</h2>
                <p class="mt-2 text-sm text-ink-500">Check the FAQ for instant answers about bookings, cancellations and refunds.</p>
                <a href="{{ route('faq') }}" class="btn-ghost btn-md mt-3 w-full text-center">Browse FAQs</a>
            </div>
        @endif
    </div>
</section>
@endsection
