@extends('layouts.admin')
@section('pageTitle', $contact->name . ' · Customer 360')

@php
    $lifecyclePill = [
        'lead' => 'bg-slate-100 text-slate-600', 'prospect' => 'bg-sky-100 text-sky-700',
        'qualified' => 'bg-indigo-100 text-indigo-700', 'customer' => 'bg-emerald-100 text-emerald-700',
        'repeat' => 'bg-teal-100 text-teal-700', 'vip' => 'bg-amber-100 text-amber-700',
        'inactive' => 'bg-rose-100 text-rose-700',
    ];
    $activityIcons = [
        'lead_created' => '✨', 'stage_changed' => '🔀', 'assigned' => '👤', 'note' => '📝',
        'call' => '📞', 'whatsapp_in' => '💬', 'whatsapp_out' => '💬', 'email' => '✉️',
        'quotation_sent' => '📄', 'quotation_created' => '📄', 'payment_received' => '💰',
        'booking_confirmed' => '✅', 'task_created' => '🗒️', 'task_completed' => '☑️',
    ];
    $optIns = [
        'Marketing' => $contact->marketing_opt_in, 'WhatsApp' => $contact->whatsapp_opt_in,
        'Email' => $contact->email_opt_in, 'SMS' => $contact->sms_opt_in,
    ];
@endphp

