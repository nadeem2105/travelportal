@extends('layouts.site')

@section('page')
<section class="shell flex max-w-md flex-col justify-center py-20">
    <div class="card p-8">
        <h1 class="font-display text-2xl font-bold">Forgot your password?</h1>
        <p class="mt-1 text-sm text-ink-500">Enter your email and we'll send you a link to reset it.</p>

        <form action="{{ route('password.email') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label" for="fp-email">Email</label>
                <input id="fp-email" type="email" name="email" class="input" value="{{ old('email') }}" required autofocus>
            </div>
            <button class="btn-primary btn-lg w-full">Send Reset Link</button>
        </form>

        <p class="mt-5 text-center text-sm text-ink-500">
            Remembered it? <a href="{{ route('login') }}" class="font-bold text-brand-600 hover:text-brand-800">Back to sign in</a>
        </p>
    </div>
</section>
@endsection
