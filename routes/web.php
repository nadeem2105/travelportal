<?php

use App\Http\Controllers\Site;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Website
|--------------------------------------------------------------------------
*/

Route::get('/', [Site\HomeController::class, 'index'])->name('home');
Route::get('/search', [Site\HomeController::class, 'globalSearch'])->name('search');

// Offers
Route::get('/offers', [Site\OfferController::class, 'index'])->name('offers.index');
Route::get('/offers/{offer:slug}', [Site\OfferController::class, 'show'])->name('offers.show');

// Destinations
Route::get('/destinations', [Site\DestinationController::class, 'index'])->name('destinations.index');
Route::get('/destinations/{destination:slug}', [Site\DestinationController::class, 'show'])->name('destinations.show');

// Packages
Route::get('/packages', [Site\PackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package:slug}', [Site\PackageController::class, 'show'])->name('packages.show');

// Flights
Route::get('/flights', [Site\FlightController::class, 'index'])->name('flights.index');
Route::get('/flights/results', [Site\FlightController::class, 'results'])->name('flights.results');
Route::post('/flights/select', [Site\FlightController::class, 'select'])->name('flights.select');
Route::get('/flights/travellers', [Site\FlightController::class, 'travellers'])->name('flights.travellers');
Route::post('/flights/book', [Site\FlightController::class, 'book'])->name('flights.book');

// Hotels
Route::get('/hotels', [Site\HotelController::class, 'index'])->name('hotels.index');
Route::get('/hotels/search', [Site\HotelController::class, 'search'])->name('hotels.search');
Route::get('/hotels/{hotel:slug}', [Site\HotelController::class, 'show'])->name('hotels.show');
Route::post('/hotels/book', [Site\HotelController::class, 'book'])->name('hotels.book');

// Cabs
Route::get('/cabs', [Site\CabController::class, 'index'])->name('cabs.index');
Route::get('/cabs/search', [Site\CabController::class, 'search'])->name('cabs.search');
Route::get('/cabs/book', [Site\CabController::class, 'details'])->name('cabs.details');
Route::post('/cabs/book', [Site\CabController::class, 'book'])->name('cabs.book');

// Packages booking (multi-step: travel details → travellers → billing)
Route::get('/packages/{package:slug}/book', [Site\PackageController::class, 'bookingForm'])->name('packages.booking-form');
Route::post('/packages/{package:slug}/book', [Site\PackageController::class, 'book'])->name('packages.book');
Route::post('/packages/{package:slug}/quote', [Site\PackageController::class, 'quote'])->name('packages.quote');

// CRM public lead capture (contact/package/hotel/flight/cab/get-quote/callback/WhatsApp forms)
Route::post('/leads/capture', [Site\LeadCaptureController::class, 'store'])
    ->middleware('throttle:20,1')->name('leads.capture');

// Public quotation (token-authenticated — customer view / accept / decline)
Route::get('/quote/{token}', [Site\QuotationController::class, 'show'])->name('quote.show');
Route::get('/quote/{token}/pdf', [Site\QuotationController::class, 'pdf'])->name('quote.pdf');
Route::post('/quote/{token}/accept', [Site\QuotationController::class, 'accept'])
    ->middleware('throttle:10,1')->name('quote.accept');
Route::post('/quote/{token}/decline', [Site\QuotationController::class, 'reject'])
    ->middleware('throttle:10,1')->name('quote.decline');

// Checkout & payments
Route::get('/checkout/{booking:booking_reference}', [Site\CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout/{booking:booking_reference}/coupon', [Site\CheckoutController::class, 'applyCoupon'])->name('checkout.coupon');
Route::post('/checkout/{booking:booking_reference}/pay', [Site\CheckoutController::class, 'pay'])->name('checkout.pay');
Route::post('/checkout/{booking:booking_reference}/mock-pay', [Site\CheckoutController::class, 'mockPay'])->name('checkout.mock_pay');
Route::post('/payments/callback', [Site\CheckoutController::class, 'callback'])->name('payments.callback');
Route::post('/webhooks/razorpay', [Site\WebhookController::class, 'razorpay'])->name('webhooks.razorpay');

// WhatsApp Cloud API webhook (GET = verification handshake, POST = events)
Route::get('/webhooks/whatsapp', [Site\WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [Site\WhatsAppWebhookController::class, 'receive'])->name('webhooks.whatsapp');

// Meta Lead Ads webhook (GET verify, POST leadgen)
Route::get('/webhooks/meta', [Site\MetaLeadWebhookController::class, 'verify'])->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [Site\MetaLeadWebhookController::class, 'receive'])->name('webhooks.meta');

// Google Ads Lead Form webhook (POST, key-authenticated)
Route::post('/webhooks/google-leads', [Site\GoogleLeadWebhookController::class, 'receive'])->name('webhooks.google-leads');

Route::get('/booking/{booking:booking_reference}/confirmation', [Site\CheckoutController::class, 'confirmation'])
    ->name('booking.confirmation');
Route::get('/booking/{booking:booking_reference}/pdf', [Site\AccountController::class, 'invoicePdf'])
    ->name('booking.pdf');
Route::get('/booking/{booking:booking_reference}/invoice', [Site\CheckoutController::class, 'invoice'])
    ->name('booking.invoice');
Route::get('/booking/{booking:booking_reference}/itinerary', [Site\CheckoutController::class, 'itinerary'])
    ->name('booking.itinerary');


// Kashmir guide, blog, pages
Route::get('/guide', [Site\GuideController::class, 'index'])->name('guide.index');
Route::get('/guide/{guide:slug}', [Site\GuideController::class, 'show'])->name('guide.show');
Route::get('/blog', [Site\BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{blog:slug}', [Site\BlogController::class, 'show'])->name('blog.show');
Route::get('/reviews', [Site\ReviewController::class, 'index'])->name('reviews.index');
Route::get('/faq', [Site\PageController::class, 'faq'])->name('faq');
Route::get('/cookie-policy', function () {
    return view('pages.cookie-policy', ['seo' => ['title' => 'Cookie Policy', 'description' => 'How Leemroz Travels uses cookies and similar technologies.', 'robots' => 'index,follow']]);
})->name('cookie-policy');
Route::get('/privacy-policy', function () {
    return view('pages.privacy-policy', ['seo' => ['title' => 'Privacy Policy', 'description' => 'How Leemroz Travels collects, uses and protects your data.', 'robots' => 'index,follow']]);
})->name('privacy-policy');
Route::get('/contact', [Site\PageController::class, 'contact'])->name('contact');
Route::post('/contact', [Site\PageController::class, 'submitContact'])->name('contact.submit');
Route::post('/newsletter/subscribe', [Site\PageController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/p/{page:slug}', [Site\PageController::class, 'show'])->name('page.show');

// Support (public form; linking to account when logged in)
Route::get('/support', [Site\SupportController::class, 'index'])->name('support.index');
Route::post('/support', [Site\SupportController::class, 'store'])->name('support.store');

// Wishlist toggle (auth required)
Route::middleware('customer.auth')->group(function () {
    Route::post('/wishlist/toggle', [Site\WishlistController::class, 'toggle'])->name('wishlist.toggle');
});

/*
|--------------------------------------------------------------------------
| Customer Account
|--------------------------------------------------------------------------
*/

Route::middleware('customer.auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [Site\AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/trips', [Site\AccountController::class, 'trips'])->name('trips');
    Route::get('/bookings/{booking:booking_reference}', [Site\AccountController::class, 'showBooking'])->name('booking.show');
    Route::get('/bookings/{booking:booking_reference}/invoice', [Site\AccountController::class, 'invoice'])->name('booking.invoice');
    Route::get('/bookings/{booking:booking_reference}/invoice-pdf', [Site\AccountController::class, 'invoicePdf'])->name('booking.invoice-pdf');
    Route::get('/bookings/{booking:booking_reference}/itinerary', [Site\AccountController::class, 'itinerary'])->name('booking.itinerary');
    Route::post('/bookings/{booking:booking_reference}/cancel', [Site\AccountController::class, 'cancelBooking'])->name('booking.cancel');
    Route::get('/profile', [Site\AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [Site\AccountController::class, 'updateProfile'])->name('profile.update');
    Route::get('/travellers', [Site\AccountController::class, 'travellers'])->name('travellers');
    Route::post('/travellers', [Site\AccountController::class, 'storeTraveller'])->name('travellers.store');
    Route::delete('/travellers/{traveller}', [Site\AccountController::class, 'destroyTraveller'])->name('travellers.destroy');
    Route::get('/wishlist', [Site\AccountController::class, 'wishlist'])->name('wishlist');
    Route::post('/reviews', [Site\AccountController::class, 'storeReview'])->name('reviews.store');
    Route::get('/notifications', [Site\AccountController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read-all', [Site\AccountController::class, 'markNotificationsRead'])->name('notifications.read_all');
    Route::get('/security', [Site\AccountController::class, 'security'])->name('security');
    Route::put('/security/password', [Site\AccountController::class, 'updatePassword'])->name('security.password');
    Route::post('/logout', [Site\AuthController::class, 'logout'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Guest auth
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [Site\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Site\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    Route::get('/register', [Site\AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [Site\AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');

    // Passwordless mobile OTP sign-in (session / 'web' guard)
    Route::get('/login/otp', [Site\AuthController::class, 'showOtpLogin'])->name('login.otp');
    Route::post('/login/otp', [Site\AuthController::class, 'requestOtp'])->middleware('throttle:6,1')->name('login.otp.request');
    Route::post('/login/otp/verify', [Site\AuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('login.otp.verify');
    Route::post('/login/otp/resend', [Site\AuthController::class, 'resendOtp'])->middleware('throttle:4,1')->name('login.otp.resend');
    Route::get('/login/otp/change', [Site\AuthController::class, 'otpChangeNumber'])->name('login.otp.change');

    // Google sign-in (Google Identity Services posts the ID token here)
    Route::post('/auth/google', [Site\AuthController::class, 'googleCallback'])->middleware('throttle:10,1')->name('login.google');

    // Facebook sign-in (Facebook JS SDK posts the user access token here)
    Route::post('/auth/facebook', [Site\AuthController::class, 'facebookCallback'])->middleware('throttle:10,1')->name('login.facebook');

    // Password reset (customer accounts)
    Route::get('/forgot-password', [Site\AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [Site\AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [Site\AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [Site\AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| B2B Agent Portal
|--------------------------------------------------------------------------
*/

// Public agent auth (redirects to dashboard if already signed in as an agent).
Route::middleware('guest:agent')->group(function () {
    Route::get('/agent/login', [Site\Agent\AuthController::class, 'showLogin'])->name('agent.login');
    Route::post('/agent/login', [Site\Agent\AuthController::class, 'login'])->middleware('throttle:10,1')->name('agent.login.attempt');
    Route::get('/agent/apply', [Site\Agent\AuthController::class, 'showApply'])->name('agent.apply');
    Route::post('/agent/apply', [Site\Agent\AuthController::class, 'apply'])->middleware('throttle:5,1')->name('agent.apply.submit');

    Route::get('/agent/forgot-password', [Site\Agent\AuthController::class, 'showForgotPassword'])->name('agent.password.request');
    Route::post('/agent/forgot-password', [Site\Agent\AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('agent.password.email');
    Route::get('/agent/reset-password/{token}', [Site\Agent\AuthController::class, 'showResetPassword'])->name('agent.password.reset');
    Route::post('/agent/reset-password', [Site\Agent\AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('agent.password.update');
});

Route::middleware('agent.auth')->prefix('agent')->name('agent.')->group(function () {
    Route::get('/dashboard', [Site\Agent\DashboardController::class, 'index'])->name('dashboard');

    // Wallet & credit ledger
    Route::get('/wallet', [Site\Agent\WalletController::class, 'index'])->name('wallet');

    // Profile & KYC
    Route::get('/profile', [Site\Agent\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [Site\Agent\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/documents', [Site\Agent\ProfileController::class, 'uploadDocuments'])->name('profile.documents');
    Route::put('/profile/password', [Site\Agent\ProfileController::class, 'updatePassword'])->name('profile.password');

    // Browse & book packages with agent pricing
    Route::get('/packages', [Site\Agent\BookingController::class, 'packages'])->name('packages');
    Route::get('/packages/{package:slug}/book', [Site\Agent\BookingController::class, 'create'])->name('packages.create');
    Route::post('/packages/{package:slug}/book', [Site\Agent\BookingController::class, 'store'])->middleware('throttle:20,1')->name('packages.store');

    // Bookings & commission
    Route::get('/bookings', [Site\Agent\BookingController::class, 'index'])->name('bookings');
    Route::get('/bookings/{booking:booking_reference}', [Site\Agent\BookingController::class, 'show'])->name('bookings.show');
    Route::get('/commission', [Site\Agent\BookingController::class, 'commission'])->name('commission');

    Route::post('/logout', [Site\Agent\AuthController::class, 'logout'])->name('logout');
});

// SEO & Diagnostics
Route::get('/sitemap.xml', [Site\SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [Site\SitemapController::class, 'robots'])->name('robots');
Route::get('/health', [\App\Http\Controllers\Api\HealthCheckController::class, 'check'])->name('health');

require __DIR__ . '/admin.php';
