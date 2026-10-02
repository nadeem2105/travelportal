@extends('layouts.admin')
@section('pageTitle', ($lead->contact?->name ?? $lead->name) . ' · CRM Lead')

@php
    $priorityPill = [
        'low' => 'bg-slate-100 text-slate-600', 'medium' => 'bg-sky-100 text-sky-700',
        'high' => 'bg-amber-100 text-amber-700', 'urgent' => 'bg-rose-100 text-rose-700',
    ];
    $activityIcons = [
        'lead_created' => '✨', 'stage_changed' => '🔀', 'assigned' => '👤', 'note' => '📝',
        'call' => '📞', 'whatsapp_in' => '💬', 'whatsapp_out' => '💬', 'email' => '✉️',
        'quotation_sent' => '📄', 'quotation_created' => '📄', 'payment_received' => '💰',
        'booking_confirmed' => '✅', 'task_created' => '🗒️', 'task_completed' => '☑️',
    ];
    $contactName = $lead->contact?->name ?? $lead->name;
    $contactPhone = $lead->contact?->phone ?? $lead->phone;
    $contactEmail = $lead->contact?->email ?? $lead->email;
@endphp

@section('content')
    <a href="{{ route('admin.crm.index') }}" class="text-xs text-brand-600 hover:underline">← Back to CRM Leads</a>

    {{-- Header --}}
    <div class="admin-card mt-2 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-display text-xl font-bold">{{ $contactName }}</h1>
                    @if ($lead->contact)
                        <a href="{{ route('admin.contacts.show', $lead->contact) }}" class="text-[11px] text-brand-600 hover:underline">(Customer 360 →)</a>
                    @endif
                </div>
                <div class="mt-1 text-xs text-ink-500">
                    <span class="font-mono font-semibold text-ink-700">{{ $lead->lead_number ?? ('#' . $lead->id) }}</span>
                    · Phone: {{ $contactPhone ?? '—' }}
                    · Email: {{ $contactEmail ?? '—' }}
                    · Destination: {{ $lead->destination ?? 'General' }}
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($lead->stage)
                        <span class="status-pill bg-indigo-100 text-indigo-700">Stage: {{ $lead->stage->name }}</span>
                    @endif
                    <span class="status-pill {{ status_pill_class($lead->status) }}">{{ label_case($lead->status) }}</span>
                    <span class="status-pill capitalize {{ $priorityPill[$lead->priority] ?? 'bg-slate-100 text-slate-600' }}">{{ $lead->priority ?? 'medium' }} priority</span>
                    <span class="status-pill bg-emerald-100 text-emerald-700">Score: {{ $lead->score ?? 0 }}</span>
                    <span class="status-pill bg-slate-100 text-slate-700">Agent: {{ $lead->assignee?->name ?? 'Unassigned' }}</span>
                </div>
            </div>

            <div class="flex flex-col items-stretch gap-2">
                {{-- Change Stage --}}
                @if ($stages->isNotEmpty())
                    <form action="{{ route('admin.crm.lead.stage', $lead) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        <select name="stage_id" class="input py-1 text-xs">
                            @foreach ($stages as $st)
                                <option value="{{ $st->id }}" {{ (int) $lead->stage_id === (int) $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn-primary btn-sm whitespace-nowrap">Move Stage</button>
                    </form>
                @endif

                {{-- Status + assignment --}}
                <form action="{{ route('admin.crm.lead.status', $lead) }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <select name="status" class="input py-1 text-xs">
                        @foreach (['new', 'contacted', 'quotation_sent', 'negotiating', 'converted', 'lost'] as $st)
                            <option value="{{ $st }}" {{ $lead->status === $st ? 'selected' : '' }}>{{ label_case($st) }}</option>
                        @endforeach
                    </select>
                    <select name="assigned_to" class="input py-1 text-xs">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $s)
                            <option value="{{ $s->id }}" {{ $lead->assigned_to == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn-ghost btn-sm whitespace-nowrap">Update</button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3" x-data="{ tab: 'timeline' }">
        {{-- Main column --}}
        <div class="space-y-4 lg:col-span-2">
            {{-- Tabs --}}
            <div class="flex flex-wrap gap-2">
                <button @click="tab = 'timeline'" :class="tab === 'timeline' ? 'btn-primary' : 'btn-ghost'" class="btn-sm">Timeline</button>
                <button @click="tab = 'tasks'" :class="tab === 'tasks' ? 'btn-primary' : 'btn-ghost'" class="btn-sm">Tasks ({{ $lead->tasks->count() }})</button>
                <button @click="tab = 'followups'" :class="tab === 'followups' ? 'btn-primary' : 'btn-ghost'" class="btn-sm">Follow-ups</button>
                <button @click="tab = 'quotations'" :class="tab === 'quotations' ? 'btn-primary' : 'btn-ghost'" class="btn-sm">Quotations</button>
            </div>

            {{-- Timeline tab --}}
            <div x-show="tab === 'timeline'" x-cloak class="space-y-4">
                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Add Note</h3>
                    <form action="{{ route('admin.crm.note.store', $lead) }}" method="POST" class="space-y-3">
                        @csrf
                        <textarea name="note" rows="2" class="input" required placeholder="Internal note — logged to the activity timeline…"></textarea>
                        <button class="btn-primary btn-sm">Add Note</button>
                    </form>
                </div>

                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Activity Timeline</h3>
                    <div class="space-y-3">
                        @forelse ($lead->activities as $activity)
                            <div class="flex gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm">{{ $activityIcons[$activity->type] ?? '•' }}</div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm font-semibold text-ink-800">{{ $activity->title }}</span>
                                        <span class="text-[11px] text-ink-400">{{ $activity->occurred_at?->format('d M Y, h:i A') }}</span>
                                    </div>
                                    @if ($activity->description)
                                        <p class="mt-0.5 whitespace-pre-line text-xs text-ink-600">{{ $activity->description }}</p>
                                    @endif
                                    <div class="mt-0.5 text-[11px] text-ink-400">
                                        {{ $activity->performer?->name ?? 'System' }}
                                        @if ($activity->is_internal) · <span class="text-amber-600">internal</span> @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="py-2 text-xs text-ink-400">No activity recorded yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Tasks tab --}}
            <div x-show="tab === 'tasks'" x-cloak class="space-y-4">
                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Create Task</h3>
                    <form action="{{ route('admin.crm-tasks.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                        @if ($lead->contact_id)<input type="hidden" name="contact_id" value="{{ $lead->contact_id }}">@endif
                        <div>
                            <label class="label">Title *</label>
                            <input type="text" name="title" class="input" required placeholder="e.g. Call customer to confirm dates">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Type</label>
                                <select name="type" class="input">
                                    @foreach (['follow_up', 'call', 'whatsapp', 'email', 'quotation', 'payment_reminder', 'document', 'booking_confirmation', 'post_trip', 'other'] as $t)
                                        <option value="{{ $t }}">{{ label_case($t) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label">Priority</label>
                                <select name="priority" class="input">
                                    @foreach (['low', 'medium', 'high', 'urgent'] as $p)
                                        <option value="{{ $p }}" {{ $p === 'medium' ? 'selected' : '' }}>{{ label_case($p) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Due</label>
                                <input type="datetime-local" name="due_at" class="input text-xs">
                            </div>
                            <div>
                                <label class="label">Assign To</label>
                                <select name="assigned_user_id" class="input">
                                    <option value="">Me</option>
                                    @foreach ($staff as $s)
                                        <option value="{{ $s->id }}" {{ $lead->assigned_to == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button class="btn-primary btn-sm">Create Task</button>
                    </form>
                </div>

                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Tasks</h3>
                    <div class="space-y-2">
                        @forelse ($lead->tasks->sortBy('due_at') as $task)
                            <div class="flex items-center justify-between gap-3 rounded-lg border p-3 text-xs {{ $task->status === 'completed' ? 'opacity-60' : '' }}">
                                <div class="min-w-0">
                                    <div class="font-semibold text-ink-800">{{ $task->title }}</div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-ink-400">
                                        <span class="capitalize">{{ label_case($task->type) }}</span>
                                        <span class="status-pill capitalize {{ $priorityPill[$task->priority] ?? 'bg-slate-100 text-slate-600' }}">{{ $task->priority }}</span>
                                        @if ($task->due_at)
                                            <span class="{{ $task->status !== 'completed' && $task->due_at->isPast() ? 'font-semibold text-rose-600' : '' }}">⏰ {{ $task->due_at->format('d M Y, h:i A') }}</span>
                                        @endif
                                        <span>· {{ $task->assignee?->name ?? 'Unassigned' }}</span>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    @if ($task->status === 'completed')
                                        <span class="status-pill bg-emerald-100 text-emerald-700">Completed</span>
                                    @else
                                        <form action="{{ route('admin.crm-tasks.complete', $task) }}" method="POST">
                                            @csrf
                                            <button class="btn-ghost btn-xs text-emerald-700 hover:bg-emerald-50">✓ Complete</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-2 text-xs text-ink-400">No tasks for this lead yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Follow-ups tab --}}
            <div x-show="tab === 'followups'" x-cloak class="space-y-4">
                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Log Follow-up / Activity</h3>
                    <form action="{{ route('admin.crm.followup.store', $lead) }}" method="POST" class="space-y-3">
                        @csrf
                        <textarea name="note" class="input" rows="3" required placeholder="Enter follow-up conversation notes, customer feedback..."></textarea>
                        <div>
                            <label class="label">Schedule Next Follow-up Reminder</label>
                            <input type="datetime-local" name="scheduled_at" class="input text-xs">
                        </div>
                        <button class="btn-primary btn-sm">Add Follow-up</button>
                    </form>
                </div>

                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Follow-up History</h3>
                    <div class="space-y-3">
                        @forelse ($lead->followUps as $fu)
                            <div class="rounded-lg bg-slate-50 p-3 text-xs">
                                <div class="flex justify-between font-bold text-ink-700">
                                    <span>{{ $fu->staff?->name ?? 'Admin Staff' }}</span>
                                    <span class="font-normal text-ink-400">{{ $fu->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-ink-600">{{ $fu->note }}</p>
                                @if ($fu->scheduled_at)
                                    <div class="mt-1 text-[11px] font-medium text-amber-600">⏰ Next Reminder: {{ $fu->scheduled_at->format('d M Y, h:i A') }}</div>
                                @endif
                            </div>
                        @empty
                            <p class="py-2 text-xs text-ink-400">No follow-up notes logged yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Quotations tab --}}
            <div x-show="tab === 'quotations'" x-cloak class="space-y-4">
                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Create Quotation</h3>
                    <form action="{{ route('admin.crm.quotation.store', $lead) }}" method="POST" class="space-y-3" x-data="{ packageId: '' }">
                        @csrf
                        <div>
                            <label class="label">Quotation Title *</label>
                            <input type="text" name="title" class="input" required placeholder="e.g. 5D/4N Kashmir Honeymoon Special">
                        </div>
                        <div>
                            <label class="label">Link Package (Optional)</label>
                            <select name="package_id" class="input" x-model="packageId">
                                <option value="">None / Custom Tour</option>
                                @foreach ($packages as $pkg)
                                    <option value="{{ $pkg->id }}">{{ $pkg->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2" x-data="{ stays: [{ hotel_id: '', location: '', nights: '' }] }">
                                <label class="label">Hotels (Optional) — add one per location / night</label>
                                <div class="space-y-2">
                                    <template x-for="(stay, i) in stays" :key="i">
                                        <div class="grid grid-cols-12 items-center gap-2">
                                            <select class="input col-span-6" x-model="stay.hotel_id" :name="`hotels[${i}][hotel_id]`">
                                                <option value="">Select hotel</option>
                                                @foreach ($hotels as $hotel)
                                                    <option value="{{ $hotel->id }}">{{ $hotel->name }}@if ($hotel->city) — {{ $hotel->city }}@endif</option>
                                                @endforeach
                                            </select>
                                            <input type="text" class="input col-span-4" x-model="stay.location" :name="`hotels[${i}][location]`" placeholder="Location (e.g. Gulmarg)">
                                            <input type="number" min="0" max="60" class="input col-span-1" x-model="stay.nights" :name="`hotels[${i}][nights]`" placeholder="Nts" title="Nights">
                                            <button type="button" class="btn-ghost btn-xs col-span-1 text-rose-600 hover:bg-rose-50" x-on:click="stays.splice(i, 1)" x-show="stays.length > 1" title="Remove this hotel">✕</button>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" class="btn-ghost btn-xs mt-2 text-brand-700 hover:bg-brand-50" x-on:click="stays.push({ hotel_id: '', location: '', nights: '' })">+ Add another hotel</button>
                                <p class="mt-1 text-[11px] text-ink-400">For multi-city trips, add a hotel row per stop (e.g. Srinagar 2N, Gulmarg 1N, Pahalgam 1N). Leave empty to skip hotels.</p>
                            </div>
                            <div>
                                <label class="label">Cab / Vehicle (Optional)</label>
                                <select name="vehicle_id" class="input">
                                    <option value="">No cab</option>
                                    @foreach ($cabs as $cab)
                                        <option value="{{ $cab->id }}">{{ $cab->name }}@if ($cab->type) ({{ $cab->type->name }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label">Pick-up Location (Optional)</label>
                                <input type="text" name="pickup_location" class="input" placeholder="e.g. Srinagar Airport">
                            </div>
                            <div>
                                <label class="label">Drop-off Location (Optional)</label>
                                <input type="text" name="dropoff_location" class="input" placeholder="e.g. Srinagar Hotel">
                            </div>
                        </div>
                        <template x-if="packageId">
                            <div class="rounded-lg border border-brand-100 bg-brand-50/60 p-3 text-[12px] text-brand-700">
                                Using the linked package's day-by-day itinerary. Choose <strong>None / Custom Tour</strong> above to build a custom itinerary instead.
                            </div>
                        </template>
                        <template x-if="!packageId">
                            <div x-data="{ days: [{ title: '', description: '', stay: '' }] }">
                                <label class="label">Day-by-Day Itinerary (Custom Tour) — note the overnight stay per day</label>
                                <div class="space-y-2">
                                    <template x-for="(day, i) in days" :key="i">
                                        <div class="rounded-lg border border-ink-100 p-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded bg-brand-600 px-1.5 text-[11px] font-semibold text-white" x-text="'Day ' + (i + 1)"></span>
                                                <input type="text" class="input flex-1" x-model="day.title" :name="`itinerary[${i}][title]`" placeholder="Title (e.g. Arrival in Srinagar)">
                                                <button type="button" class="btn-ghost btn-xs text-rose-600 hover:bg-rose-50" x-on:click="days.splice(i, 1)" x-show="days.length > 1" title="Remove this day">✕</button>
                                            </div>
                                            <textarea class="input mt-2" rows="2" x-model="day.description" :name="`itinerary[${i}][description]`" placeholder="What happens this day (sightseeing, transfers, activities)..."></textarea>
                                            <input type="text" class="input mt-2" x-model="day.stay" :name="`itinerary[${i}][stay]`" placeholder="Overnight stay (e.g. Houseboat, Dal Lake / Hotel Grand, Gulmarg)">
                                        </div>
                                    </template>
                                </div>
                                <button type="button" class="btn-ghost btn-xs mt-2 text-brand-700 hover:bg-brand-50" x-on:click="days.push({ title: '', description: '', stay: '' })">+ Add another day</button>
                                <p class="mt-1 text-[11px] text-ink-400">Add a row per day and mention where the traveller stays that night. This shows only for custom tours.</p>
                            </div>
                        </template>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Subtotal (₹) *</label>
                                <input type="number" step="0.01" name="subtotal" class="input" required min="0">
                            </div>
                            <div>
                                <label class="label">Tax (₹)</label>
                                <input type="number" step="0.01" name="tax_amount" class="input" value="0">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Travel Start Date</label>
                                <input type="date" name="travel_date" class="input"
                                    value="{{ optional($lead->travel_start_date)->format('Y-m-d') }}">
                                <p class="mt-1 text-[11px] text-ink-400">Arrival date — sets hotel check-in/out & pickup dates when converted to a booking.</p>
                            </div>
                            <div>
                                <label class="label">Valid Until</label>
                                <input type="date" name="valid_until" class="input">
                            </div>
                        </div>
                        <div>
                            <label class="label">Travellers</label>
                            <div class="grid grid-cols-4 gap-2">
                                <input type="number" name="adults" min="0" max="99" class="input" placeholder="Adults" value="{{ $lead->adults ?? $lead->travellers_count }}" title="Adults">
                                <input type="number" name="children" min="0" max="99" class="input" placeholder="Children" value="{{ $lead->children }}" title="Children">
                                <input type="number" name="infants" min="0" max="99" class="input" placeholder="Infants" value="{{ $lead->infants }}" title="Infants">
                                <input type="number" name="rooms" min="0" max="99" class="input" placeholder="Rooms" value="{{ $lead->rooms }}" title="Rooms">
                            </div>
                            <p class="mt-1 text-[11px] text-ink-400">Adults · Children · Infants · Rooms (pre-filled from the lead's trip details).</p>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label">Inclusions</label>
                                <textarea name="inclusions_raw" rows="4" class="input" placeholder="One per line, e.g.&#10;Accommodation on twin-sharing&#10;Daily breakfast&#10;Airport transfers"></textarea>
                            </div>
                            <div>
                                <label class="label">Exclusions</label>
                                <textarea name="exclusions_raw" rows="4" class="input" placeholder="One per line, e.g.&#10;Airfare&#10;Lunch & dinner&#10;Personal expenses"></textarea>
                            </div>
                        </div>
                        <p class="-mt-1 text-[11px] text-ink-400">One item per line. Leave blank to use the linked package's inclusions/exclusions.</p>
                        <button class="btn-primary btn-sm">Generate &amp; Send Quotation</button>
                    </form>
                </div>

                <div class="admin-card p-4">
                    <h3 class="mb-3 text-sm font-bold">Quotations Sent</h3>
                    <div class="space-y-3">
                        @forelse ($lead->quotations as $q)
                            <div class="rounded-lg border p-3 text-xs">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-ink-800">{{ $q->title }}</div>
                                        <div class="font-mono text-ink-400">{{ $q->quotation_number }}</div>
                                    </div>
                                    <div class="flex items-center gap-2 text-right">
                                        <div>
                                            <div class="text-sm font-bold text-emerald-600">₹{{ number_format($q->total_amount, 2) }}</div>
                                            <span class="status-pill bg-blue-100 capitalize text-blue-700">{{ $q->status }}</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <a href="{{ route('admin.crm.quotation.pdf', $q) }}" target="_blank" class="btn-ghost btn-xs text-brand-700 hover:bg-brand-50" title="View Quotation PDF in browser">📄 PDF</a>
                                            <a href="{{ route('admin.crm.quotation.download', $q) }}" class="btn-ghost btn-xs text-ink-600 hover:bg-ink-50" title="Download Quotation PDF">📥</a>
                                            @if ($contactEmail)
                                                <form action="{{ route('admin.crm.quotation.send', $q) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="btn-ghost btn-xs text-brand-700 hover:bg-brand-50" title="Resend quotation to {{ $contactEmail }}">✉️ Resend</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                {{-- Shareable public link + tracking --}}
                                <div class="mt-2 flex flex-wrap items-center gap-2 border-t pt-2">
                                    <input type="text" readonly value="{{ $q->publicUrl() }}"
                                           class="flex-1 min-w-0 rounded border bg-ink-50 px-2 py-1 font-mono text-[11px] text-ink-500"
                                           onclick="this.select()">
                                    <button type="button" class="btn-ghost btn-xs text-brand-700 hover:bg-brand-50"
                                            onclick="navigator.clipboard.writeText('{{ $q->publicUrl() }}');this.textContent='✓ Copied';setTimeout(()=>this.textContent='🔗 Copy link',1500)">🔗 Copy link</button>
                                    <a href="{{ $q->publicUrl() }}" target="_blank" class="btn-ghost btn-xs text-ink-600 hover:bg-ink-50">Open ↗</a>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-3 text-[11px] text-ink-400">
                                    @if ($q->sent_at) <span>Sent {{ $q->sent_at->format('d M, H:i') }}</span> @endif
                                    @if ($q->viewed_at) <span class="text-blue-600">Viewed {{ $q->viewed_at->format('d M, H:i') }}</span> @endif
                                    @if ($q->accepted_at) <span class="text-emerald-600">Accepted {{ $q->accepted_at->format('d M, H:i') }}</span> @endif
                                    @if ($q->rejected_at) <span class="text-rose-600">Declined {{ $q->rejected_at->format('d M, H:i') }}</span> @endif
                                    @if ($q->travel_date) <span class="text-indigo-600">Travel {{ $q->travel_date->format('d M Y') }}</span> @endif
                                    @if ($q->valid_until) <span>Valid till {{ $q->valid_until->format('d M Y') }}</span> @endif
                                </div>
                                {{-- Conversion to booking --}}
                                <div class="mt-2 border-t pt-2">
                                    @if ($q->converted_booking_id)
                                        <a href="{{ route('admin.bookings.show', $q->converted_booking_id) }}"
                                           class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:underline">
                                            ✓ Converted → view booking #{{ $q->converted_booking_id }}
                                        </a>
                                    @elseif (! in_array($q->status, ['rejected', 'expired'], true))
                                        <form action="{{ route('admin.crm.quotation.convert', $q) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Convert this quotation into a payment-pending booking?');">
                                            @csrf
                                            <button type="submit" class="btn-primary btn-xs">➜ Convert to Booking</button>
                                        </form>
                                        <span class="ml-1 text-[11px] text-ink-400">Creates a payment-pending booking + checkout link.</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-2 text-xs text-ink-400">No quotations created yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            {{-- Trip summary --}}
            <div class="admin-card p-4">
                <h3 class="mb-3 text-sm font-bold">Trip Requirements</h3>
                <dl class="space-y-1.5 text-xs">
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Product</dt><dd class="font-medium capitalize">{{ $lead->service_type ?? $lead->product_type ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Travel dates</dt><dd class="font-medium text-right">{{ optional($lead->travel_start_date ?? $lead->travel_date)?->format('d M Y') ?? '—' }}{{ $lead->travel_end_date ? ' – ' . $lead->travel_end_date->format('d M Y') : '' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Pax</dt><dd class="font-medium">{{ $lead->adults ?? $lead->travellers_count ?? '—' }} adult(s){{ $lead->children ? ', ' . $lead->children . ' child' : '' }}{{ $lead->infants ? ', ' . $lead->infants . ' infant' : '' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Rooms</dt><dd class="font-medium">{{ $lead->rooms ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Budget</dt><dd class="font-medium">
                        @if ($lead->budget_min || $lead->budget_max)
                            ₹{{ number_format((float) $lead->budget_min) }} – ₹{{ number_format((float) $lead->budget_max) }}
                        @elseif ($lead->budget)
                            ₹{{ number_format((float) $lead->budget) }}
                        @else — @endif
                    </dd></div>
                    @if ($lead->next_follow_up_at)
                        <div class="flex justify-between gap-2"><dt class="text-ink-400">Next follow-up</dt><dd class="font-medium {{ $lead->next_follow_up_at->isPast() ? 'text-rose-600' : '' }}">{{ $lead->next_follow_up_at->format('d M Y, h:i A') }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Attribution --}}
            <div class="admin-card p-4">
                <h3 class="mb-3 text-sm font-bold">Attribution</h3>
                <dl class="space-y-1.5 text-xs">
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Source</dt><dd class="font-medium text-right">{{ $lead->leadSource?->name ?? label_case((string) $lead->source) }}</dd></div>
                    @if ($lead->source_detail)
                        <div class="flex justify-between gap-2"><dt class="text-ink-400">Source detail</dt><dd class="font-medium text-right">{{ $lead->source_detail }}</dd></div>
                    @endif

                    <div class="pt-1 text-[10px] font-bold uppercase tracking-widest text-ink-400">First touch</div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Source / Medium</dt><dd class="font-medium text-right">{{ $lead->first_touch_source ?? '—' }}{{ $lead->first_touch_medium ? ' / ' . $lead->first_touch_medium : '' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Campaign</dt><dd class="font-medium text-right">{{ $lead->first_touch_campaign ?? '—' }}</dd></div>

                    <div class="pt-1 text-[10px] font-bold uppercase tracking-widest text-ink-400">Last touch</div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Source / Medium</dt><dd class="font-medium text-right">{{ $lead->last_touch_source ?? '—' }}{{ $lead->last_touch_medium ? ' / ' . $lead->last_touch_medium : '' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">Campaign</dt><dd class="font-medium text-right">{{ $lead->last_touch_campaign ?? '—' }}</dd></div>

                    <div class="pt-1 text-[10px] font-bold uppercase tracking-widest text-ink-400">UTM</div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">utm_source</dt><dd class="font-medium text-right">{{ $lead->utm_source ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">utm_medium</dt><dd class="font-medium text-right">{{ $lead->utm_medium ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">utm_campaign</dt><dd class="font-medium text-right">{{ $lead->utm_campaign ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">utm_term</dt><dd class="font-medium text-right">{{ $lead->utm_term ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">utm_content</dt><dd class="font-medium text-right">{{ $lead->utm_content ?? '—' }}</dd></div>

                    <div class="pt-1 text-[10px] font-bold uppercase tracking-widest text-ink-400">Click IDs & landing</div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">gclid</dt><dd class="max-w-[60%] truncate font-mono text-right" title="{{ $lead->gclid }}">{{ $lead->gclid ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-ink-400">fbclid</dt><dd class="max-w-[60%] truncate font-mono text-right" title="{{ $lead->fbclid }}">{{ $lead->fbclid ?? '—' }}</dd></div>
                    <div class="flex flex-col gap-0.5"><dt class="text-ink-400">Landing page</dt><dd class="break-all font-medium">{{ $lead->landing_page ?? '—' }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
@endsection
