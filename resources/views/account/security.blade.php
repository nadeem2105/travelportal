@extends('layouts.site')

@section('page')
<x-account.shell :accountNav="'security'">
    <h1 class="font-display text-2xl font-bold">Security</h1>

    <form action="{{ route('account.security.password') }}" method="POST" class="card mt-5 max-w-lg space-y-4 p-6">
        @csrf
        @method('PUT')
        <h2 class="font-display text-lg font-bold">Change Password</h2>
        <div>
            <label class="label">Current Password</label>
            <input type="password" name="current_password" class="input" required>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label">New Password</label>
                <input type="password" name="password" class="input" required>
            </div>
            <div>
                <label class="label">Confirm New</label>
                <input type="password" name="password_confirmation" class="input" required>
            </div>
        </div>
        <button class="btn-primary btn-md">Update Password</button>
    </form>

    <div class="card mt-5 max-w-lg p-6">
        <h2 class="font-display text-lg font-bold">Login Activity</h2>
        <p class="mt-1 text-sm text-ink-500">Last login: {{ auth('web')->user()->updated_at?->format('d M Y, h:i A') ?? 'First session' }}</p>
        <p class="mt-2 text-xs text-ink-500">Tip: never share your password or OTP with anyone. We will never call asking for it.</p>
    </div>
</x-account.shell>
@endsection
