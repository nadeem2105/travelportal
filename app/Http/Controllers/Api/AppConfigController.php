<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Destination;
use App\Models\HomepageSection;
use App\Models\Offer;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;

/**
 * Bootstrap/config endpoint for the mobile app. Returns everything the app
 * needs on cold start in one call: branding, support contact, feature flags
 * (which login methods + payment gateway are live), promo banners, the
 * homepage section order, and featured destinations. Public + cacheable.
 *
 * All values are read from the SAME sources the website uses (settings(),
 * Banner, HomepageSection, Destination), so app and web stay in sync.
 */
class AppConfigController extends Controller
{
    use ApiResponse;

    public function index(Request $request, PaymentManager $payments)
    {
        return $this->ok([
            'app' => [
                'name' => settings('company_name', 'Leemroz Travels'),
                'logo' => asset(img(settings('company_logo', 'images/logo.svg'))),
                'currency' => settings('default_currency', 'INR'),
                'currency_symbol' => settings('currency_symbol', '₹'),
            ],
            'support' => [
                'phone' => settings('company_phone', '+91 70069 76447'),
                'email' => settings('company_email', 'hello@leemroztravels.com'),
                'whatsapp' => preg_replace('/\D+/', '', settings('company_whatsapp', settings('company_phone', '+917006976447'))),
                'address' => settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025'),
            ],
            'features' => $this->features($payments),
            'banners' => $this->banners(),
            'sections' => $this->sections(),
            'destinations' => $this->featuredDestinations(),
            'offers_available' => Offer::active()->count(),
        ]);
    }

    /** Feature flags so the app can show/hide login + payment options. */
    private function features(PaymentManager $payments): array
    {
        $gateway = null;
        try {
            $gateway = $payments->activeGateway();
        } catch (\Throwable $e) {
            // never fail the bootstrap call over a gateway lookup
        }

        return [
            'auth' => [
                'email_password' => true,
                'phone_otp' => (bool) config('sms.enabled'),
                'google' => count((array) config('services.google.client_ids', [])) > 0,
                'apple' => count((array) config('services.apple.client_ids', [])) > 0,
            ],
            'payments' => [
                'enabled' => (bool) $gateway,
                'provider' => $gateway?->code,
                'currency' => $gateway?->currency ?? settings('default_currency', 'INR'),
            ],
        ];
    }

    private function banners(): array
    {
        $now = now();

        return Banner::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'image' => asset(img($b->image)),
                'link_url' => $b->link_url,
                'button_text' => $b->button_text,
                'position' => $b->position,
            ])
            ->all();
    }

    private function sections(): array
    {
        return HomepageSection::enabled()->get()
            ->map(fn (HomepageSection $s) => [
                'type' => $s->type,
                'title' => $s->title ?? null,
            ])
            ->all();
    }

    private function featuredDestinations(): array
    {
        return Destination::where('status', 'active')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(12)
            ->get()
            ->map(fn (Destination $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'slug' => $d->slug,
                'region' => $d->region ?? null,
                'image' => asset(img($d->cover_image)),
                'is_featured' => (bool) $d->is_featured,
            ])
            ->all();
    }
}
