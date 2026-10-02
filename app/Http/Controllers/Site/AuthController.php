<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Sms\SmsManager;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
        protected SmsManager $sms,
    ) {
    }

    public function showLogin()
    {
        return view('auth.login', ['seo' => ['title' => 'Sign In', 'description' => 'Sign in to your account']]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            return back()->withErrors(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register', ['seo' => ['title' => 'Create Account', 'description' => 'Create your account']]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:15',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        $this->notifications->sendWelcome($user);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('success', 'Welcome to ' . settings('company_name', 'Leemroz Travels') . ', ' . str($user->name)->before(' ') . '!');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    // --- Mobile OTP login (session / 'web' guard) -------------------------
    //
    // Passwordless sign-in for the customer website. Reuses SmsManager (the
    // same OTP lifecycle the mobile API uses) but logs the user into the
    // session via the 'web' guard instead of issuing a Sanctum token.

    private const OTP_SESSION_PHONE = 'otp_login_phone';
    private const OTP_SESSION_RESEND = 'otp_login_resend_after';

    /** Show the OTP login screen (phone step, or code step once a code is sent). */
    public function showOtpLogin(Request $request)
    {
        $phone = $request->session()->get(self::OTP_SESSION_PHONE);

        return view('auth.otp-login', [
            'seo' => ['title' => 'Sign in with OTP', 'description' => 'Sign in with a one-time code sent to your mobile'],
            'phone' => $phone,
            'step' => $phone ? 'verify' : 'request',
            'resendAfter' => (int) $request->session()->get(self::OTP_SESSION_RESEND, 0),
        ]);
    }

    /** Step 1: validate the phone number and dispatch a one-time code. */
    public function requestOtp(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:20']);

        if (! $this->sms->enabled()) {
            return back()->withErrors(['phone' => 'Mobile sign-in is currently unavailable. Please sign in with your email instead.'])->withInput();
        }

        $result = $this->sms->sendOtp($data['phone'], 'phone_login');

        if (! $result['success']) {
            return back()->withErrors(['phone' => $result['error'] ?? 'We could not send the code. Please try again.'])->withInput();
        }

        $request->session()->put(self::OTP_SESSION_PHONE, $this->sms->normalize($data['phone']));
        $request->session()->put(self::OTP_SESSION_RESEND, $result['retry_after'] ?? 60);

        return redirect()->route('login.otp')->with('success', 'We sent a verification code to your phone.');
    }

    /** Resend a code to the phone number captured in the current session. */
    public function resendOtp(Request $request)
    {
        $phone = $request->session()->get(self::OTP_SESSION_PHONE);
        if (! $phone) {
            return redirect()->route('login.otp')->withErrors(['phone' => 'Please enter your mobile number first.']);
        }

        $result = $this->sms->sendOtp($phone, 'phone_login');
        if (! $result['success']) {
            return back()->withErrors(['code' => $result['error'] ?? 'We could not resend the code. Please try again.']);
        }

        $request->session()->put(self::OTP_SESSION_RESEND, $result['retry_after'] ?? 60);

        return back()->with('success', 'A new verification code is on its way.');
    }

    /** Step 2: verify the code and sign the user into the session. */
    public function verifyOtp(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:8']);

        $phone = $request->session()->get(self::OTP_SESSION_PHONE);
        if (! $phone) {
            return redirect()->route('login.otp')->withErrors(['phone' => 'Your session expired. Please request a new code.']);
        }

        $result = $this->sms->verifyOtp($phone, $data['code'], 'phone_login');
        if (! $result['success']) {
            return back()->withErrors(['code' => $result['error'] ?? 'That code is not valid.']);
        }

        // Match an existing account by stored phone (normalized or bare 10-digit).
        $last10 = substr(preg_replace('/\D+/', '', $phone), -10);
        $user = User::whereNotNull('phone')
            ->where(function ($q) use ($phone, $last10) {
                $q->where('phone', $phone)->orWhereRaw('RIGHT(phone, 10) = ?', [$last10]);
            })
            ->first();

        $isNew = false;
        if (! $user) {
            $digits = preg_replace('/\D+/', '', $phone);
            $user = User::create([
                'name' => 'Traveller',
                // `users.email` is NOT NULL; use a unique placeholder derived from
                // the phone. The user can set a real email later from their profile.
                'email' => $digits . '@phone.leemroz.local',
                'phone' => $phone,
                'password' => bin2hex(random_bytes(16)), // random; user can set one later
                'is_active' => true,
            ]);
            $isNew = true;
            $this->notifications->sendWelcome($user);
        }

        if (! $user->is_active) {
            $request->session()->forget([self::OTP_SESSION_PHONE, self::OTP_SESSION_RESEND]);

            return redirect()->route('login.otp')->withErrors(['phone' => 'Your account has been deactivated. Please contact support.']);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        $request->session()->forget([self::OTP_SESSION_PHONE, self::OTP_SESSION_RESEND]);

        return redirect()->intended(route('account.dashboard'))->with(
            'success',
            $isNew
                ? 'Welcome to ' . settings('company_name', 'Leemroz Travels') . '!'
                : 'Signed in successfully.'
        );
    }

    /** Clear the captured phone so the user can enter a different number. */
    public function otpChangeNumber(Request $request)
    {
        $request->session()->forget([self::OTP_SESSION_PHONE, self::OTP_SESSION_RESEND]);

        return redirect()->route('login.otp');
    }

    // --- Google sign-in (session / 'web' guard) ---------------------------
    //
    // Uses Google Identity Services on the browser: the GIS button returns an
    // ID token ("credential") which we verify server-side against Google's
    // public tokeninfo endpoint (same trust model as the mobile API — no client
    // secret needed), then match/create the user and start a session.

    public function googleCallback(Request $request)
    {
        $data = $request->validate(['credential' => 'required|string']);

        $profile = $this->verifyGoogleIdToken($data['credential']);
        if (! $profile || empty($profile['email'])) {
            return redirect()->route('login')->withErrors(['email' => 'We could not verify your Google sign-in. Please try again.']);
        }

        $user = User::where('email', $profile['email'])->first();

        $isNew = false;
        if (! $user) {
            $user = User::create([
                'name' => $profile['name'] ?: 'Traveller',
                'email' => $profile['email'],
                'avatar' => $profile['picture'] ?: null,
                'password' => bin2hex(random_bytes(16)), // random; user can set one later
                'is_active' => true,
            ]);
            $isNew = true;
            $this->notifications->sendWelcome($user);
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        // Trust Google's email verification; backfill avatar if we don't have one.
        $fill = [];
        if (! $user->email_verified_at && ($profile['email_verified'] ?? false)) {
            $fill['email_verified_at'] = now();
        }
        if (empty($user->avatar) && ! empty($profile['picture'])) {
            $fill['avatar'] = $profile['picture'];
        }
        if ($fill) {
            $user->forceFill($fill)->save();
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))->with(
            'success',
            $isNew
                ? 'Welcome to ' . settings('company_name', 'Leemroz Travels') . '!'
                : 'Signed in with Google.'
        );
    }

    /** Verify a Google ID token via the public tokeninfo endpoint. */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            $res = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
            if (! $res->successful()) {
                return null;
            }
            $p = $res->json();

            // Audience must match one of our configured Google client IDs.
            $allowed = (array) config('services.google.client_ids', []);
            if ($webId = config('services.google.web_client_id')) {
                $allowed[] = $webId;
            }
            $allowed = array_values(array_filter($allowed));
            if (empty($allowed) || ! in_array($p['aud'] ?? '', $allowed, true)) {
                return null;
            }

            $iss = $p['iss'] ?? '';
            if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
                return null;
            }

            return [
                'sub' => $p['sub'] ?? '',
                'email' => $p['email'] ?? null,
                'email_verified' => filter_var($p['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'name' => $p['name'] ?? null,
                'picture' => $p['picture'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Web Google token verify failed: ' . $e->getMessage());

            return null;
        }
    }

    // --- Facebook sign-in (session / 'web' guard) -------------------------
    //
    // Uses the Facebook JavaScript SDK on the browser: FB.login() yields a
    // short-lived user access token which we POST here. We verify it server-side
    // via the Graph API (debug_token, authenticated with an app access token
    // built from app_id|app_secret) to confirm the token was issued for OUR app,
    // then fetch the profile, match/create the user and start a session.

    public function facebookCallback(Request $request)
    {
        $data = $request->validate(['access_token' => 'required|string']);

        $profile = $this->verifyFacebookToken($data['access_token']);
        if (! $profile || empty($profile['id'])) {
            return redirect()->route('login')->withErrors(['email' => 'We could not verify your Facebook sign-in. Please try again.']);
        }

        // Facebook may not return an email (user registered by phone, or denied
        // the permission). `users.email` is NOT NULL, so fall back to a unique
        // placeholder derived from the Facebook user ID for those accounts.
        $email = $profile['email'] ?: ($profile['id'] . '@facebook.leemroz.local');

        $user = User::where('email', $email)->first();

        $isNew = false;
        if (! $user) {
            $user = User::create([
                'name' => $profile['name'] ?: 'Traveller',
                'email' => $email,
                'avatar' => $profile['picture'] ?: null,
                'password' => bin2hex(random_bytes(16)), // random; user can set one later
                'is_active' => true,
            ]);
            $isNew = true;
            $this->notifications->sendWelcome($user);
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        // Facebook only returns emails it has already verified; trust it. Backfill
        // avatar when we don't have one.
        $fill = [];
        if (! $user->email_verified_at && ! empty($profile['email'])) {
            $fill['email_verified_at'] = now();
        }
        if (empty($user->avatar) && ! empty($profile['picture'])) {
            $fill['avatar'] = $profile['picture'];
        }
        if ($fill) {
            $user->forceFill($fill)->save();
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))->with(
            'success',
            $isNew
                ? 'Welcome to ' . settings('company_name', 'Leemroz Travels') . '!'
                : 'Signed in with Facebook.'
        );
    }

    /**
     * Verify a Facebook user access token server-side and return the profile.
     * Confirms via debug_token that the token is valid AND was issued for our
     * own app (guards against tokens minted by a different Facebook app), then
     * fetches id/name/email/picture from the Graph API.
     */
    private function verifyFacebookToken(string $accessToken): ?array
    {
        $appId = (string) config('services.facebook.app_id');
        $appSecret = (string) config('services.facebook.app_secret');
        $version = (string) config('services.facebook.graph_version', 'v21.0');

        if ($appId === '' || $appSecret === '') {
            return null;
        }

        try {
            $base = "https://graph.facebook.com/{$version}";
            $appToken = $appId . '|' . $appSecret;

            // 1) Validate the token and its owning app.
            $debug = Http::timeout(10)->get("{$base}/debug_token", [
                'input_token' => $accessToken,
                'access_token' => $appToken,
            ]);
            if (! $debug->successful()) {
                return null;
            }
            $meta = (array) $debug->json('data', []);
            if (empty($meta['is_valid']) || (string) ($meta['app_id'] ?? '') !== $appId) {
                return null;
            }

            // 2) Fetch the profile using the (now-trusted) user token.
            $me = Http::timeout(10)->get("{$base}/me", [
                'fields' => 'id,name,email,picture.width(200).height(200)',
                'access_token' => $accessToken,
            ]);
            if (! $me->successful()) {
                return null;
            }
            $p = (array) $me->json();
            if (empty($p['id'])) {
                return null;
            }

            return [
                'id' => (string) $p['id'],
                'email' => $p['email'] ?? null,
                'name' => $p['name'] ?? null,
                'picture' => data_get($p, 'picture.data.url') ?: null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Web Facebook token verify failed: ' . $e->getMessage());

            return null;
        }
    }

    // --- Password reset (customer 'users' broker) -------------------------

    public function showForgotPassword()
    {
        return view('auth.forgot-password', ['seo' => ['title' => 'Forgot Password', 'description' => 'Reset your account password']]);
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = PasswordBroker::sendResetLink($request->only('email'));

        // Always report success-style to avoid leaking which emails are registered.
        return $status === PasswordBroker::RESET_LINK_SENT
            ? back()->with('success', 'If that email is registered, a password reset link is on its way.')
            : back()->with('success', 'If that email is registered, a password reset link is on its way.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'seo' => ['title' => 'Reset Password', 'description' => 'Choose a new password'],
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password reset successfully. Please sign in.');
        }

        return back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