@section('content')
<div x-data="{ editOpen: {{ $errors->any() ? 'true' : 'false' }} }">
    <a href="{{ route('admin.contacts.index') }}" class="text-xs text-brand-600 hover:underline">← Back to Contacts</a>

    @if (session('success'))<div class="mt-2 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="mt-2 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="mt-2 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Header --}}
    <div class="admin-card mt-2 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-xl font-bold">{{ $contact->name }}</h1>
                <div class="mt-1 text-xs text-ink-500">
                    Phone: {{ $contact->phone ?? '—' }}
                    @if ($contact->alternate_phone) · Alt: {{ $contact->alternate_phone }} @endif
                    · Email: {{ $contact->email ?? '—' }}
                    @if ($contact->city || $contact->country) · {{ collect([$contact->city, $contact->state, $contact->country])->filter()->implode(', ') }} @endif
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="status-pill capitalize {{ $lifecyclePill[$contact->lifecycle_stage] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case((string) $contact->lifecycle_stage) }}</span>
                    <span class="status-pill bg-slate-100 text-slate-700">Owner: {{ $contact->assignee?->name ?? 'Unassigned' }}</span>
                    @if ($contact->leadSource)<span class="status-pill bg-slate-100 text-slate-700">Source: {{ $contact->leadSource->name }}</span>@endif
                    @if ($contact->user)<span class="status-pill bg-emerald-100 text-emerald-700">Registered customer</span>@endif
                </div>
            </div>

            <div class="flex items-center gap-2">
            {{-- Edit --}}
            <button type="button" @click="editOpen = true" class="btn-primary btn-sm">✎ Edit</button>

            {{-- Merge --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="btn-ghost btn-sm">⤵ Merge duplicate</button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 top-10 z-20 w-80 rounded-2xl bg-white p-4 shadow-float ring-1 ring-slate-900/10">
                    <p class="mb-2 text-xs text-ink-500">Merge another contact <strong>into</strong> {{ $contact->name }}. Leads, activities, tasks, and tags move over; the other contact is archived.</p>
                    <form action="{{ route('admin.contacts.merge', $contact) }}" method="POST" class="space-y-2"
                          onsubmit="return confirm('Merge the selected contact into {{ addslashes($contact->name) }}? This cannot be undone.');">
                        @csrf
                        <select name="secondary_id" class="input text-xs" required>
                            <option value="">Select contact to merge…</option>
                            @foreach ($mergeCandidates as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} — {{ $c->phone ?? $c->email ?? ('#' . $c->id) }}</option>
                            @endforeach
                        </select>
                        <button class="btn-primary btn-sm w-full">Merge into this contact</button>
                    </form>
                </div>
            </div>
            </div>{{-- /actions --}}
        </div>
    </div>

    {{-- Edit contact modal --}}
    <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8" @keydown.escape.window="editOpen = false">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl" @click.outside="editOpen = false">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="font-display text-lg font-bold">Edit Contact</h2>
                <button type="button" @click="editOpen = false" class="text-ink-400 hover:text-ink-700">&times;</button>
            </div>
            <form action="{{ route('admin.contacts.update', $contact) }}" method="POST" class="px-6 py-5">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">Full Name *</label>
                        <input type="text" name="name" value="{{ old('name', $contact->name) }}" required class="input">
                    </div>
                    <div>
                        <label class="label">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $contact->phone) }}" class="input">
                    </div>
                    <div>
                        <label class="label">Alternate Phone</label>
                        <input type="text" name="alternate_phone" value="{{ old('alternate_phone', $contact->alternate_phone) }}" class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Email</label>
                        <input type="email" name="email" value="{{ old('email', $contact->email) }}" class="input">
                    </div>
                    <div>
                        <label class="label">City</label>
                        <input type="text" name="city" value="{{ old('city', $contact->city) }}" class="input">
                    </div>
                    <div>
                        <label class="label">State</label>
                        <input type="text" name="state" value="{{ old('state', $contact->state) }}" class="input">
                    </div>
                    <div>
                        <label class="label">Country</label>
                        <input type="text" name="country" value="{{ old('country', $contact->country) }}" class="input">
                    </div>
                    <div>
                        <label class="label">Lifecycle Stage</label>
                        <select name="lifecycle_stage" class="input">
                            @foreach ($lifecycleStages as $s)
                                <option value="{{ $s }}" @selected(old('lifecycle_stage', $contact->lifecycle_stage) === $s)>{{ label_case($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Address</label>
                        <textarea name="address" rows="2" class="input">{{ old('address', $contact->address) }}</textarea>
                    </div>
                    <div>
                        <label class="label">Owner</label>
                        <select name="assigned_user_id" class="input">
                            <option value="">Unassigned</option>
                            @foreach ($staff as $s)
                                <option value="{{ $s->id }}" @selected((string) old('assigned_user_id', $contact->assigned_user_id) === (string) $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Source</label>
                        <select name="source_id" class="input">
                            <option value="">—</option>
                            @foreach ($sources as $src)
                                <option value="{{ $src->id }}" @selected((string) old('source_id', $contact->source_id) === (string) $src->id)>{{ $src->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Notes</label>
                        <textarea name="notes" rows="2" class="input">{{ old('notes', $contact->notes) }}</textarea>
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="whatsapp_opt_in" value="1" @checked(old('whatsapp_opt_in', $contact->whatsapp_opt_in)) class="rounded"> WhatsApp</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in', $contact->marketing_opt_in)) class="rounded"> Marketing</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="email_opt_in" value="1" @checked(old('email_opt_in', $contact->email_opt_in)) class="rounded"> Email</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="sms_opt_in" value="1" @checked(old('sms_opt_in', $contact->sms_opt_in)) class="rounded"> SMS</label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="editOpen = false" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Main --}}
        <div class="space-y-4 lg:col-span-2">
            {{-- Leads --}}
            <div class="admin-card p-4">
                <h3 class="mb-3 text-sm font-bold">Leads ({{ $contact->leads->count() }})</h3>
                <div class="space-y-2">
                    @forelse ($contact->leads as $lead)
                        <div class="flex items-center justify-between gap-3 rounded-lg border p-3 text-xs">
                            <div class="min-w-0">
                                <a href="{{ route('admin.crm.show', $lead) }}" class="font-mono font-semibold text-brand-600 hover:underline">{{ $lead->lead_number ?? ('#' . $lead->id) }}</a>
                                <span class="text-ink-600"> · {{ $lead->destination ?? 'General enquiry' }}</span>
                                <div class="mt-0.5 text-[11px] text-ink-400">{{ $lead->assignee?->name ?? 'Unassigned' }} · {{ $lead->created_at?->format('d M Y') }}</div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($lead->stage)<span class="status-pill bg-indigo-100 text-indigo-700">{{ $lead->stage->name }}</span>@endif
                                <span class="status-pill {{ status_pill_class($lead->status) }}">{{ label_case($lead->status) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="py-2 text-xs text-ink-400">No leads for this contact.</p>
                    @endforelse
                </div>
            </div>

            {{-- Quotations --}}
            <div class="admin-card p-4">
                <h3 class="mb-3 text-sm font-bold">Quotations ({{ $quotations->count() }})</h3>
                <div class="space-y-2">
                    @forelse ($quotations as $q)
                        <div class="flex items-center justify-between gap-3 rounded-lg border p-3 text-xs">
                            <div class="min-w-0">
                                <div class="font-semibold text-ink-800">{{ $q->title }}</div>
                                <div class="font-mono text-[11px] text-ink-400">{{ $q->quotation_number }}</div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2 text-right">
                                <span class="text-sm font-bold text-emerald-600">₹{{ number_format($q->total_amount, 2) }}</span>
                                <span class="status-pill bg-blue-100 capitalize text-blue-700">{{ $q->status }}</span>
                                <a href="{{ route('admin.crm.quotation.pdf', $q) }}" target="_blank" class="btn-ghost btn-xs text-brand-700 hover:bg-brand-50">📄</a>
                            </div>
                        </div>
                    @empty
                        <p class="py-2 text-xs text-ink-400">No quotations yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Bookings (only when linked to a registered user) --}}
            @if ($contact->user)
                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Bookings ({{ $contact->user->bookings->count() }})</h3>
                    <div class="space-y-2">
                        @forelse ($contact->user->bookings as $booking)
                            <div class="flex items-center justify-between gap-3 rounded-lg border p-3 text-xs">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.bookings.show', $booking) }}" class="font-mono font-semibold text-brand-600 hover:underline">{{ $booking->booking_reference }}</a>
                                    <div class="mt-0.5 text-[11px] capitalize text-ink-400">{{ label_case((string) $booking->product_type) }} · {{ $booking->created_at?->format('d M Y') }}</div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2 text-right">
                                    <span class="text-sm font-bold text-ink-800">₹{{ number_format((float) $booking->total_amount, 2) }}</span>
                                    <span class="status-pill {{ status_pill_class((string) $booking->status) }}">{{ label_case((string) $booking->status) }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="py-2 text-xs text-ink-400">No bookings yet.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            {{-- Activity timeline --}}
            <div class="admin-card p-4">
                <h3 class="mb-3 text-sm font-bold">Activity Timeline</h3>
                <div class="space-y-3">
                    @forelse ($contact->activities as $activity)
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm">{{ $activityIcons[$activity->type] ?? '•' }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-ink-800">{{ $activity->title }}</span>
                                    <span class="text-[11px] text-ink-400">{{ $activity->occurred_at?->format('d M Y, h:i A') }}</span>
                                </div>
                                @if ($activity->description)<p class="mt-0.5 whitespace-pre-line text-xs text-ink-600">{{ $activity->description }}</p>@endif
                                <div class="mt-0.5 text-[11px] text-ink-400">{{ $activity->performer?->name ?? 'System' }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="py-2 text-xs text-ink-400">No activity recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            {{-- Opt-ins --}}
            <div class="admin-card p-4">
                <h3 class="mb-3 text-sm font-bold">Consent &amp; Opt-ins</h3>
                <div class="space-y-1.5 text-xs">
                    @foreach ($optIns as $label => $on)
                        <div class="flex items-center justify-between">
                            <span class="text-ink-500">{{ $label }}</span>
                            <span class="status-pill {{ $on ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $on ? 'Opted in' : 'No' }}</span>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-ink-500">Preferred channel</span>
                        <span class="font-medium capitalize">{{ $contact->preferred_contact_channel ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-ink-500">Language</span>
                        <span class="font-medium">{{ $contact->preferred_language ?? '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- Tasks --}}
            <div class="admin-card p-4">
                <h3 class="mb-3 text-sm font-bold">Tasks ({{ $contact->tasks->count() }})</h3>
                <div class="space-y-2">
                    @forelse ($contact->tasks->sortBy('due_at') as $task)
                        <div class="flex items-center justify-between gap-2 rounded-lg border p-2 text-xs {{ $task->status === 'completed' ? 'opacity-60' : '' }}">
                            <div class="min-w-0">
                                <div class="font-semibold text-ink-800">{{ $task->title }}</div>
                                @if ($task->due_at)<div class="text-[11px] text-ink-400">⏰ {{ $task->due_at->format('d M Y') }}</div>@endif
                            </div>
                            @if ($task->status === 'completed')
                                <span class="status-pill bg-emerald-100 text-emerald-700">Done</span>
                            @else
                                <form action="{{ route('admin.crm-tasks.complete', $task) }}" method="POST">
                                    @csrf
                                    <button class="btn-ghost btn-xs text-emerald-700 hover:bg-emerald-50">✓</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="py-2 text-xs text-ink-400">No tasks.</p>
                    @endforelse
                </div>
            </div>

            {{-- Profile notes --}}
            @if ($contact->notes)
                <div class="admin-card p-4">
                    <h3 class="mb-2 text-sm font-bold">Notes</h3>
                    <p class="whitespace-pre-line text-xs text-ink-600">{{ $contact->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>{{-- /x-data editOpen --}}
@endsection
