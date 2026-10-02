<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Admin;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends templated notifications (email/SMS-ready) using admin-editable
 * templates from the notification_templates table.
 */
class NotificationService
{
    public function __construct(
        protected SettingsService $settings,
        protected ?SmsService $sms = null,
        protected ?MailConfigService $mailConfig = null
    ) {
        $this->sms = $sms ?? app(SmsService::class);
        $this->mailConfig = $mailConfig ?? app(MailConfigService::class);
    }

    public function send(string $key, array $variables, string $toEmail, ?string $toPhone = null, bool $queue = true): bool
    {
        // Dispatch SMS if recipient phone number is provided
        if ($toPhone) {
            $smsTemplate = NotificationTemplate::where('key', $key)->where('channel', 'sms')->where('is_active', true)->first();
            $smsBody = $smsTemplate
                ? $smsTemplate->render($variables)['body']
                : "Leemroz Travels: Your {$key} notification. Ref: " . ($variables['booking_id'] ?? $key);
            $this->sms->send($toPhone, $smsBody, ['template' => $key]);
        }

        if (! $this->mailConfig->isEnabled()) {
            return false;
        }

        $this->mailConfig->apply();

        $template = NotificationTemplate::where('key', $key)->where('channel', 'email')->where('is_active', true)->first();

        if (! $template) {
            return false;
        }

        $rendered = $template->render($variables);
        $fromEmail = $this->settings->get('mail_from_address', config('mail.from.address', 'hello@leemroztravels.com'));
        $fromName = $this->settings->get('mail_from_name', config('mail.from.name', 'Leemroz Travels'));

        if ($queue && config('queue.default') !== 'sync') {
            \App\Jobs\SendNotificationJob::dispatch(
                $toEmail,
                $rendered['subject'],
                $rendered['body'],
                $fromEmail,
                $fromName,
                $key
            );

            return true;
        }

        try {
            Mail::raw($rendered['body'], function ($message) use ($toEmail, $rendered, $fromEmail, $fromName) {
                $message->to($toEmail)
                    ->subject($rendered['subject'])
                    ->from($fromEmail, $fromName);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Email send failed: ' . $e->getMessage(), ['template' => $key, 'to' => $toEmail]);

            return false;
        }
    }

    public function sendUserNotification(int $userId, string $title, string $body, string $type = 'general', array $data = []): void
    {
        UserNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data ?: null,
        ]);
    }

