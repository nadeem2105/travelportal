<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Request;

/**
 * Passwordless phone-OTP authentication for the mobile app. Uses SmsManager
 * (Fast2SMS / MSG91 / Twilio, chosen in the admin panel) to deliver codes and
 * the `otps` table to verify them. On success an existing user is matched by
 * phone (or a lightweight account is created) and a Sanctum token is issued.
 */
class OtpAuthController extends Controller
{
    use ApiResponse;

    public function __construct(private SmsManager $sms)
    {
    }

    /** Request an OTP for login/registration. */
    public function request(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:20']);

        if (! $this->sms->enabled()) {
            return $this->fail('Phone sign-in is currently unavailable.', 503);
        }

        $result = $this->sms->sendOtp($data['phone'], 'phone_login');

        if (! $result['success']) {
            $status = $result['retry_after'] ? 429 : 422;

            return $this->fail($result['error'] ?? 'Could not send the code.', $status);
        }

        return $this->ok([
            'expires_at' => $result['expires_at'],
            'resend_after' => $result['retry_after'],
        ], 'Verification code sent.');
    }

    /** Verify the OTP and issue a token. */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'code' => 'required|string|max:8',
            'name' => 'nullable|string|max:80',
            'device_name' => 'nullable|string|max:80',
        ]);

        $result = $this->sms->verifyOtp($data['phone'], $data['code'], 'phone_login');
        if (! $result['success']) {
            return $this->fail($result['error'] ?? 'Invalid code.', 422);
        }

        $normalized = $this->sms->normalize($data['phone']);
        $last10 = substr(preg_replace('/\D+/', '', $normalized), -10);

        // Match an existing account by stored phone (normalized or bare 10-digit).
        $user = User::whereNotNull('phone')
            ->where(function ($q) use ($normalized, $last10) {
                $q->where('phone', $normalized)->orWhereRaw('RIGHT(phone, 10) = ?', [$last10]);
            })
            ->first();

        $isNew = false;
        if (! $user) {
            $digits = preg_replace('/\D+/', '', $normalized);
            $user = User::create([
                'name' => $data['name'] ?: 'Traveller',
                // `users.email` is NOT NULL; placeholder derived from the phone.
                'email' => $digits . '@phone.leemroz.local',
                'phone' => $normalized,
                'password' => bin2hex(random_bytes(16)), // random; user can set later
                'is_active' => true,
            ]);
            $isNew = true;
        }

        if (! $user->is_active) {
            return $this->fail('Your account is inactive. Please contact support.', 403);
        }

        // Mark phone verified; backfill name for a brand-new account.
        $fill = ['phone_verified_at' => now()];
        if ($isNew === false && empty($user->phone)) {
            $fill['phone'] = $normalized;
        }
        $user->forceFill($fill)->save();

        return $this->ok([
            'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'phone_verified' => true,
            ],
            'is_new' => $isNew,
        ], $isNew ? 'Welcome!' : 'Signed in.');
    }
}
