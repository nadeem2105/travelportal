@extends('admin._layout')

@section('content')
<div class="admin-body flex min-h-screen items-center justify-center">
    <div class="w-full max-w-md px-6">
        <div class="card p-8">
            <div class="flex items-center justify-center gap-2.5">
                <img src="{{ asset('images/logo.svg') }}" class="h-12 w-12" alt="logo">
                <div>
                    <p class="font-display text-lg font-bold text-ink-900">{{ settings('company_name', 'Leemroz Travels') }}</p>
                    <p class="text-xs font-semibold text-brand-600">Admin Panel</p>
                </div>
            </div>

            <form action="{{ route('admin.login.attempt') }}" method="POST" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label class="label">Email</label>
                    <input type="email" name="email" class="input" value="{{ old('email') }}" required autofocus>
                </div>
                <div>
                    <label class="label">Password</label>
                    <input type="password" name="password" class="input" required>
                </div>
                @error('email')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                <button class="btn-primary btn-lg w-full">Sign In</button>
            </form>
        </div>
        <p class="mt-4 text-center text-xs text-ink-500">Authorized personnel only. All actions are logged.</p>
    </div>
</div>
@endsection