    public function sendBookingConfirmation(Booking $booking, bool $confirmed): void
    {
        $recipientEmail = $booking->contact['email'] ?? $booking->user?->email;
        $recipientPhone = $booking->contact['phone'] ?? $booking->user?->phone;

        $destination = $this->bookingDestination($booking);
        if (empty($destination)) {
            $destination = ucfirst($booking->product_type ?? 'Tour');
        }

        $travelDate = $this->bookingTravelDate($booking);
        if (empty($travelDate)) {
            $travelDate = $booking->created_at?->format('d M Y') ?? date('d M Y');
        }

        $variables = [
            'name' => $booking->contact['first_name'] ?? ($booking->user?->name ?? 'Traveller'),
            'booking_id' => $booking->booking_reference,
            'amount' => '₹' . number_format((float) $booking->total_amount, 2),
            'destination' => $destination,
            'travel_date' => $travelDate,
            'ticket_url' => route('account.booking.show', $booking),
            'invoice_url' => route('account.booking.invoice', $booking),
            'status' => $confirmed ? 'Confirmed' : 'Payment Received – Being Processed',
        ];

        if ($confirmed) {
            // Dispatch rich HTML product confirmation email
            if ($recipientEmail && $this->mailConfig->isEnabled()) {
                $this->mailConfig->apply();
                try {
                    Mail::to($recipientEmail)->send(new \App\Mail\BookingConfirmationMail($booking));
                } catch (\Throwable $e) {
                    Log::error("Failed to send booking confirmation email [{$booking->booking_reference}]: " . $e->getMessage());
                }
            }

            // Dispatch confirmation SMS
            if ($recipientPhone) {
                $smsMsg = "Leemroz Travels: Booking {$booking->booking_reference} confirmed for {$variables['destination']}. Travel Date: {$variables['travel_date']}. View: {$variables['ticket_url']}";
                $this->sms->send($recipientPhone, $smsMsg, ['template' => 'booking_confirmed']);
            }

            // Queue WhatsApp confirmation (approved template). Best-effort — the
            // job renders any attachment PDF and sends on a worker, so the booking
            // flow never blocks on Graph API / PDF rendering.
            // Body vars {{1}}=name {{2}}=booking ref {{3}}=destination {{4}}=travel date {{5}}=amount
            if ($recipientPhone) {
                // Prefer the product itinerary/voucher; fall back to the invoice.
                $attachItinerary = (bool) config('services.whatsapp.attach_documents.booking_itinerary', false);
                $attachInvoice = (bool) config('services.whatsapp.attach_documents.booking_confirmed', false);
                $documentType = $attachItinerary ? 'itinerary' : ($attachInvoice ? 'invoice' : null);

                // Prefer a product-specific voucher template (hotel/cab) when the
                // admin has configured one; otherwise fall back to the generic
                // booking_confirmed template. Body variables are the same 5-field
                // order for all of them (name, ref, destination/hotel/route,
                // date, amount), so no param changes are needed.
                $voucherEvent = match ($booking->product_type) {
                    'hotel' => 'hotel_voucher',
                    'cab' => 'cab_voucher',
                    default => 'booking_confirmed',
                };
                if (! config("services.whatsapp.templates.{$voucherEvent}")) {
                    $voucherEvent = 'booking_confirmed';
                }

                \App\Jobs\SendWhatsAppNotification::dispatch(
                    $voucherEvent,
                    $recipientPhone,
                    [$variables['name'], $booking->booking_reference, $variables['destination'], $variables['travel_date'], $variables['amount']],
                    $variables['name'],
                    $booking->id,
                    $documentType,
                );

                // Queue the separate Tax Invoice WhatsApp template (with Invoice PDF)
                // if configured. Delayed so it does NOT hit Meta in the same instant
                // as the itinerary message above — two templates to the same number
                // within the same second is a trigger for Meta's "healthy ecosystem
                // engagement" drop. The itinerary goes first (higher priority); the
                // invoice follows ~90s later.
                if (config('services.whatsapp.templates.booking_invoice')) {
                    $this->queueBookingInvoiceWhatsApp($booking, now()->addSeconds(90));
                }
            }
        } else {
            // Processing notification
            if ($recipientEmail) {
                $this->send('booking_processing', $variables, $recipientEmail, $recipientPhone);
            }
        }

        if ($booking->user_id) {
            $this->sendUserNotification(
                $booking->user_id,
                $confirmed ? 'Booking Confirmed ✓' : 'Payment Received',
                $confirmed
                    ? "Your booking {$booking->booking_reference} is confirmed. {$variables['destination']}"
                    : "We received your payment for {$booking->booking_reference}. We're confirming with the supplier.",
                'booking',
                ['booking_reference' => $booking->booking_reference]
            );
        }
    }

    public function sendCancellation(Booking $booking, float $refundAmount): void
    {
        $recipientEmail = $booking->contact['email'] ?? $booking->user?->email;
        $recipientPhone = $booking->contact['phone'] ?? $booking->user?->phone;
        $refundDays = (string) $this->settings->get('refund_processing_days', '5-7');

        $variables = [
            'name' => $booking->contact['first_name'] ?? ($booking->user?->name ?? 'Traveller'),
            'booking_id' => $booking->booking_reference,
            'amount' => '₹' . number_format($refundAmount, 2),
            'refund_days' => $refundDays,
        ];

        if ($recipientEmail && $this->mailConfig->isEnabled()) {
            $this->mailConfig->apply();
            try {
                Mail::to($recipientEmail)->send(new \App\Mail\BookingCancellationMail($booking, $refundAmount, $refundDays));
            } catch (\Throwable $e) {
                Log::error("Failed to send booking cancellation email [{$booking->booking_reference}]: " . $e->getMessage());
            }
        }

        if ($recipientPhone) {
            $smsMsg = "Leemroz Travels: Booking {$booking->booking_reference} has been cancelled. Refund of {$variables['amount']} will be processed in {$refundDays} days.";
            $this->sms->send($recipientPhone, $smsMsg, ['template' => 'booking_cancelled']);

            // Queue WhatsApp cancellation (approved template). Best-effort.
            // Body vars {{1}}=name {{2}}=booking ref {{3}}=refund amount {{4}}=refund days
            \App\Jobs\SendWhatsAppNotification::dispatch(
                'booking_cancelled',
                $recipientPhone,
                [$variables['name'], $booking->booking_reference, $variables['amount'], $refundDays],
                $variables['name'],
            );
        }

        if ($booking->user_id) {
            $this->sendUserNotification(
                $booking->user_id,
                'Booking Cancelled',
                "Your booking {$booking->booking_reference} was cancelled. Refund of {$variables['amount']} will reach you in {$variables['refund_days']} working days.",
                'cancellation',
                ['booking_reference' => $booking->booking_reference]
            );
        }
    }

