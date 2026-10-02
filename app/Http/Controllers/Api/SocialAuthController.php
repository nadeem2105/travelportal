<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Social sign-in for the mobile app. The native app authenticates with Google
 * or Apple and sends us the resulting ID token; we verify it server-side, then
 * match/create a local user and issue a Sanctum token. No provider secrets are
 * stored — verification uses each provider's public key set.
 */
class SocialAuthController extends Controller
{
    use ApiResponse;

    /** POST /auth/social/{provider} where provider = google|apple. */
    public function login(Request $request, string $provider)
    {
        $data = $request->validate([
            'id_token' => 'required|string',
            'name' => 'nullable|string|max:80',
            'device_name' => 'nullable|string|max:80',
        ]);

        $profile = match ($provider) {
            'google' => $this->verifyGoogle($data['id_token']),
            'apple' => $this->verifyApple($data['id_token']),
            default => null,
        };

        if (! $profile) {
            return $this->fail('Could not verify your ' . ucfirst($provider) . ' sign-in.', 422);
        }

        $email = $profile['email'] ?? null;
        $name = $data['name'] ?: ($profile['name'] ?? 'Traveller');

        // Match by verified provider email; else create a lightweight account.
        $user = $email ? User::where('email', $email)->first() : null;

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email ?: ($provider . '_' . substr(sha1($profile['sub']), 0, 12) . '@social.local'),
                'password' => bin2hex(random_bytes(16)),
                'is_active' => true,
            ]);
        }

        if (! $user->is_active) {
            return $this->fail('Your account is inactive. Please contact support.', 403);
        }

        // Trust the provider's email verification.
        if ($email && ! $user->email_verified_at && ($profile['email_verified'] ?? false)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $this->ok([
            'token' => $user->createToken($data['device_name'] ?? ('mobile-' . $provider))->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ], 'Signed in with ' . ucfirst($provider) . '.');
    }

    /** Verify a Google ID token via the public tokeninfo endpoint. */
    private function verifyGoogle(string $idToken): ?array
    {
        try {
            $res = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
            if (! $res->successful()) {
                return null;
            }
            $p = $res->json();

            $allowed = (array) config('services.google.client_ids', []);
            if (! empty($allowed) && ! in_array($p['aud'] ?? '', $allowed, true)) {
                return null;
            }
            if (($p['iss'] ?? '') !== 'https://accounts.google.com' && ($p['iss'] ?? '') !== 'accounts.google.com') {
                return null;
            }

            return [
                'sub' => $p['sub'] ?? '',
                'email' => $p['email'] ?? null,
                'email_verified' => filter_var($p['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'name' => $p['name'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Google token verify failed: ' . $e->getMessage());

            return null;
        }
    }

    /** Verify an Apple identity token (RS256 JWT) against Apple's public keys. */
    private function verifyApple(string $idToken): ?array
    {
        try {
            [$h, $pl, $sig] = explode('.', $idToken) + [null, null, null];
            if (! $h || ! $pl || ! $sig) {
                return null;
            }

            $header = json_decode($this->b64($h), true) ?: [];
            $payload = json_decode($this->b64($pl), true) ?: [];
            $kid = $header['kid'] ?? null;
            if (! $kid) {
                return null;
            }

            $keys = Http::timeout(10)->get('https://appleid.apple.com/auth/keys')->json('keys') ?? [];
            $jwk = collect($keys)->firstWhere('kid', $kid);
            if (! $jwk) {
                return null;
            }

            $pem = $this->jwkToPem($jwk['n'], $jwk['e']);
            $signed = $h . '.' . $pl;
            $signature = $this->b64($sig);

            if (openssl_verify($signed, $signature, $pem, OPENSSL_ALGO_SHA256) !== 1) {
                return null;
            }

            // Claim checks: issuer, audience, expiry.
            if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
                return null;
            }
            $allowed = (array) config('services.apple.client_ids', []);
            if (! empty($allowed) && ! in_array($payload['aud'] ?? '', $allowed, true)) {
                return null;
            }
            if (($payload['exp'] ?? 0) < time()) {
                return null;
            }

            return [
                'sub' => $payload['sub'] ?? '',
                'email' => $payload['email'] ?? null,
                'email_verified' => filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'name' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Apple token verify failed: ' . $e->getMessage());

            return null;
        }
    }

    private function b64(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /** Build an RSA public-key PEM from JWK modulus (n) and exponent (e). */
    private function jwkToPem(string $n, string $e): string
    {
        $modulus = $this->b64($n);
        $exponent = $this->b64($e);

        $encodeLength = function (int $length): string {
            if ($length <= 0x7F) {
                return chr($length);
            }
            $temp = ltrim(pack('N', $length), "\x00");

            return chr(0x80 | strlen($temp)) . $temp;
        };

        $encodeInteger = function (string $bytes) use ($encodeLength): string {
            if (ord($bytes[0]) > 0x7F) {
                $bytes = "\x00" . $bytes; // keep it positive
            }

            return "\x02" . $encodeLength(strlen($bytes)) . $bytes;
        };

        $rsaKey = $encodeInteger($modulus) . $encodeInteger($exponent);
        $rsaKey = "\x30" . $encodeLength(strlen($rsaKey)) . $rsaKey;

        // AlgorithmIdentifier for rsaEncryption + BIT STRING wrapper.
        $bitString = "\x03" . $encodeLength(strlen($rsaKey) + 1) . "\x00" . $rsaKey;
        $algId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $spki = "\x30" . $encodeLength(strlen($algId) + strlen($bitString)) . $algId . $bitString;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}
