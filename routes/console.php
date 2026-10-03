<?php

use App\Models\Booking;
use App\Models\FlightSearch;
use App\Models\PackageDeparture;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire-pending', function () {
    $expired = Booking::query()
        ->where('status', 'payment_pending')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->with(['packageBooking'])
        ->get();

    $count = 0;
    foreach ($expired as $booking) {
        DB::transaction(function () use ($booking, &$count) {
            $locked = Booking::where('id', $booking->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'payment_pending') {
                return;
            }

            // If it is a package departure booking, release the reserved seats
            if ($locked->product_type === 'package' && $locked->packageBooking) {
                $pb = $locked->packageBooking;
                $seats = ($pb->adults ?? 0) + ($pb->children ?? 0);
                if ($seats > 0 && $locked->product_id) {
                    $departure = PackageDeparture::where('package_id', $locked->product_id)
                        ->where('departure_date', $pb->departure_date)
                        ->lockForUpdate()
                        ->first();
                    if ($departure && $departure->booked >= $seats) {
                        $departure->decrement('booked', $seats);
                    }
                }
            }

            $locked->update([
                'status' => 'failed',
                'notes' => trim(($locked->notes ? $locked->notes . "\n" : '') . 'Auto-expired due to payment timeout at ' . now()->toDateTimeString()),
            ]);
            $count++;
        });
    }

    $this->info("Expired {$count} stale pending booking(s).");
})->purpose('Expire pending bookings whose payment window has lapsed and release held inventory');

Artisan::command('searches:prune', function () {
    $deleted = FlightSearch::where('created_at', '<', now()->subDays(7))->delete();
    $this->info("Pruned {$deleted} old flight search query records.");
})->purpose('Prune old flight search query logs');

/*
 * Reconcile bookings where payment succeeded but the supplier booking failed.
 * Retries supplier confirmation; on success the booking is confirmed and the
 * customer notified. Bookings still failing after 24h are left for an admin to
 * review/refund (surfaced on the admin reconciliation screen).
 */
Artisan::command('bookings:reconcile-failed', function () {
    $service = app(\App\Services\BookingService::class);

    $stuck = Booking::query()
        ->where('status', 'payment_success_booking_failed')
        ->where('updated_at', '>', now()->subDay())
        ->get();

    $recovered = 0;
    foreach ($stuck as $booking) {
        DB::transaction(function () use ($booking, $service, &$recovered) {
            $locked = Booking::where('id', $booking->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'payment_success_booking_failed') {
                return;
            }

            if ($service->confirmWithSupplier($locked)) {
                $locked->update([
                    'status' => 'confirmed',
                    'booked_at' => $locked->booked_at ?? now(),
                    'notes' => trim(($locked->notes ? $locked->notes . "\n" : '') . 'Auto-reconciled: supplier confirmed at ' . now()->toDateTimeString()),
                ]);
                $service->confirmBookingHotels($locked);
                app(\App\Services\NotificationService::class)->sendBookingConfirmation($locked, true);
                $recovered++;
            }
        });
    }

    $this->info("Reconciled {$recovered} payment-success/booking-failed booking(s).");
})->purpose('Retry supplier confirmation for paid-but-unconfirmed bookings');

/*
 * Dispatch any WhatsApp campaigns whose scheduled time has arrived.
 */
Artisan::command('whatsapp:dispatch-campaigns', function () {
    $count = app(\App\Services\WhatsApp\CampaignService::class)->processDue();
    $this->info("Dispatched {$count} due WhatsApp campaign(s).");
})->purpose('Dispatch scheduled WhatsApp campaigns that are due');

/*
 * Reconcile any WhatsApp campaigns stuck in 'sending' status (stale jobs, dead workers).
 */
