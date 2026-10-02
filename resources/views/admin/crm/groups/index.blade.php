@extends('layouts.admin')
@section('pageTitle', 'Contact Groups')

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">Contact Groups</h1>
        <p class="text-xs text-ink-500">Static lists and dynamic segments for campaign targeting</p>
    </div>
    <a href="{{ route('admin.contact-groups.create') }}" class="btn-primary btn-sm">+ New Group</a>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

<div class="admin-card mt-4 overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th class="text-center">Members</th>
                <th>Description</th>
                <th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                <tr>
                    <td><a href="{{ route('admin.contact-groups.show', $group) }}" class="font-semibold text-brand-600 hover:underline">{{ $group->name }}</a></td>
                    <td>
                        <span class="status-pill {{ $group->isDynamic() ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }} capitalize">{{ $group->type }}</span>
                    </td>
                    <td class="text-center font-semibold">{{ $group->isDynamic() ? $group->memberCount() : $group->members_count }}</td>
                    <td class="text-xs text-ink-500">{{ \Illuminate\Support\Str::limit($group->description, 80) ?: '—' }}</td>
                    <td class="text-right">
                        <a href="{{ route('admin.contact-groups.show', $group) }}" class="btn-ghost btn-xs">View</a>
                        <a href="{{ route('admin.contact-groups.edit', $group) }}" class="btn-ghost btn-xs">Edit</a>
                        <form action="{{ route('admin.contact-groups.destroy', $group) }}" method="POST" class="inline" onsubmit="return confirm('Delete this group?');">
                            @csrf @method('DELETE')
                            <button class="btn-ghost btn-xs text-rose-600">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-ink-500">No groups yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $groups->links() }}</div>
@endsection