    public function sendOtp(string $email, string $code): bool
    {
        return $this->send('otp', ['otp' => $code, 'name' => $email], $email);
    }

    public function sendWelcome(User $user): void
    {
        $this->send('welcome', ['name' => $user->name], $user->email);
    }

    public function notifyAdmins(Booking $booking): void
    {
        $email = $this->settings->get('company_email');

        if ($email) {
            $this->send('admin_new_booking', [
                'booking_id' => $booking->booking_reference,
                'amount' => '₹' . number_format((float) $booking->total_amount, 2),
                'product' => ucfirst($booking->product_type),
                'customer' => $booking->contact['first_name'] ?? ($booking->user?->name ?? 'Guest'),
            ], $email);
        }
    }

    /**
     * Dispatch a dedicated Tax Invoice WhatsApp template with the Invoice PDF attached.
     */
    public function sendBookingInvoiceWhatsApp(Booking $booking): array
    {
        $recipientPhone = $booking->contact['phone'] ?? $booking->user?->phone;
        if (! $recipientPhone) {
            return ['success' => false, 'error' => 'No recipient phone number'];
        }

        $document = null;
        if (config('services.whatsapp.attach_documents.booking_invoice', true)) {
            try {
                $document = [
                    'bytes' => app(\App\Services\PdfDocumentService::class)->invoice($booking),
                    'filename' => "Invoice-{$booking->booking_reference}.pdf",
                ];
            } catch (\Throwable $e) {
                Log::warning('Invoice PDF for WhatsApp invoice event failed: ' . $e->getMessage());
            }
        }

        $name = $booking->contact['first_name'] ?? ($booking->user?->name ?? 'Traveller');
        $invoiceDate = $booking->created_at?->format('d M Y') ?? date('d M Y');
        $amount = '₹' . number_format((float) $booking->total_amount, 2);
        $destination = $this->bookingDestination($booking) ?: ucfirst($booking->product_type ?? 'Tour');

        return app(\App\Services\WhatsApp\WhatsAppService::class)->notifyEvent(
            'booking_invoice',
            $recipientPhone,
            [$name, $booking->booking_reference, $invoiceDate, $amount, $destination],
            $name,
            $document,
        );
    }

    /**
     * Queue the dedicated Tax Invoice WhatsApp template (Invoice PDF rendered on
     * the worker). Mirrors sendBookingInvoiceWhatsApp() but off the request cycle;
     * the synchronous variant is kept for callers (e.g. BookingController) that
     * need the send result immediately.
     */
    public function queueBookingInvoiceWhatsApp(Booking $booking, ?\DateTimeInterface $delayUntil = null): void
    {
        $recipientPhone = $booking->contact['phone'] ?? $booking->user?->phone;
        if (! $recipientPhone) {
            return;
        }

        $name = $booking->contact['first_name'] ?? ($booking->user?->name ?? 'Traveller');
        $invoiceDate = $booking->created_at?->format('d M Y') ?? date('d M Y');
        $amount = '₹' . number_format((float) $booking->total_amount, 2);
        $destination = $this->bookingDestination($booking) ?: ucfirst($booking->product_type ?? 'Tour');
        $documentType = config('services.whatsapp.attach_documents.booking_invoice', true) ? 'invoice' : null;

        $job = \App\Jobs\SendWhatsAppNotification::dispatch(
            'booking_invoice',
            $recipientPhone,
            [$name, $booking->booking_reference, $invoiceDate, $amount, $destination],
            $name,
            $booking->id,
            $documentType,
        );

        if ($delayUntil) {
            $job->delay($delayUntil);
        }
    }

    protected function bookingDestination(Booking $booking): string
    {
        return match ($booking->product_type) {
            'flight' => trim(($booking->flight?->journey['segments'][0]['from']['city'] ?? '') . ' → ' . ($booking->flight?->journey['segments'][0]['to']['city'] ?? '')),
            'hotel' => $booking->hotelBooking?->hotel_name ?? '',
            'cab' => trim($booking->cab?->pickup_location . ' → ' . $booking->cab?->drop_location),
            'package' => $booking->packageBooking?->package_name ?? '',
            default => '',
        };
    }

    protected function bookingTravelDate(Booking $booking): string
    {
        return match ($booking->product_type) {
            'flight' => $booking->flight?->journey['segments'][0]['from']['date'] ?? '',
            'hotel' => $booking->hotelBooking?->check_in?->format('d M Y') ?? '',
            'cab' => $booking->cab?->pickup_datetime?->format('d M Y, h:i A') ?? '',
            'package' => $booking->packageBooking?->departure_date?->format('d M Y') ?? '',
            default => '',
        };
    }
}
