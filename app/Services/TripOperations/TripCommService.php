<?php

namespace App\Services\TripOperations;

use App\Models\DriverAssignment;
use App\Models\Trip;
use App\Models\TripOperationComm;
use App\Models\TripOperationSetting;
use App\Services\MailConfigService;
use App\Services\PdfDocumentService;
use App\Services\SmsService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Idempotent operational communications for trips. Every send is guarded by a
 * TripOperationComm row keyed on (event_key, channel, recipient) so a driver or
 * customer is never messaged twice for the same trigger, even across retries,
 * scheduler runs, or reassignments.
 *
 * Proactive WhatsApp messages use approved TEMPLATES (valid outside the 24h window).
 * When a template isn't configured the WhatsApp send is a silent no-op and email/SMS
 * still carry the message.
 */
class TripCommService
{
    public function __construct(
        protected WhatsAppService $whatsapp,
        protected SmsService $sms,
        protected MailConfigService $mailConfig,
        protected PdfDocumentService $pdf,
    ) {}

    /* ===================== driver assignment ===================== */

    public function sendDriverAssignment(DriverAssignment $assignment): void
    {
        $trip = $assignment->trip;
        $driver = $assignment->driver;
        if (! $trip || ! $driver || ! $driver->phone) {
            return;
        }

        $eventKey = "driver_assigned:{$assignment->id}:{$driver->id}";
        $channels = TripOperationSetting::get('notify_driver_channels', ['whatsapp']);

        $params = [
            $driver->name,
            $trip->customerName(),
            optional($trip->arrival_date)->format('d M Y') ?? 'TBD',
            $trip->destination_label ?: 'your trip',
            $assignment->pickup_location ?: ($trip->destination_label ?: 'as scheduled'),
        ];

        $text = $this->driverAssignmentText($assignment);

        if (in_array('whatsapp', $channels, true)) {
            $this->guard($eventKey, 'whatsapp', $driver->waNumber(), $trip, $assignment, function () use ($driver, $params, $trip) {
                $doc = null;
                if (config('services.whatsapp.attach_documents.trip_customer_itinerary')) {
                    // Driver gets the operational driver sheet, not the customer itinerary.
                    $doc = ['bytes' => $this->pdf->driverSheet($trip), 'filename' => 'DriverSheet-' . $trip->trip_reference . '.pdf'];
                }

                return $this->whatsapp->notifyEvent('trip_driver_assigned', $driver->waNumber(), $params, $driver->name, $doc);
            });
        }

        if (in_array('sms', $channels, true)) {
            $this->guard($eventKey, 'sms', $driver->phone, $trip, $assignment,
                fn () => ['success' => $this->sms->send($driver->phone, $text)]);
        }
    }

    /* ===================== driver sheet ===================== */

    /**
     * Send the driver their operational Driver Sheet PDF over WhatsApp using the
     * dedicated `trip_driver_sheet` template. Separate from the assignment message
     * so an admin can (re)send the sheet on demand — e.g. after editing the
     * itinerary — without re-triggering the whole assignment notification.
     */
    public function sendDriverSheet(DriverAssignment $assignment, bool $force = false): array
    {
        $trip = $assignment->trip;
        $driver = $assignment->driver;
        if (! $trip || ! $driver || ! $driver->waNumber()) {
            return [];
        }

        // Manual re-sends get a timestamped key so the guard never blocks them.
        $eventKey = 'driver_sheet:' . $assignment->id . ($force ? ':manual:' . now()->format('YmdHis') : '');
        $params = [
            $driver->name,
            $trip->customerName(),
            optional($trip->arrival_date)->format('d M Y') ?? 'TBD',
            $trip->destination_label ?: 'your trip',
            $assignment->pickup_location ?: ($trip->destination_label ?: 'as scheduled'),
        ];

        return [
            'whatsapp' => $this->guard($eventKey, 'whatsapp', $driver->waNumber(), $trip, $assignment, function () use ($driver, $params, $trip) {
                $doc = null;
                if (config('services.whatsapp.attach_documents.trip_driver_sheet', true)) {
                    $doc = ['bytes' => $this->pdf->driverSheet($trip), 'filename' => 'DriverSheet-' . $trip->trip_reference . '.pdf'];
                }

                return $this->whatsapp->notifyEvent('trip_driver_sheet', $driver->waNumber(), $params, $driver->name, $doc);
            }, $force),
        ];
    }

    public function sendCustomerItinerary(Trip $trip, bool $force = false): array
    {
        $phone = $trip->customerPhone();
        $email = $trip->customerEmail();
        $eventKey = "customer_itinerary:{$trip->id}:v{$trip->itinerary_version}";
        $channels = TripOperationSetting::get('notify_customer_channels', ['whatsapp', 'email']);

        $params = [
            $trip->customerName(),
            $trip->destination_label ?: 'your upcoming trip',
            optional($trip->arrival_date)->format('d M Y') ?? 'soon',
            $trip->total_days . ' day(s)',
        ];

        $outcomes = [];

        if (in_array('whatsapp', $channels, true) && $phone) {
            $outcomes['whatsapp'] = $this->guard($eventKey, 'whatsapp', preg_replace('/\D+/', '', $phone), $trip, null, function () use ($phone, $params, $trip) {
                $doc = null;
                if (config('services.whatsapp.attach_documents.trip_customer_itinerary')) {
                    $doc = ['bytes' => $this->pdf->customerItinerary($trip), 'filename' => 'Itinerary-' . $trip->trip_reference . '.pdf'];
                }

                return $this->whatsapp->notifyEvent('trip_customer_itinerary', preg_replace('/\D+/', '', $phone), $params, $trip->customerName(), $doc);
            }, $force);
        }

        if (in_array('email', $channels, true) && $email) {
            $outcomes['email'] = $this->guard($eventKey, 'email', $email, $trip, null, fn () => $this->emailItinerary($trip, $email), $force);
        }

        return $outcomes;
    }

