@extends('layouts.admin')
@section('pageTitle', 'Roles & Permissions')

@section('content')
    <h1 class="font-display text-xl font-bold">Roles</h1>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
            @foreach ($roles as $role)
                <details class="admin-card" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between">
                        <span class="font-display font-bold">{{ $role->name }}
                            @if ($role->is_system) <span class="badge-soft ml-1 !text-[10px]">System</span> @endif
                            <span class="ml-2 text-xs font-medium text-ink-500">{{ $role->admins_count }} staff · {{ $role->permissions->count() }} permissions</span>
                        </span>
                    </summary>
                    <form action="{{ route('admin.roles.update', $role) }}" method="POST" class="mt-4">
                        @csrf @method('PUT')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="label">Name</label><input type="text" name="name" class="input" value="{{ $role->name }}" required></div>
                            <div><label class="label">Description</label><input type="text" name="description" class="input" value="{{ $role->description }}"></div>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                            @foreach ($permissions as $module => $modulePermissions)
                                <div class="rounded-xl border border-slate-100 p-2.5">
                                    <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-ink-500">{{ $module }}</p>
                                    @foreach ($modulePermissions as $permission)
                                        <label class="flex cursor-pointer items-center gap-1.5 text-xs">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="accent-brand-600"
                                                   @checked($role->permissions->contains($permission->id))> {{ $permission->slug }}
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <button class="btn-primary btn-sm">Save Role</button>
                            @unless ($role->is_system)
                                @csrf
                            @endunless
                        </div>
                    </form>
                    @unless ($role->is_system)
                        <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="mt-2" onclick="return confirm('Delete role {{ $role->name }}?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-bold text-rose-500">Delete Role</button>
                        </form>
                    @endunless
                </details>
            @endforeach
        </div>

        <div class="admin-card h-fit">
            <h2 class="font-display text-base font-bold">Create Role</h2>
            <form action="{{ route('admin.roles.store') }}" method="POST" class="mt-3 space-y-3">
                @csrf
                <div><label class="label">Name</label><input type="text" name="name" class="input" placeholder="e.g. Booking Manager" required></div>
                <div><label class="label">Description</label><input type="text" name="description" class="input"></div>
                <div class="max-h-72 space-y-2 overflow-y-auto rounded-xl border border-slate-100 p-3">
                    @foreach ($permissions as $module => $modulePermissions)
                        <p class="text-[10px] font-bold uppercase tracking-wider text-ink-500">{{ $module }}</p>
                        @foreach ($modulePermissions as $permission)
                            <label class="flex cursor-pointer items-center gap-1.5 text-xs">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="accent-brand-600"> {{ $permission->slug }}
                            </label>
                        @endforeach
                    @endforeach
                </div>
                <button class="btn-primary btn-md w-full">Create Role</button>
            </form>
        </div>
    </div>
@endsection
