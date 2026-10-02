@extends('layouts.admin')
@section('pageTitle', $contactGroup->name)

@section('content')
<a href="{{ route('admin.contact-groups.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Groups</a>

<div class="mt-2 flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">{{ $contactGroup->name }}</h1>
        <p class="text-xs text-ink-500">
            <span class="status-pill {{ $contactGroup->isDynamic() ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }} capitalize">{{ $contactGroup->type }}</span>
            · {{ $contactGroup->memberCount() }} members
        </p>
    </div>
    <a href="{{ route('admin.contact-groups.edit', $contactGroup) }}" class="btn-ghost btn-sm">Edit</a>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

@if ($contactGroup->description)
    <p class="mt-3 text-sm text-ink-600">{{ $contactGroup->description }}</p>
@endif

@unless ($contactGroup->isDynamic())
    {{-- Static: add-member picker --}}
    <div class="admin-card mt-4 p-4">
        <form action="{{ route('admin.contact-groups.members.add', $contactGroup) }}" method="POST" class="flex flex-wrap items-end gap-2">
            @csrf
            <div class="flex-1">
                <label class="label">Add a contact</label>
                <select name="contact_id" required class="input">
                    <option value="">Select a contact…</option>
                    @foreach ($candidates as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} @if ($c->phone) · {{ $c->phone }} @endif</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-primary btn-sm">Add</button>
        </form>
        @if ($candidates->isEmpty())
            <p class="mt-2 text-xs text-ink-400">No more contacts to add (showing first 50 candidates only — use search on the Contacts page to curate).</p>
        @endif
    </div>
@else
    <div class="admin-card mt-4 p-4 text-sm text-ink-600">
        This is a dynamic group. Membership updates automatically based on its filters. Members below are a live preview.
    </div>
@endunless

<div class="admin-card mt-4 overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Lifecycle</th>
                <th>WhatsApp</th>
                @unless ($contactGroup->isDynamic())<th class="text-right">Action</th>@endunless
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $m)
                <tr>
                    <td><a href="{{ route('admin.contacts.show', $m) }}" class="font-semibold text-brand-600 hover:underline">{{ $m->name }}</a></td>
                    <td class="text-xs">{{ $m->phone ?? '—' }}</td>
                    <td class="text-xs">{{ $m->email ?? '—' }}</td>
                    <td class="text-xs capitalize">{{ label_case((string) $m->lifecycle_stage) }}</td>
                    <td>@if ($m->whatsapp_opt_in)<span class="status-pill bg-emerald-100 text-emerald-700">Opted in</span>@else<span class="status-pill bg-slate-100 text-slate-500">No</span>@endif</td>
                    @unless ($contactGroup->isDynamic())
                        <td class="text-right">
                            <form action="{{ route('admin.contact-groups.members.remove', [$contactGroup, $m]) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-xs text-rose-600">Remove</button>
                            </form>
                        </td>
                    @endunless
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-ink-500">No members yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $members->links() }}</div>
@endsection