Artisan::command('whatsapp:reconcile-campaigns {--stale=30 : Minutes before an unmoving campaign is considered stale}', function () {
    $staleMinutes = (int) $this->option('stale');
    $count = app(\App\Services\WhatsApp\CampaignService::class)->reconcileStuckCampaigns($staleMinutes);
    $this->info("Reconciled {$count} stuck WhatsApp campaign(s).");
})->purpose('Reconcile stuck WhatsApp campaigns and auto-close completed or abandoned sends');

Schedule::command('bookings:expire-pending')->everyFiveMinutes();
Schedule::command('bookings:reconcile-failed')->everyTenMinutes();
Schedule::command('searches:prune')->daily();
Schedule::command('whatsapp:dispatch-campaigns')->everyMinute();
Schedule::command('whatsapp:reconcile-campaigns')->everyTenMinutes();

/*
 * Fire time-based ("no activity for N days") automation workflows. Runs the
 * workflow once per matching lead per inactivity window (guarded via a recent
 * automation_run so a lead isn't nudged repeatedly).
 */
Artisan::command('automations:run-scheduled', function () {
    $engine = app(\App\Services\Crm\AutomationEngine::class);
    $fired = 0;

    $workflows = \App\Models\AutomationWorkflow::active()
        ->where('trigger_event', 'no_activity')->with('actions')->get();

    foreach ($workflows as $workflow) {
        $days = (int) (($workflow->trigger_config['days'] ?? 3));
        $cutoff = now()->subDays(max(1, $days));

        $leads = \App\Models\CrmLead::query()
            ->whereNotIn('status', ['converted', 'lost'])
            ->where('updated_at', '<', $cutoff)
            ->limit(500)
            ->get();

        foreach ($leads as $lead) {
            if (! $engine->conditionsMatch($workflow, $lead)) {
                continue;
            }
            // Guard: don't re-fire within the inactivity window.
            $recent = \App\Models\AutomationRun::where('workflow_id', $workflow->id)
                ->where('lead_id', $lead->id)
                ->where('created_at', '>=', $cutoff)
                ->exists();
            if ($recent) {
                continue;
            }

            $engine->runWorkflow($workflow, $lead, 'no_activity');
            $fired++;
        }
    }

    $this->info("Fired {$fired} no-activity automation(s).");
})->purpose('Run time-based (no-activity) CRM automation workflows');

Schedule::command('automations:run-scheduled')->dailyAt('09:00');

/*
 * Trip Operations — driver reminders.
 * For every active assignment with a known pickup time, send the driver a reminder
 * as the pickup enters each enabled offset band (1 day / 12 hours / 2 hours before).
 * Idempotent: TripCommService guards each (assignment, offset) so it fires once.
 */
Artisan::command('trip-ops:driver-reminders', function () {
    $offsets = \App\Models\TripOperationSetting::get('driver_reminder_offsets', ['1_day', '2_hours']);
    if (empty($offsets)) {
        $this->info('No driver reminder offsets enabled.');
        return;
    }

    $map = ['1_day' => 24, '12_hours' => 12, '2_hours' => 2];
    // Enabled bands sorted ascending by hours, so the closest-in-time band wins.
    $bands = collect($map)
        ->only($offsets)
        ->sort()
        ->map(fn ($h, $k) => ['key' => $k, 'hours' => $h])
        ->values();

    $comms = app(\App\Services\TripOperations\TripCommService::class);
    $now = now();
    $sent = 0;

    \App\Models\DriverAssignment::query()
        ->whereIn('status', ['assigned', 'reassigned'])
        ->with(['trip', 'driver'])
        ->chunkById(200, function ($assignments) use ($bands, $comms, $now, &$sent) {
            foreach ($assignments as $assignment) {
                $trip = $assignment->trip;
                if (! $trip || $trip->isCancelled()) {
                    continue;
                }
                $pickup = $assignment->pickup_datetime ?? $trip->arrival_date;
                if (! $pickup) {
                    continue;
                }
                $hoursUntil = $now->floatDiffInHours($pickup, false);
                if ($hoursUntil <= 0) {
                    continue; // pickup already passed
                }

                $lower = 0;
                foreach ($bands as $band) {
                    if ($hoursUntil > $lower && $hoursUntil <= $band['hours']) {
                        $comms->sendDriverReminder($assignment, $band['key']);
                        $sent++;
                        break;
                    }
                    $lower = $band['hours'];
                }
            }
        });

    $this->info("Processed driver reminders ({$sent} due band matches).");
})->purpose('Send driver pickup reminders for the enabled offset bands');

