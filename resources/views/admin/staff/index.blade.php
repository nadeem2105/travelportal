@extends('layouts.admin')
@section('pageTitle', 'Staff')

@section('content')
    <h1 class="font-display text-xl font-bold">Staff Management</h1>

    <x-admin.filters
        :action="route('admin.staff.index')"
        search-placeholder="Search name or email…"
        :filters="[
            ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ['name' => 'role', 'label' => 'Role', 'options' => $roles->pluck('name', 'id')->all(), 'all' => 'All Roles'],
        ]"
        :count="$staff->total()" />

    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_360px]">
        <div>
        <div class="admin-card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th>Last Login</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @foreach ($staff as $member)
                        <tr>
                            <td>
                                <span class="font-bold">{{ $member->name }}</span>
                                @if ($member->is_super_admin) <span class="badge-soft ml-1 !text-[10px]">Super Admin</span> @endif
                                <form id="edit-{{ $member->id }}" action="{{ route('admin.staff.update', $member) }}" method="POST" class="hidden">
                                    @csrf @method('PUT')
                                </form>
                            </td>
                            <td>{{ $member->email }}</td>
                            <td>
                                @forelse ($member->roles as $role) <span class="badge-soft !text-[10px]">{{ $role->name }}</span> @empty <span class="text-xs text-ink-300">—</span> @endforelse
                            </td>
                            <td><span class="status-pill {{ status_pill_class($member->status) }}">{{ ucfirst($member->status) }}</span></td>
                            <td class="text-xs text-ink-500">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-right">
                                <a href="#" onclick="event.preventDefault(); document.getElementById('staff-edit-{{ $member->id }}').showModal()" class="font-bold text-brand-600">Edit</a>
                                @unless ($member->id === auth('admin')->id())
                                    <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" class="inline" onclick="return confirm('Remove this staff member?')">
                                        @csrf @method('DELETE')
                                        <button class="ml-3 font-bold text-rose-500">Delete</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $staff->links() }}</div>
        </div>

        {{-- Add staff --}}
        <div class="admin-card">
            <h2 class="font-display text-base font-bold">Add Staff Member</h2>
            <form action="{{ route('admin.staff.store') }}" method="POST" class="mt-3 space-y-3">
                @csrf
                <div><label class="label">Name</label><input type="text" name="name" class="input" required></div>
                <div><label class="label">Email</label><input type="email" name="email" class="input" required></div>
                <div><label class="label">Phone</label><input type="text" name="phone" class="input"></div>
                <div><label class="label">Password</label><input type="password" name="password" class="input" required></div>
                <div>
                    <label class="label">Roles</label>
                    <div class="space-y-1.5">
                        @foreach ($roles as $role)
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="accent-brand-600"> {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" name="is_super_admin" value="1" class="accent-brand-600"> Super Admin (full access)
                </label>
                <button class="btn-primary btn-md w-full">Create Staff</button>
            </form>
        </div>
    </div>

    {{-- Edit dialogs --}}
    @foreach ($staff as $member)
        <dialog id="staff-edit-{{ $member->id }}" class="rounded-2xl p-0 backdrop:bg-black/40">
            <form action="{{ route('admin.staff.update', $member) }}" method="POST" class="w-[420px] p-6">
                @csrf @method('PUT')
                <h2 class="font-display text-lg font-bold">Edit {{ $member->name }}</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="label">Name</label><input type="text" name="name" class="input" value="{{ $member->name }}" required></div>
                    <div><label class="label">Email</label><input type="email" name="email" class="input" value="{{ $member->email }}" required></div>
                    <div><label class="label">New Password (leave blank to keep)</label><input type="password" name="password" class="input"></div>
                    <div>
                        <label class="label">Roles</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($roles as $role)
                                <label class="flex cursor-pointer items-center gap-1.5 text-sm">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="accent-brand-600" @checked($member->roles->contains($role->id))> {{ $role->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select name="status" class="input">
                            <option value="active" @selected($member->status === 'active')>Active</option>
                            <option value="inactive" @selected($member->status === 'inactive')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-5 flex gap-2">
                    <button class="btn-primary btn-md">Save</button>
                    <button type="button" class="btn-ghost btn-md" onclick="document.getElementById('staff-edit-{{ $member->id }}').close()">Cancel</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endsection
