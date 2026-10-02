<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Customer authentication for the mobile app: email/password register + login,
 * token logout, email-OTP password reset, and authenticated password change.
 * Phone-OTP login lives in OtpAuthController; social login in SocialAuthController.
 * All responses use the shared {success,message,data} envelope.
 */
class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:15',
            'password' => ['required', Password::min(8)],
            'device_name' => 'nullable|string|max:80',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'], // hashed by cast
            'is_active' => true,
        ]);

        return $this->ok([
            'token' => $user->createToken($validated['device_name'] ?? 'mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ], 'Account created.', 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:80',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->fail('Invalid credentials.', 422);
        }
        if (! $user->is_active) {
            return $this->fail('Your account is inactive. Please contact support.', 403);
        }

        return $this->ok([
            'token' => $user->createToken($credentials['device_name'] ?? 'mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ], 'Signed in.');
    }

    public function me(Request $request)
    {
        return $this->ok(['user' => $this->userPayload($request->user())]);
    }

    /** Revoke the current access token (this device only). */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Signed out.');
    }

    /** Step 1 of reset: email a one-time code. Always returns success to avoid user enumeration. */
    public function forgotPassword(Request $request, NotificationService $notifier)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            Otp::where('identifier', $user->email)->where('purpose', 'password_reset')
                ->whereNull('consumed_at')->update(['consumed_at' => now()]);

            Otp::create([
                'identifier' => $user->email,
                'code' => $code,
                'purpose' => 'password_reset',
                'attempts' => 0,
                'expires_at' => now()->addMinutes(15),
            ]);

            try {
                $notifier->sendOtp($user->email, $code);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->ok(null, 'If that email exists, a reset code has been sent.');
    }

    /** Step 2 of reset: verify the code and set a new password. */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return $this->fail('Invalid or expired reset code.', 422);
        }

        $otp = Otp::where('identifier', $user->email)->where('purpose', 'password_reset')
            ->whereNull('consumed_at')->latest('id')->first();

        if (! $otp || $otp->isExpired() || ! hash_equals((string) $otp->code, trim($data['code']))) {
            if ($otp && ! $otp->isExpired()) {
                $otp->increment('attempts');
            }

            return $this->fail('Invalid or expired reset code.', 422);
        }

        $otp->forceFill(['consumed_at' => now()])->save();
        $user->forceFill(['password' => $data['password']])->save();
        $user->tokens()->delete(); // force re-login on all devices

        return $this->ok(null, 'Password reset. Please sign in again.');
    }

    /** Authenticated password change. */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->fail('Current password is incorrect.', 422);
        }

        $user->forceFill(['password' => $data['password']])->save();

        // Keep the current device signed in; revoke the rest.
        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return $this->ok(null, 'Password updated.');
    }

    /** Canonical user shape returned everywhere in the mobile API. */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar ? asset(img($user->avatar)) : null,
            'email_verified' => (bool) $user->email_verified_at,
            'phone_verified' => (bool) $user->phone_verified_at,
            'city' => $user->city,
        ];
    }
}