/*
 * Trip Operations — nightly "Tomorrow's Plan" to customers on active trips that
 * have events scheduled tomorrow. Gated by the send_tomorrow_plan setting and the
 * configured tomorrow_plan_time hour. Idempotent per (trip, date).
 */
Artisan::command('trip-ops:tomorrow-plan {--force : Ignore the enabled flag and send-time gate}', function () {
    $force = (bool) $this->option('force');

    if (! $force && ! \App\Models\TripOperationSetting::get('send_tomorrow_plan', false)) {
        $this->info('Tomorrow\'s plan is disabled.');
        return;
    }

    // Only run in the configured hour (command is scheduled hourly).
    $time = \App\Models\TripOperationSetting::get('tomorrow_plan_time', '19:00');
    $hour = (int) \Illuminate\Support\Carbon::createFromFormat('H:i', $time)->format('H');
    if (! $force && (int) now()->format('H') !== $hour) {
        return;
    }

    $tomorrow = \Illuminate\Support\Carbon::tomorrow();
    $dateKey = $tomorrow->format('Y-m-d');
    $comms = app(\App\Services\TripOperations\TripCommService::class);
    $sent = 0;

    \App\Models\Trip::query()
        ->whereIn('status', [\App\Models\Trip::STATUS_ARRIVING_TODAY, \App\Models\Trip::STATUS_IN_PROGRESS])
        ->whereHas('days', fn ($d) => $d->whereDate('date', $tomorrow))
        ->with('booking')
        ->chunkById(200, function ($trips) use ($comms, $dateKey, &$sent) {
            foreach ($trips as $trip) {
                $comms->sendTomorrowPlan($trip, $dateKey);
                $sent++;
            }
        });

    $this->info("Dispatched tomorrow's plan for {$sent} active trip(s).");
})->purpose('Send customers their next-day plan for active trips');

Schedule::command('trip-ops:driver-reminders')->everyFifteenMinutes();
Schedule::command('trip-ops:tomorrow-plan')->hourly();

/*
 * Diagnose why a transactional WhatsApp document (e.g. the booking itinerary)
 * isn't delivered. Prints the EFFECTIVE config (env overlaid with the admin
 * WhatsApp Settings stored in integration_settings), the real Meta header type
 * of each template used on booking confirmation, and the most recent failed
 * outbound WhatsApp messages with their error — the send failures that are
 * otherwise only recorded in the whatsapp_messages table.
 */
