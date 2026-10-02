@extends('layouts.site')

@section('page')
<section class="shell flex max-w-md flex-col justify-center py-20">
    <div class="card p-8">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
            B2B Agent Portal
        </div>
        <h1 class="font-display text-2xl font-bold">Agent Sign In</h1>
        <p class="mt-1 text-sm text-ink-500">Access agent pricing, wallet & bookings.</p>

        <form action="{{ route('agent.login.attempt') }}" method="POST" class="mt-6 space-y-4">
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
                <a href="{{ route('agent.password.request') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">Forgot password?</a>
            </div>
            <button class="btn-primary btn-lg w-full">Sign In</button>
        </form>

        <p class="mt-5 text-center text-sm text-ink-500">
            Not a partner yet? <a href="{{ route('agent.apply') }}" class="font-bold text-brand-600 hover:text-brand-800">Apply as an agent</a>
        </p>
    </div>
</section>
@endsection
