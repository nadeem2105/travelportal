@extends('layouts.site')

@section('page')
<section class="shell flex max-w-md flex-col justify-center py-20">
    <div class="card p-8">
        @if ($step === 'request')
            {{-- Step 1: enter mobile number --}}
            <h1 class="font-display text-2xl font-bold">Sign in with OTP</h1>
            <p class="mt-1 text-sm text-ink-500">Enter your mobile number and we'll text you a one-time code.</p>

            <form action="{{ route('login.otp.request') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="otp-phone">Mobile number</label>
                    <input id="otp-phone" type="tel" name="phone" inputmode="tel" autocomplete="tel"
                           class="input" value="{{ old('phone') }}" placeholder="e.g. 98765 43210" required autofocus>
                    <p class="mt-1 text-xs text-ink-400">Indian numbers can be entered as 10 digits; add the country code for others.</p>
                </div>
                <button class="btn-primary btn-lg w-full">Send code</button>
            </form>
        @else
            {{-- Step 2: enter the code --}}
            <h1 class="font-display text-2xl font-bold">Enter your code</h1>
            <p class="mt-1 text-sm text-ink-500">
                We sent a verification code to <span class="font-semibold text-ink-700">{{ $phone }}</span>.
                <a href="{{ route('login.otp.change') }}" class="font-semibold text-brand-600 hover:text-brand-800">Change</a>
            </p>

            <form action="{{ route('login.otp.verify') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="otp-code">Verification code</label>
                    <input id="otp-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                           pattern="[0-9]*" maxlength="8" class="input tracking-[0.5em] text-center text-lg"
                           placeholder="••••••" required autofocus>
                </div>
                <button class="btn-primary btn-lg w-full">Verify &amp; sign in</button>
            </form>

            <div class="mt-4 text-center text-sm text-ink-500" x-data="{ wait: {{ (int) $resendAfter }} }" x-init="wait > 0 && (function tick(){ if (wait > 0) { wait--; setTimeout(tick, 1000); } })()">
                <form action="{{ route('login.otp.resend') }}" method="POST" class="inline">
                    @csrf
                    <span x-show="wait > 0">You can request a new code in <span x-text="wait"></span>s</span>
                    <button type="submit" x-show="wait === 0" x-cloak
                            class="font-semibold text-brand-600 hover:text-brand-800">Resend code</button>
                </form>
            </div>
        @endif

        <div class="mt-6 border-t border-ink-100 pt-5 text-center text-sm text-ink-500">
            <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-800">Sign in with email instead</a>
        </div>

        @include('partials.google-signin')
        @include('partials.facebook-signin')

        <p class="mt-3 text-center text-sm text-ink-500">
            New here? <a href="{{ route('register') }}" class="font-bold text-brand-600 hover:text-brand-800">Create an account</a>
        </p>
    </div>
</section>
@endsection