Artisan::command('whatsapp:diagnose', function () {
    $wa = (array) config('services.whatsapp');

    $this->info('WhatsApp enabled: ' . (($wa['enabled'] ?? false) ? 'yes' : 'no'));
    $this->newLine();

    $templates = (array) ($wa['templates'] ?? []);
    $attach = (array) ($wa['attach_documents'] ?? []);

    // On booking confirmation two messages go out: booking_confirmed (carries the
    // itinerary or invoice) and booking_invoice (carries the invoice).
    $attachItinerary = (bool) ($attach['booking_itinerary'] ?? false);
    $attachInvoiceOnConfirmed = (bool) ($attach['booking_confirmed'] ?? false);
    $confirmedDoc = $attachItinerary ? 'itinerary' : ($attachInvoiceOnConfirmed ? 'invoice' : 'none');

    $this->line('Effective template names + attach flags:');
    $rows = [];
    foreach (['booking_confirmed', 'booking_invoice', 'booking_cancelled', 'trip_customer_itinerary'] as $event) {
        $rows[] = [$event, $templates[$event] ?? '(blank → skipped)'];
    }
    $this->table(['event', 'template name'], $rows);

    $this->line("attach_documents.booking_itinerary = " . var_export($attachItinerary, true));
    $this->line("attach_documents.booking_confirmed = " . var_export($attachInvoiceOnConfirmed, true));
    $this->line("attach_documents.booking_invoice   = " . var_export((bool) ($attach['booking_invoice'] ?? false), true));
    $this->line(">> On confirmation the 'booking_confirmed' message will carry: {$confirmedDoc}");
    $this->newLine();

    // Check the real header type of each template as synced from Meta.
    $this->line('Meta template header check (synced whatsapp_templates):');
    $tplRows = [];
    foreach (array_filter([$templates['booking_confirmed'] ?? null, $templates['booking_invoice'] ?? null]) as $name) {
        $tpl = \App\Models\WhatsAppTemplate::where('name', $name)->first();
        if (! $tpl) {
            $tplRows[] = [$name, 'NOT SYNCED', '—', '—', '—', 'run template sync in admin, or name is wrong'];
            continue;
        }
        $header = $tpl->getHeaderType() ?: 'none';
        $cat = strtoupper($tpl->category ?? '?');
        $verdict = $header !== 'document'
            ? 'CANNOT carry a PDF'
            : ($cat === 'MARKETING' ? 'MARKETING → freq-capped/throttled' : 'OK');
        $tplRows[] = [$name, $tpl->status ?? '?', $cat, $header, (string) ($tpl->body_variable_count ?? 0), $verdict];
    }
    $this->table(['template', 'status', 'category', 'header', 'body vars', 'verdict'], $tplRows);
    $this->newLine();

    // Recent booking_confirmed_itinerary sends (any status) — did it even attempt,
    // and what did the status webhook say afterwards?
    $itinName = $templates['booking_confirmed'] ?? null;
    if ($itinName) {
        $this->line("Last 5 '{$itinName}' outbound sends (any status):");
        $recent = \App\Models\WhatsAppMessage::where('direction', 'outbound')
            ->where('template_name', $itinName)
            ->latest('id')->limit(5)
            ->get(['created_at', 'status', 'error']);
        if ($recent->isEmpty()) {
            $this->warn('  (none) — this template has NEVER been sent (job not dispatched / worker stale).');
        } else {
            $this->table(['when', 'status', 'error'], $recent->map(fn ($m) => [
                (string) $m->created_at, $m->status ?? '?', \Illuminate\Support\Str::limit($m->error ?? '', 70),
            ])->all());
        }
        $this->newLine();
    }

    // Recent failed outbound sends — the silent failures.
    $this->line('Last 10 failed outbound WhatsApp messages:');
    $failed = \App\Models\WhatsAppMessage::where('direction', 'outbound')
        ->where('status', 'failed')
        ->latest('id')
        ->limit(10)
        ->get(['created_at', 'template_name', 'error']);

    if ($failed->isEmpty()) {
        $this->info('  (none) — no failed outbound sends recorded.');
    } else {
        $this->table(
            ['when', 'template', 'error'],
            $failed->map(fn ($m) => [
                (string) $m->created_at,
                $m->template_name ?: '—',
                \Illuminate\Support\Str::limit($m->error ?? '', 80),
            ])->all()
        );
    }
})->purpose('Diagnose non-delivered transactional WhatsApp documents (templates, headers, failures)');

/*
 * Send the booking-confirmation WhatsApp (with the itinerary/voucher PDF) for one
 * booking RIGHT NOW, synchronously, and print the exact Meta result. This bypasses
 * the queue/worker entirely, so it isolates a stale worker (queue not restarted)
 * from a genuine Meta delivery block (e.g. a MARKETING-category template being
 * frequency-capped: "not delivered to maintain healthy ecosystem engagement").
 *
 *   php artisan whatsapp:send-itinerary LMT29369C66
 *   php artisan whatsapp:send-itinerary LMT29369C66 --phone=+9170...
 */
