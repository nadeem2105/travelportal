@extends('layouts.admin')
@section('pageTitle', 'CRM Leads & Enquiries')

@php
    $statusOptions = [
        'new' => 'New', 'contacted' => 'Contacted', 'quotation_sent' => 'Quotation Sent',
        'negotiating' => 'Negotiating', 'converted' => 'Converted', 'lost' => 'Lost',
    ];
    $priorityOptions = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
    $priorityPill = [
        'low' => 'bg-slate-100 text-slate-600', 'medium' => 'bg-sky-100 text-sky-700',
        'high' => 'bg-amber-100 text-amber-700', 'urgent' => 'bg-rose-100 text-rose-700',
    ];
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-display text-xl font-bold">CRM Leads &amp; Enquiries</h1>
            <p class="text-xs text-ink-500">Track inquiries, follow-ups, and convert leads into bookings</p>
        </div>
        <button onclick="document.getElementById('leadModal').classList.remove('hidden')" class="btn-primary btn-md">+ New Lead</button>
    </div>

    <x-admin.filters
        :search="true"
        search-name="q"
        search-placeholder="Search name, phone, email, lead #, destination…"
        :filters="[
            ['name' => 'status', 'label' => 'Status', 'all' => 'All statuses', 'options' => $statusOptions],
            ['name' => 'source_id', 'label' => 'Source', 'all' => 'All sources', 'options' => $sources->pluck('name', 'id')->all()],
            ['name' => 'stage_id', 'label' => 'Stage', 'all' => 'All stages', 'options' => $stages->mapWithKeys(fn ($s) => [$s->id => trim(($s->pipeline?->name ? $s->pipeline->name . ' · ' : '') . $s->name)])->all()],
            ['name' => 'assigned_to', 'label' => 'Agent', 'all' => 'All agents', 'options' => $staff->pluck('name', 'id')->all()],
            ['name' => 'priority', 'label' => 'Priority', 'all' => 'All priorities', 'options' => $priorityOptions],
        ]"
        :sorts="[
            'recent' => 'Newest first',
            'oldest' => 'Oldest first',
            'score_high' => 'Highest score',
            'followup' => 'Next follow-up',
            'name' => 'Name (A–Z)',
        ]"
        :count="$leads->total()"
    />

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Lead #</th>
                    <th>Contact</th>
                    <th>Source</th>
                    <th>Stage</th>
                    <th class="text-center">Score</th>
                    <th>Priority</th>
                    <th>Agent</th>
                    <th>Next follow-up</th>
                    <th>Created</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td>
                            <a href="{{ route('admin.crm.show', $lead) }}" class="font-mono text-xs font-bold text-brand-600 hover:underline">{{ $lead->lead_number ?? ('#' . $lead->id) }}</a>
                        </td>
                        <td>
                            <a href="{{ route('admin.crm.show', $lead) }}" class="font-semibold text-ink-800 hover:text-brand-600">{{ $lead->contact?->name ?? $lead->name }}</a>
                            <div class="text-[11px] text-ink-400">{{ $lead->contact?->phone ?? $lead->phone }}</div>
                        </td>
                        <td>
                            <span class="status-pill bg-slate-100 text-slate-700 capitalize">{{ $lead->leadSource?->name ?? label_case((string) $lead->source) }}</span>
                        </td>
                        <td>
                            @if ($lead->stage)
                                @php($stageColor = $lead->stage->color)
                                <span class="status-pill {{ $stageColor ? '' : 'bg-slate-100 text-slate-700' }}"
                                      @if ($stageColor) style="background-color: {{ $stageColor }}1a; color: {{ $stageColor }};" @endif>{{ $lead->stage->name }}</span>
                            @else
                                <span class="status-pill {{ status_pill_class($lead->status) }}">{{ label_case($lead->status) }}</span>
                            @endif
                        </td>
                        <td class="text-center font-semibold">{{ $lead->score ?? 0 }}</td>
                        <td>
                            <span class="status-pill capitalize {{ $priorityPill[$lead->priority] ?? 'bg-slate-100 text-slate-600' }}">{{ $lead->priority ?? 'medium' }}</span>
                        </td>
                        <td class="text-xs">{{ $lead->assignee?->name ?? 'Unassigned' }}</td>
                        <td class="text-xs">
                            @if ($lead->next_follow_up_at)
                                <span class="{{ $lead->next_follow_up_at->isPast() ? 'text-rose-600 font-semibold' : 'text-ink-600' }}">{{ $lead->next_follow_up_at->format('d M Y, h:i A') }}</span>
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </td>
                        <td class="text-xs text-ink-500">{{ $lead->created_at?->format('d M Y') }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.crm.show', $lead) }}" class="btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-ink-500 py-6">No leads found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $leads->links() }}</div>

    <!-- Modal for new lead -->
    <div id="leadModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-lg">Add New Lead</h3>
                <button onclick="document.getElementById('leadModal').classList.add('hidden')" class="text-ink-400 hover:text-ink-600">✕</button>
            </div>
            <form action="{{ route('admin.crm.lead.store') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="label">Lead Name *</label>
                    <input type="text" name="name" class="input" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Phone *</label>
                        <input type="text" name="phone" class="input" required>
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input type="email" name="email" class="input">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Destination</label>
                        <input type="text" name="destination" class="input" placeholder="e.g. Kashmir">
                    </div>
                    <div>
                        <label class="label">Product Type</label>
                        <select name="product_type" class="input">
                            <option value="package">Package</option>
                            <option value="flight">Flight</option>
                            <option value="hotel">Hotel</option>
                            <option value="cab">Cab</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Budget (₹)</label>
                        <input type="number" name="budget" class="input">
                    </div>
                    <div>
                        <label class="label">Source</label>
                        <select name="source" class="input">
                            <option value="website">Website</option>
                            <option value="phone">Phone</option>
                            <option value="referral">Referral</option>
                            <option value="social">Social</option>
                            <option value="campaign">Campaign</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">Assign To</label>
                    <select name="assigned_to" class="input">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="document.getElementById('leadModal').classList.add('hidden')" class="btn-ghost btn-md">Cancel</button>
                    <button class="btn-primary btn-md">Save Lead</button>
                </div>
            </form>
        </div>
    </div>
@endsection