    /* ===================== driver reminder ===================== */

    public function sendDriverReminder(DriverAssignment $assignment, string $offset, bool $force = false): array
    {
        $trip = $assignment->trip;
        $driver = $assignment->driver;
        if (! $trip || ! $driver || ! $driver->phone) {
            return [];
        }

        $eventKey = "driver_reminder:{$assignment->id}:{$offset}";
        $when = optional($assignment->pickup_datetime ?? $trip->arrival_date)->format('d M Y, h:i A') ?? 'soon';
        $params = [$driver->name, $trip->customerName(), $when, $assignment->pickup_location ?: ($trip->destination_label ?: 'as scheduled')];
        $text = "Reminder: pickup for {$trip->customerName()} on {$when}. " .
            ($assignment->pickup_location ? "From {$assignment->pickup_location}. " : '') .
            "Trip {$trip->trip_reference}.";

        $channels = TripOperationSetting::get('notify_driver_channels', ['whatsapp']);
        $outcomes = [];

        if (in_array('whatsapp', $channels, true)) {
            $outcomes['whatsapp'] = $this->guard($eventKey, 'whatsapp', $driver->waNumber(), $trip, $assignment,
                fn () => $this->whatsapp->notifyEvent('trip_driver_reminder', $driver->waNumber(), $params, $driver->name), $force);
        }
        if (in_array('sms', $channels, true)) {
            $outcomes['sms'] = $this->guard($eventKey, 'sms', $driver->phone, $trip, $assignment,
                fn () => ['success' => $this->sms->send($driver->phone, $text)], $force);
        }

        return $outcomes;
    }

    /* ===================== tomorrow's plan ===================== */

    public function sendTomorrowPlan(Trip $trip, string $dateKey, bool $force = false): array
    {
        $phone = $trip->customerPhone();
        if (! $phone) {
            return [];
        }
        $eventKey = "tomorrow_plan:{$trip->id}:{$dateKey}";
        $params = [$trip->customerName(), $trip->destination_label ?: 'your trip', $dateKey];

        return [
            'whatsapp' => $this->guard($eventKey, 'whatsapp', preg_replace('/\D+/', '', $phone), $trip, null,
                fn () => $this->whatsapp->notifyEvent('trip_tomorrow_plan', preg_replace('/\D+/', '', $phone), $params, $trip->customerName()), $force),
        ];
    }

    /* ===================== internals ===================== */

    /**
     * Run $send once per (event_key, channel, recipient). Records the outcome and
     * short-circuits on any prior successful/skipped attempt — UNLESS $force is
     * true (manual admin "Send Itinerary" click), which re-sends regardless.
     *
     * @return array{status:string,error:?string}
     */
    protected function guard(string $eventKey, string $channel, ?string $recipient, ?Trip $trip, ?DriverAssignment $assignment, callable $send, bool $force = false): array
    {
        if (! $recipient) {
            return ['status' => 'no_recipient', 'error' => 'no ' . $channel . ' recipient on file'];
        }

        // Already handled? (unique index also protects against races). A forced
        // send (manual button) bypasses this so the admin can always re-send.
        $existing = TripOperationComm::where('event_key', $eventKey)
            ->where('channel', $channel)->where('recipient', $recipient)->first();
        if (! $force && $existing && $existing->status !== 'failed') {
            return ['status' => 'duplicate', 'error' => null];
        }

        $status = 'sent';
        $error = null;
        try {
            $result = $send();
            $ok = is_array($result) ? ($result['success'] ?? false) : (bool) $result;
            $status = $ok ? 'sent' : 'skipped';
            if (is_array($result) && ! $ok) {
                $error = $result['error'] ?? ($result['message'] ?? 'not sent (channel disabled or template missing)');
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            Log::warning('TripCommService send failed', ['event' => $eventKey, 'channel' => $channel, 'error' => $e->getMessage()]);
        }

        TripOperationComm::updateOrCreate(
            ['event_key' => $eventKey, 'channel' => $channel, 'recipient' => $recipient],
            [
                'trip_id' => $trip?->id,
                'driver_assignment_id' => $assignment?->id,
                'recipient_type' => $assignment ? 'driver' : 'customer',
                'status' => $status,
                'error' => $error,
                'sent_at' => now(),
            ]
        );

        return ['status' => $status, 'error' => $error];
    }

    protected function emailItinerary(Trip $trip, string $email): array
    {
        if (! $this->mailConfig->isEnabled()) {
            return ['success' => false, 'message' => 'mail disabled'];
        }

        // Queue the itinerary email — the PDF is rendered inside the Mailable on
        // the worker, so package confirmations don't block on itinerary rendering
        // and stay consistent with the (also queued) BookingConfirmationMail.
        // Web requests still need the DB mail config applied before dispatch so the
        // queued job serializes with the right mailer; console/worker boot applies it.
        $this->mailConfig->apply();

        Mail::to($email)->send(new \App\Mail\TripItineraryMail($trip));

        return ['success' => true];
    }

    protected function driverAssignmentText(DriverAssignment $assignment): string
    {
        $trip = $assignment->trip;
        $when = optional($trip->arrival_date)->format('d M Y') ?? 'TBD';

        return "New assignment: {$trip->customerName()} arriving {$when} at " .
            ($trip->destination_label ?: 'destination') . '. ' .
            ($assignment->pickup_location ? "Pickup: {$assignment->pickup_location}. " : '') .
            "Trip {$trip->trip_reference}.";
    }
}
