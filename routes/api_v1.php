<?php

use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CabController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\FlightController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OtpAuthController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal REST API v1
|--------------------------------------------------------------------------
| Consistent JSON responses, API resources, form-request validation,
| authentication and rate limiting. Consumed by the mobile app; every
| response follows the {success, message, data} envelope (ApiResponse trait).
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::get('health', [HealthCheckController::class, 'check']);

    // ---- App bootstrap / config (public) ----
    Route::get('app-config', [AppConfigController::class, 'index']);

    // ---- Auth: email + password ----
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // ---- Catalogue (public) ----
    Route::get('destinations', [\App\Http\Controllers\Api\DestinationController::class, 'index']);
    Route::get('packages', [PackageController::class, 'index']);
    Route::get('packages/{package:slug}', [PackageController::class, 'show']);

    Route::get('flights/search', [FlightController::class, 'search']);
    Route::post('flights/quote', [FlightController::class, 'quote']);

    Route::get('hotels/search', [HotelController::class, 'search']);
    Route::get('hotels/{hotel:slug}', [HotelController::class, 'show']);
    Route::get('hotels/{hotel:slug}/rooms', [HotelController::class, 'rooms']);

    Route::get('cabs/search', [CabController::class, 'search']);

    // ---- Content & engagement (public reads) ----
    Route::get('offers', [OfferController::class, 'index']);
    Route::get('offers/{offer}', [OfferController::class, 'show']);
    Route::get('reviews', [ReviewController::class, 'index']);

    // Coupon preview + lead capture accept guests OR logged-in users.
    Route::post('coupons/apply', [CouponController::class, 'apply']);
    Route::post('leads', [LeadController::class, 'store']);

    // ---- First-party analytics ingestion (public, beacon; higher throttle) ----
    Route::middleware('throttle:300,1')->group(function () {
        Route::post('analytics/event', [\App\Http\Controllers\Api\AnalyticsController::class, 'event']);
        Route::post('analytics/session', [\App\Http\Controllers\Api\AnalyticsController::class, 'session']);
        Route::post('analytics/page-view', [\App\Http\Controllers\Api\AnalyticsController::class, 'pageView']);
    });

    // ---- Password reset (public, email OTP) — tightly throttled ----
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    // ---- Phone OTP login — tightly throttled to prevent SMS abuse ----
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('auth/otp/request', [OtpAuthController::class, 'request']);
        Route::post('auth/otp/verify', [OtpAuthController::class, 'verify']);
    });

    // ---- Social login (Google / Apple) ----
    Route::post('auth/social/{provider}', [SocialAuthController::class, 'login'])
        ->where('provider', 'google|apple');

    // ---- Authenticated ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        Route::apiResource('bookings', BookingController::class)->only(['index', 'show', 'store']);
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);

        // Native in-app payment (Razorpay) — server-authoritative amounts.
        Route::post('bookings/{booking}/payment/create-order', [PaymentController::class, 'createOrder']);
        Route::post('bookings/{booking}/payment/verify', [PaymentController::class, 'verify']);
        Route::get('bookings/{booking}/payment/status', [PaymentController::class, 'status']);

        Route::get('user/profile', [UserController::class, 'profile']);
        Route::put('user/profile', [UserController::class, 'updateProfile']);
        Route::get('user/saved-travellers', [UserController::class, 'savedTravellers']);
        Route::post('user/saved-travellers', [UserController::class, 'storeSavedTraveller']);

        // Wishlist (shared with website)
        Route::get('wishlist', [WishlistController::class, 'index']);
        Route::post('wishlist/toggle', [WishlistController::class, 'toggle']);

        // Reviews — posting requires auth (moderated)
        Route::post('reviews', [ReviewController::class, 'store']);

        // In-app notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    });
});