Artisan::command('whatsapp:send-itinerary {ref} {--phone=}', function () {
    $ref = $this->argument('ref');
    $booking = \App\Models\Booking::where('booking_reference', $ref)->first();
    if (! $booking) {
        $this->error("Booking {$ref} not found.");
        return 1;
    }

    $phone = $this->option('phone') ?: ($booking->contact['phone'] ?? $booking->user?->phone);
    if (! $phone) {
        $this->error('No phone on the booking; pass --phone=');
        return 1;
    }

    $whatsapp = app(\App\Services\WhatsApp\WhatsAppService::class);
    $pdf = app(\App\Services\PdfDocumentService::class);

    if (! $whatsapp->isEnabled()) {
        $this->error('WhatsApp is disabled in settings.');
        return 1;
    }

    // Render the product-appropriate itinerary/voucher PDF.
    try {
        $bytes = match ($booking->product_type) {
            'package' => $pdf->itinerary($booking),
            'flight' => $pdf->flightTicket($booking),
            'hotel' => $pdf->hotelVoucher($booking),
            'cab' => $pdf->cabVoucher($booking),
            default => null,
        };
    } catch (\Throwable $e) {
        $this->error('Itinerary PDF render failed: ' . $e->getMessage());
        return 1;
    }
    if (! $bytes) {
        $this->error("No itinerary document for product_type '{$booking->product_type}'.");
        return 1;
    }

    $name = $booking->contact['first_name'] ?? ($booking->user?->name ?? 'Traveller');
    $params = [
        $name,
        $booking->booking_reference,
        (string) ($booking->packageBooking?->package_name ?? ucfirst((string) $booking->product_type)),
        (string) ($booking->created_at?->format('d M Y') ?? date('d M Y')),
        '₹' . number_format((float) $booking->total_amount, 2),
    ];

    $this->info("Sending 'booking_confirmed' template + itinerary PDF to {$phone} ...");
    $result = $whatsapp->notifyEvent(
        'booking_confirmed', $phone, $params, $name,
        ['bytes' => $bytes, 'filename' => "Itinerary-{$booking->booking_reference}.pdf"]
    );

    if ($result['success'] ?? false) {
        $this->info('Meta ACCEPTED the send (wa message id: ' . ($result['data']['messages'][0]['id'] ?? 'n/a') . ').');
        $this->line('If the customer still does not receive it, it was dropped post-accept — check the status webhook / template category.');
    } else {
        $this->error('Send FAILED: ' . ($result['error'] ?? 'unknown error'));
    }
})->purpose('Synchronously send the itinerary WhatsApp for one booking and print the Meta result');

/*
 * One-time migration of integration credentials FROM .env INTO the database
 * (integration_settings, encrypted at rest) so everything is managed from the
 * admin panel and .env can be stripped of secrets.
 *
 * Reads the CURRENT effective config (which, before any DB row exists, is just
 * the .env/config values) and persists the non-empty ones into the encrypted
 * `credentials` column for each provider. Idempotent: re-running only fills gaps
 * and refreshes values, it never wipes a field the admin already set in the panel
 * (blank .env values are skipped).
 *
 * After running this and confirming the panel shows the values, remove the
 * secrets from .env — the boot-time overlay (IntegrationSettings::applyToConfig)
 * will serve them from the DB.
 *
 *   php artisan integration:import-env
 */
