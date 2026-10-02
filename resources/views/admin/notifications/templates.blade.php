@extends('layouts.admin')
@section('pageTitle', 'Notification Templates')

@section('content')
    <h1 class="font-display text-xl font-bold">Email &amp; SMS Templates</h1>
    <p class="mt-1 text-sm text-ink-500">Available variables: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{name} {booking_id} {amount} {destination} {travel_date} {ticket_url} {invoice_url} {status} {otp} {refund_days}</code></p>

    <div class="mt-4 space-y-3">
        @foreach ($templates as $template)
            <details class="admin-card">
                <summary class="flex cursor-pointer list-none items-center justify-between">
                    <span class="font-display font-bold">{{ $template->name }}
                        <span class="ml-1 font-mono text-xs font-medium text-ink-500">{{ $template->key }}</span>
                    </span>
                    <span class="status-pill {{ $template->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $template->is_active ? 'Active' : 'Off' }}</span>
                </summary>
                <form action="{{ route('admin.notification-templates.update', $template) }}" method="POST" class="mt-3">
                    @csrf @method('PUT')
                    <div class="space-y-3">
                        <div><label class="label">Subject</label><input type="text" name="subject" class="input" value="{{ $template->subject }}"></div>
                        <div><label class="label">Body</label><textarea name="body" rows="7" class="input font-mono text-xs">{{ $template->body }}</textarea></div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" value="1" class="accent-brand-600" @checked($template->is_active)> Active
                        </label>
                    </div>
                    <button class="btn-primary btn-sm mt-3">Save Template</button>
                </form>
            </details>
        @endforeach
    </div>
@endsection
