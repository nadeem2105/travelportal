<?php

namespace App\Http\Controllers\Site\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // --- Login -----------------------------------------------------------

    public function showLogin()
    {
        return view('agent.auth.login', ['seo' => ['title' => 'Agent Login', 'description' => 'Sign in to the B2B agent portal']]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $agent = Agent::where('email', $credentials['email'])->first();

        if (! $agent || empty($agent->password) || ! Auth::guard('agent')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        // Enforce account state after a successful credential match.
        if ($agent->status === 'suspended') {
            Auth::guard('agent')->logout();

            return back()->withErrors(['email' => 'Your agent account has been suspended. Please contact support.']);
        }

        if (! $agent->isApproved()) {
            Auth::guard('agent')->logout();

            return back()->withErrors(['email' => 'Your application is still under review. We will notify you once it is approved.']);
        }

        $agent->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();

        return redirect()->intended(route('agent.dashboard'));
    }

    // --- Apply as agent (public registration → pending) ------------------

    public function showApply()
    {
        return view('agent.auth.apply', ['seo' => ['title' => 'Become a Travel Agent', 'description' => 'Apply to join our B2B agent network']]);
    }

    public function apply(Request $request)
    {
        $validated = $request->validate([
            'agency_name' => 'required|string|max:150',
            'contact_person' => 'required|string|max:120',
            'email' => 'required|email|max:150|unique:agents,email',
            'phone' => 'required|string|max:25',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:60',
            'pincode' => 'nullable|string|max:12',
            'address' => 'nullable|string|max:500',
            'pan_number' => 'required|string|max:20',
            'gst_number' => 'nullable|string|max:20',
            'iata_code' => 'nullable|string|max:20',
            'pan_document' => 'required|file|mimes:jpeg,jpg,png,pdf|max:4096',
            'gst_document' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:4096',
            'business_license' => 'required|file|mimes:jpeg,jpg,png,pdf|max:4096',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => 'accepted',
        ]);

        $agent = Agent::create([
            'agency_name' => $validated['agency_name'],
            'agency_code' => $this->generateAgencyCode($validated['agency_name']),
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'address' => $validated['address'] ?? null,
            'pan_number' => $validated['pan_number'],
            'gst_number' => $validated['gst_number'] ?? null,
            'iata_code' => $validated['iata_code'] ?? null,
            'password' => $validated['password'],
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        // Store KYC documents under the agent's folder now that we have its id.
        $docUpdates = [];
        foreach ([
            'logo' => 'logo_path',
            'pan_document' => 'pan_document',
            'gst_document' => 'gst_document',
            'business_license' => 'business_license',
        ] as $field => $column) {
            if ($request->hasFile($field)) {
                $docUpdates[$column] = $request->file($field)->store('agents/'.$agent->id, 'public');
            }
        }
        if ($docUpdates) {
            $agent->update($docUpdates);
        }

        ActivityLogger::log('agent.applied', 'agents', 'New agent application: '.$agent->agency_name, [
            'agent_id' => $agent->id,
            'agency_name' => $agent->agency_name,
            'email' => $agent->email,
        ]);

        return redirect()->route('agent.login')->with('success',
            'Thank you for applying! Your application (code '.$agent->agency_code.') is under review. We will notify you once it is approved, after which you can sign in.');
    }

    protected function generateAgencyCode(string $agencyName): string
    {
        $prefix = strtoupper(Str::of($agencyName)->replaceMatches('/[^A-Za-z]/', '')->substr(0, 3)->padRight(3, 'X'));

        do {
            $code = 'AG-'.$prefix.'-'.strtoupper(Str::random(4));
        } while (Agent::where('agency_code', $code)->exists());

        return $code;
    }

    public function logout(Request $request)
    {
        Auth::guard('agent')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('agent.login');
    }

    // --- Password reset (agent 'agents' broker) --------------------------

    public function showForgotPassword()
    {
        return view('agent.auth.forgot-password', ['seo' => ['title' => 'Forgot Password', 'description' => 'Reset your agent portal password']]);
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        PasswordBroker::broker('agents')->sendResetLink($request->only('email'));

        // Neutral response to avoid leaking which emails are registered.
        return back()->with('success', 'If that email is registered, a password reset link is on its way.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('agent.auth.reset-password', [
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

        $status = PasswordBroker::broker('agents')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Agent $agent, string $password) {
                $agent->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($agent));
            }
        );

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return redirect()->route('agent.login')->with('success', 'Password reset successfully. Please sign in.');
        }

        return back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
