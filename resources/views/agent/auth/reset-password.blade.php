@extends('layouts.site')

@section('page')
<section class="shell flex max-w-md flex-col justify-center py-20">
    <div class="card p-8">
        <h1 class="font-display text-2xl font-bold">Choose a new password</h1>
        <p class="mt-1 text-sm text-ink-500">Set a strong password (at least 8 characters).</p>

        <form action="{{ route('agent.password.update') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="label" for="rp-email">Email</label>
                <input id="rp-email" type="email" name="email" class="input" value="{{ old('email', $email) }}" required autofocus>
            </div>
            <div>
                <label class="label" for="rp-password">New Password</label>
                <input id="rp-password" type="password" name="password" class="input" required>
            </div>
            <div>
                <label class="label" for="rp-password-confirm">Confirm Password</label>
                <input id="rp-password-confirm" type="password" name="password_confirmation" class="input" required>
            </div>
            <button class="btn-primary btn-lg w-full">Reset Password</button>
        </form>
    </div>
</section>
@endsection
