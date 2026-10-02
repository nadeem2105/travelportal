@extends('layouts.site')

@section('page')
<section class="shell flex max-w-md flex-col justify-center py-20">
    <div class="card p-8">
        <h1 class="font-display text-2xl font-bold">Create your account</h1>
        <p class="mt-1 text-sm text-ink-500">Unlock exclusive deals and manage all your trips in one place.</p>

        <form action="{{ route('register.store') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label">Full Name</label>
                <input type="text" name="name" class="input" value="{{ old('name') }}" required autofocus>
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" class="input" value="{{ old('email') }}" required>
            </div>
            <div>
                <label class="label">Mobile (optional)</label>
                <input type="tel" name="phone" class="input" value="{{ old('phone') }}">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Password</label>
                    <input type="password" name="password" class="input" required>
                </div>
                <div>
                    <label class="label">Confirm</label>
                    <input type="password" name="password_confirmation" class="input" required>
                </div>
            </div>
            <button class="btn-primary btn-lg w-full">Create Account</button>
        </form>

        <div class="mt-5 flex items-center gap-3 text-xs text-ink-400">
            <span class="h-px flex-1 bg-ink-100"></span> or <span class="h-px flex-1 bg-ink-100"></span>
        </div>

        @include('partials.google-signin')
        @include('partials.facebook-signin')

        <p class="mt-5 text-center text-sm text-ink-500">
            Already have an account? <a href="{{ route('login') }}" class="font-bold text-brand-600 hover:text-brand-800">Sign in</a>
        </p>
    </div>
</section>
@endsection
