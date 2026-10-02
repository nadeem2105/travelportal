<?php

namespace App\Services;

use App\Mail\TestEmailMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailConfigService
{
    public function __construct(protected SettingsService $settings)
    {
    }

    /**
     * Dynamically apply database mail settings to Laravel runtime configuration.
     */
    public function apply(): void
    {
        try {
            $mailer = (string) $this->settings->get('mail_mailer', config('mail.default', 'smtp'));
            $host = (string) $this->settings->get('mail_host', config('mail.mailers.smtp.host', '127.0.0.1'));
            $port = (int) $this->settings->get('mail_port', config('mail.mailers.smtp.port', 587));
            $encryption = (string) $this->settings->get('mail_encryption', 'tls');
            $username = (string) $this->settings->get('mail_username', '');
            $password = (string) $this->settings->get('mail_password', '');
            $fromAddress = (string) $this->settings->get('mail_from_address', config('mail.from.address', 'hello@leemroztravels.com'));
            $fromName = (string) $this->settings->get('mail_from_name', config('mail.from.name', 'Leemroz Travels'));

            Config::set('mail.default', $mailer);

            if ($mailer === 'smtp') {
                Config::set('mail.mailers.smtp.host', $host);
                Config::set('mail.mailers.smtp.port', $port);
                Config::set('mail.mailers.smtp.encryption', ($encryption === 'none' || empty($encryption)) ? null : $encryption);
                Config::set('mail.mailers.smtp.username', $username ?: null);
                Config::set('mail.mailers.smtp.password', $password ?: null);
            }

            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName);

            // Purge previously resolved mailer instance so refreshed configs take effect
            if (app()->resolved('mail.manager')) {
                app('mail.manager')->purge();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load dynamic mail configuration from database: ' . $e->getMessage());
        }
    }

    /**
     * Check if email notifications are active in settings.
     */
    public function isEnabled(): bool
    {
        try {
            return (bool) $this->settings->get('mail_notifications_enabled', true);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Send a diagnostic test email to verify SMTP credentials and network connectivity.
     */
    public function testConnection(string $toEmail): array
    {
        $this->apply();

        try {
            Mail::to($toEmail)->send(new TestEmailMail([
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption') ?? 'none',
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'timestamp' => now()->toDayDateTimeString(),
            ]));

            return [
                'success' => true,
                'message' => "Test email successfully dispatched to {$toEmail}.",
            ];
        } catch (\Throwable $e) {
            Log::error('SMTP Test email failed: ' . $e->getMessage(), ['to' => $toEmail]);

            return [
                'success' => false,
                'message' => 'Email delivery failed: ' . $e->getMessage(),
            ];
        }
    }
}
