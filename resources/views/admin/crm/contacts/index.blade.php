@extends('layouts.admin')
@section('pageTitle', 'Contacts')

@php
    $lifecyclePill = [
        'lead' => 'bg-slate-100 text-slate-600', 'prospect' => 'bg-sky-100 text-sky-700',
        'qualified' => 'bg-indigo-100 text-indigo-700', 'customer' => 'bg-emerald-100 text-emerald-700',
        'repeat' => 'bg-teal-100 text-teal-700', 'vip' => 'bg-amber-100 text-amber-700',
        'inactive' => 'bg-rose-100 text-rose-700',
    ];
@endphp

@section('content')
<div x-data="{ addOpen: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-display text-xl font-bold">Contacts</h1>
            <p class="text-xs text-ink-500">Unified customer records across leads, quotations, and bookings</p>
        </div>
        <button type="button" @click="addOpen = true" class="btn-primary btn-sm">+ Add Contact</button>
    </div>

    @if (session('error'))
        <div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>
    @endif
    @if (session('info'))
        <div class="mt-3 rounded-lg bg-blue-50 px-4 py-2 text-sm text-blue-700">{{ session('info') }}</div>
    @endif
    @if (session('success'))
        <div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-admin.filters
        :search="true"
        search-name="q"
        search-placeholder="Search name, phone, or email…"
        :filters="[
            ['name' => 'lifecycle_stage', 'label' => 'Lifecycle', 'all' => 'All lifecycle stages', 'options' => collect($lifecycleStages)->mapWithKeys(fn ($s) => [$s => label_case($s)])->all()],
            ['name' => 'assigned_user', 'label' => 'Owner', 'all' => 'All owners', 'options' => $staff->pluck('name', 'id')->all()],
        ]"
        :count="$contacts->total()"
    />

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Lifecycle</th>
                    <th class="text-center">Leads</th>
                    <th>Owner</th>
                    <th>Last activity</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contacts as $contact)
                    <tr>
                        <td><a href="{{ route('admin.contacts.show', $contact) }}" class="font-semibold text-brand-600 hover:underline">{{ $contact->name }}</a></td>
                        <td class="text-xs">{{ $contact->phone ?? '—' }}</td>
                        <td class="text-xs">{{ $contact->email ?? '—' }}</td>
                        <td><span class="status-pill capitalize {{ $lifecyclePill[$contact->lifecycle_stage] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case((string) $contact->lifecycle_stage) }}</span></td>
                        <td class="text-center font-semibold">{{ $contact->leads_count }}</td>
                        <td class="text-xs">{{ $contact->assignee?->name ?? 'Unassigned' }}</td>
                        <td class="text-xs text-ink-500">{{ $contact->last_activity_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-right"><a href="{{ route('admin.contacts.show', $contact) }}" class="btn-ghost btn-sm">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-ink-500">No contacts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $contacts->links() }}</div>

    {{-- Add Contact modal --}}
    <div x-show="addOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8"
         @keydown.escape.window="addOpen = false">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl" @click.outside="addOpen = false">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="font-display text-lg font-bold">Add Contact</h2>
                <button type="button" @click="addOpen = false" class="text-ink-400 hover:text-ink-700">&times;</button>
            </div>
            <form action="{{ route('admin.contacts.store') }}" method="POST" class="px-6 py-5">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">Full Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="input" placeholder="e.g. Aarav Sharma">
                    </div>
                    <div>
                        <label class="label">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="input" placeholder="+91 98765 43210">
                    </div>
                    <div>
                        <label class="label">Alternate Phone</label>
                        <input type="text" name="alternate_phone" value="{{ old('alternate_phone') }}" class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="input" placeholder="name@example.com">
                    </div>
                    <p class="sm:col-span-2 -mt-2 text-xs text-ink-400">Provide at least a phone or an email. Duplicates are detected automatically.</p>
                    <div>
                        <label class="label">City</label>
                        <input type="text" name="city" value="{{ old('city') }}" class="input">
                    </div>
                    <div>
                        <label class="label">State</label>
                        <input type="text" name="state" value="{{ old('state') }}" class="input">
                    </div>
                    <div>
                        <label class="label">Country</label>
                        <input type="text" name="country" value="{{ old('country') }}" class="input">
                    </div>
                    <div>
                        <label class="label">Lifecycle Stage</label>
                        <select name="lifecycle_stage" class="input">
                            @foreach ($lifecycleStages as $s)
                                <option value="{{ $s }}" @selected(old('lifecycle_stage', 'lead') === $s)>{{ label_case($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Address</label>
                        <textarea name="address" rows="2" class="input">{{ old('address') }}</textarea>
                    </div>
                    <div>
                        <label class="label">Owner</label>
                        <select name="assigned_user_id" class="input">
                            <option value="">Unassigned</option>
                            @foreach ($staff as $s)
                                <option value="{{ $s->id }}" @selected(old('assigned_user_id') == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col justify-end gap-1 pt-1 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="whatsapp_opt_in" value="1" @checked(old('whatsapp_opt_in')) class="rounded"> WhatsApp opt-in</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in')) class="rounded"> Marketing opt-in</label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="addOpen = false" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm">Save Contact</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