Artisan::command('integration:import-env', function () {
    $onlyNonEmpty = fn (array $a) => array_filter($a, fn ($v) => $v !== null && $v !== '' && $v !== []);
    $imported = [];

    // --- WhatsApp -------------------------------------------------------
    $wa = (array) config('services.whatsapp', []);
    $waCreds = $onlyNonEmpty([
        'api_version' => $wa['api_version'] ?? null,
        'phone_number_id' => $wa['phone_number_id'] ?? null,
        'waba_id' => $wa['waba_id'] ?? null,
        'access_token' => $wa['access_token'] ?? null,
        'verify_token' => $wa['verify_token'] ?? null,
        'app_secret' => $wa['app_secret'] ?? null,
        'app_id' => $wa['app_id'] ?? null,
        'default_template_lang' => $wa['default_template_lang'] ?? null,
    ]);
    // Template names + attach flags are non-secret but belong with the row so the
    // panel is the single source of truth.
    if (! empty($wa['templates'])) {
        $waCreds['templates'] = $onlyNonEmpty((array) $wa['templates']);
    }
    if (isset($wa['attach_documents'])) {
        $waCreds['attach_documents'] = array_map(fn ($v) => (bool) $v, (array) $wa['attach_documents']);
    }
    if (filled($waCreds['access_token'] ?? null) || filled($waCreds['phone_number_id'] ?? null)) {
        $row = \App\Models\IntegrationSetting::firstOrNew(['provider' => 'whatsapp']);
        $row->credentials = array_merge((array) ($row->credentials ?? []), $waCreds);
        $row->enabled = (bool) ($wa['enabled'] ?? $row->enabled);
        $row->save();
        $imported[] = 'whatsapp';
    }

    // --- AI: shared defaults + per-provider keys ------------------------
    $ai = (array) config('services.ai', []);
    $aiDefaults = $onlyNonEmpty([
        'default_provider' => $ai['default_provider'] ?? null,
        'default_model' => $ai['default_model'] ?? null,
        'image_model' => $ai['image_model'] ?? null,
    ]);
    $aiDefaults['enable_image'] = (bool) ($ai['enable_image'] ?? false);
    if (! empty($aiDefaults)) {
        $row = \App\Models\IntegrationSetting::firstOrNew(['provider' => 'ai']);
        $row->credentials = array_merge((array) ($row->credentials ?? []), $aiDefaults);
        $row->enabled = true;
        $row->save();
        $imported[] = 'ai';
    }

    foreach (['openai', 'anthropic', 'gemini', 'groq', 'openrouter'] as $name) {
        $key = $ai['providers'][$name]['api_key'] ?? null;
        if (! filled($key)) {
            continue; // no key in .env → nothing to migrate for this provider
        }
        $row = \App\Models\IntegrationSetting::firstOrNew(['provider' => $name]);
        $row->credentials = array_merge((array) ($row->credentials ?? []), $onlyNonEmpty([
            'api_key' => $key,
            'model' => $ai['providers'][$name]['model'] ?? null,
        ]));
        $row->enabled = true;
        $row->save();
        $imported[] = $name;
    }

    // --- SMS (only if a provider key is present in config/.env) ---------
    $sms = (array) config('sms', []);
    $smsProvider = $sms['default'] ?? null;
    $smsProviderCreds = $onlyNonEmpty((array) ($sms['providers'][$smsProvider] ?? []));
    if (! empty($smsProviderCreds)) {
        $row = \App\Models\IntegrationSetting::firstOrNew(['provider' => 'sms']);
        $creds = (array) ($row->credentials ?? []);
        $creds['default'] = $smsProvider;
        $creds['providers'] = array_merge((array) ($creds['providers'] ?? []), [$smsProvider => $smsProviderCreds]);
        $row->credentials = $creds;
        $row->enabled = (bool) ($sms['enabled'] ?? $row->enabled);
        $row->save();
        $imported[] = 'sms(' . $smsProvider . ')';
    }

    if (empty($imported)) {
        $this->warn('Nothing to import — no non-empty credentials found in .env/config.');
        return 0;
    }

    $this->info('Imported into integration_settings (encrypted): ' . implode(', ', $imported));
    $this->line('Verify in the admin panel (WhatsApp / AI / SMS settings), then remove these secrets from .env.');
    return 0;
})->purpose('Import .env credentials into the encrypted integration_settings table (admin-managed)');
