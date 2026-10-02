<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $staff = Admin::with('roles')
            ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($request->input('role'), fn ($query, $v) => $query->whereHas('roles', fn ($r) => $r->where('roles.id', $v)))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $roles = Role::all();

        return view('admin.staff.index', compact('staff', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|unique:admins,email',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', Password::min(8)],
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
            'is_super_admin' => 'nullable|boolean',
        ]);

        $staff = Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'is_super_admin' => $request->boolean('is_super_admin'),
            'status' => 'active',
        ]);

        $staff->roles()->sync($validated['roles'] ?? []);

        ActivityLogger::log('create', 'staff', "Created staff {$staff->email}");

        return back()->with('success', 'Staff member created.');
    }

    public function update(Request $request, Admin $staff)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|unique:admins,email,' . $staff->id,
            'phone' => 'nullable|string|max:20',
            'password' => ['nullable', Password::min(8)],
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
            'status' => 'required|in:active,inactive',
        ]);

        $staff->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            ...(! empty($validated['password']) ? ['password' => $validated['password']] : []),
        ]);

        if (! $staff->is_super_admin) {
            $staff->roles()->sync($validated['roles'] ?? []);
        }

        ActivityLogger::log('update', 'staff', "Updated staff {$staff->email}");

        return back()->with('success', 'Staff updated.');
    }

    public function destroy(Admin $staff)
    {
        if ($staff->id === auth('admin')->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        ActivityLogger::log('delete', 'staff', "Deleted staff {$staff->email}");
        $staff->delete();

        return back()->with('success', 'Staff removed.');
    }
}
