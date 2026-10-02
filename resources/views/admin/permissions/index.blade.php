@extends('layouts.admin')
@section('pageTitle', 'Permissions')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-display text-xl font-bold">Permissions Registry</h1>
        <a href="{{ route('admin.roles.index') }}" class="btn-ghost btn-md">Assign to Roles →</a>
    </div>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Permission</th><th>Module</th><th>Description</th><th class="text-right">Roles</th></tr></thead>
            <tbody>
                @forelse ($permissions as $permission)
                    <tr>
                        <td class="font-mono text-xs font-bold">{{ $permission->slug }}</td>
                        <td class="capitalize">{{ $permission->module }}</td>
                        <td class="text-ink-500">{{ $permission->description }}</td>
                        <td class="text-right">{{ $permission->roles_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-ink-500">No permissions defined</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $permissions->links() }}</div>
@endsection
