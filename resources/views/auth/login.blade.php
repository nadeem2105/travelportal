@extends('layouts.site')

@section('page')
<section class="shell flex max-w-md flex-col justify-center py-20">
    <div class="card p-8">
        <h1 class="font-display text-2xl font-bold">Welcome back</h1>
        <p class="mt-1 text-sm text-ink-500">Sign in to manage your trips and bookings.</p>

        <form action="{{ route('login.attempt') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" class="input" value="{{ old('email') }}" required autofocus>
            </div>
            <div>
                <label class="label">Password</label>
                <input type="password" name="password" class="input" required>
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-ink-700">
                    <input type="checkbox" name="remember" class="accent-brand-600"> Remember me
                </label>
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">Forgot password?</a>
            </div>
            <button class="btn-primary btn-lg w-full">Sign In</button>
        </form>

        <div class="mt-5 flex items-center gap-3 text-xs text-ink-400">
            <span class="h-px flex-1 bg-ink-100"></span> or <span class="h-px flex-1 bg-ink-100"></span>
        </div>

        <a href="{{ route('login.otp') }}" class="btn-ghost btn-lg mt-4 flex w-full items-center justify-center gap-2">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <rect x="7" y="2.5" width="10" height="19" rx="2.2"></rect>
                <line x1="11" y1="18.5" x2="13" y2="18.5"></line>
            </svg>
            Sign in with mobile OTP
        </a>

        @include('partials.google-signin')
        @include('partials.facebook-signin')

        <p class="mt-5 text-center text-sm text-ink-500">
            New here? <a href="{{ route('register') }}" class="font-bold text-brand-600 hover:text-brand-800">Create an account</a>
        </p>
    </div>
</section>
@endsection
