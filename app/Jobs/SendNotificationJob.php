<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $toEmail,
        public string $subject,
        public string $body,
        public string $fromEmail,
        public string $fromName,
        public string $templateKey = 'general'
    ) {
    }

    public function handle(): void
    {
        try {
            Mail::raw($this->body, function ($message) {
                $message->to($this->toEmail)
                    ->subject($this->subject)
                    ->from($this->fromEmail, $this->fromName);
            });
        } catch (\Throwable $e) {
            Log::error("Queued email send failed for [{$this->templateKey}] to [{$this->toEmail}]: " . $e->getMessage());
            throw $e;
        }
    }
}
