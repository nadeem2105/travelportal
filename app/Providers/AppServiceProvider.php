<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Overlay DB-backed integration credentials (WhatsApp + AI providers)
        // onto config('services.*') so admins manage them from the panel and
        // .env stays a fallback. No-op before migration / if DB unavailable.
        app(\App\Services\Settings\IntegrationSettings::class)->applyToConfig();

        // Web requests apply the DB-backed mail settings on demand (right before
        // each send). Queue workers / console processes don't hit that path, so
        // apply them once at boot — otherwise queued mail (ShouldQueue Mailables,
        // SendNotificationJob) would fall back to .env instead of the admin's
        // SMTP config. Guarded internally, so it is safe before migrations run.
        if ($this->app->runningInConsole()) {
            app(\App\Services\MailConfigService::class)->apply();
        }

        \Illuminate\Support\Facades\Gate::before(function (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability) {
            if ($admin = auth('admin')->user()) {
                return $admin->can($ability);
            }
        });

        // Trip Operations: driver assignment → idempotent driver + customer comms.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\DriverAssigned::class,
            \App\Listeners\SendDriverAssignmentNotifications::class,
        );
    }
}
