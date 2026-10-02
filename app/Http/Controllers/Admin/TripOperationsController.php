<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripOperationSetting;
use App\Services\PdfDocumentService;
use App\Services\TripOperations\TripBuilder;
use App\Services\TripOperations\TripCommService;
use App\Services\TripOperations\TripOperationsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read + operational actions over the Trip Operations overlay: Today dashboard,
 * Upcoming / Active / Completed queues, per-trip timeline, PDFs, comms and settings.
 * All booking/customer data is referenced from the authoritative Booking — never duplicated.
 */
class TripOperationsController extends Controller
{
    public function __construct(
        protected TripOperationsService $ops,
        protected TripBuilder $builder,
        protected TripCommService $comms,
        protected PdfDocumentService $pdf,
    ) {}

    /* ============================ Today dashboard ============================ */

    public function today()
    {
        $this->ops->refreshAllActiveStatuses();

        return view('admin.trip-ops.today', [
            'kpis' => $this->ops->todayKpis(),
            'arrivals' => $this->ops->arrivalsToday(),
            'timeline' => $this->ops->todayTimeline(),
        ]);
    }

    /* ============================ queues ============================ */

    public function upcoming(Request $request)
    {
        return view('admin.trip-ops.upcoming', [
            'trips' => $this->queue($request, fn ($q) => $q->upcoming()->orderBy('arrival_date')),
            'filters' => $request->only(['q', 'from', 'to', 'assigned', 'product_type']),
            'title' => 'Upcoming Arrivals',
            'listRoute' => 'trip-ops.upcoming',
        ]);
    }

    public function active(Request $request)
    {
        return view('admin.trip-ops.upcoming', [
            'trips' => $this->queue($request, fn ($q) => $q
                ->whereIn('status', [Trip::STATUS_ARRIVING_TODAY, Trip::STATUS_IN_PROGRESS])
                ->orderBy('arrival_date')),
            'filters' => $request->only(['q', 'from', 'to', 'assigned', 'product_type']),
            'title' => 'Active Trips',
            'listRoute' => 'trip-ops.active',
        ]);
    }

    public function completed(Request $request)
    {
        return view('admin.trip-ops.upcoming', [
            'trips' => $this->queue($request, fn ($q) => $q
                ->where('status', Trip::STATUS_COMPLETED)
                ->orderByDesc('departure_date')),
            'filters' => $request->only(['q', 'from', 'to', 'assigned', 'product_type']),
            'title' => 'Completed Trips',
            'listRoute' => 'trip-ops.completed',
        ]);
    }

    /** Shared filtered/paginated query for the list screens. */
    protected function queue(Request $request, callable $scope)
    {
        $query = Trip::query()
            ->with(['booking', 'activeAssignments.driver'])
            ->where('status', '!=', Trip::STATUS_CANCELLED)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->query('q') . '%';
                $q->where(fn ($w) => $w->where('trip_reference', 'like', $term)
                    ->orWhere('lead_customer_name', 'like', $term)
                    ->orWhere('lead_customer_phone', 'like', $term)
                    ->orWhere('destination_label', 'like', $term));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('arrival_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('arrival_date', '<=', $request->query('to')))
            ->when($request->filled('product_type'), fn ($q) => $q->where('product_type', $request->query('product_type')))
            ->when($request->query('assigned') === 'yes', fn ($q) => $q->whereHas('activeAssignments'))
            ->when($request->query('assigned') === 'no', fn ($q) => $q->whereDoesntHave('activeAssignments'));

        $scope($query);

        return $query->paginate((int) $request->query('per_page', 20))->withQueryString();
    }

    /* ============================ trip timeline ============================ */

    public function show(Trip $trip)
    {
        $trip->load(['booking.user', 'days.events', 'assignments.driver', 'assignments.histories', 'comms']);
        $drivers = Driver::active()->orderBy('name')->get();

        return view('admin.trip-ops.show', compact('trip', 'drivers'));
    }

    /** Rebuild the itinerary from the authoritative booking (bumps version). */
    public function regenerate(Trip $trip)
    {
        $this->builder->generateItinerary($trip, true);

        return back()->with('success', 'Itinerary regenerated (v' . $trip->fresh()->itinerary_version . ').');
    }

    /**
     * Manually override a trip's operational status. Statuses are normally derived
     * from booking + dates (deriveStatus / the status-refresh scheduler), but ops
     * staff need to nudge a trip — e.g. mark it In Progress on early arrival, or
     * Completed ahead of the departure date. Sets the started_at / completed_at
     * stamps to match, mirroring TripOperationsService::refreshStatus.
     */
    public function updateStatus(Request $request, Trip $trip)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', Trip::STATUSES),
        ]);

        $new = $data['status'];
        if ($new === $trip->status) {
            return back()->with('success', 'Status unchanged.');
        }

        $trip->forceFill(['status' => $new]);
        if ($new === Trip::STATUS_IN_PROGRESS && ! $trip->started_at) {
            $trip->started_at = now();
        }
        if ($new === Trip::STATUS_COMPLETED && ! $trip->completed_at) {
            $trip->completed_at = now();
        }
        $trip->save();

        \App\Services\ActivityLogger::log('update', 'trip_operations',
            "Trip {$trip->trip_reference} status changed to " . label_case($new));

        return back()->with('success', 'Trip status updated to ' . label_case($new) . '.');
    }

    public function updateNotes(Request $request, Trip $trip)
    {
        $trip->update(['notes' => $request->validate(['notes' => 'nullable|string|max:5000'])['notes'] ?? null]);

        return back()->with('success', 'Internal notes saved.');
    }

    /* ============================ PDFs ============================ */

    public function driverSheet(Trip $trip)
    {
        $bytes = $this->pdf->driverSheet($trip);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="DriverSheet-' . $trip->trip_reference . '.pdf"',
        ]);
    }

    public function customerItineraryPdf(Trip $trip)
    {
        $bytes = $this->pdf->customerItinerary($trip);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Itinerary-' . $trip->trip_reference . '.pdf"',
        ]);
    }

    /* ============================ comms ============================ */

    public function sendItinerary(Trip $trip)
    {
        // Manual admin click → force a real re-send (bypasses the idempotency guard).
        $outcomes = $this->comms->sendCustomerItinerary($trip, true);

        // Build a truthful flash message from the actual per-channel outcomes
        // instead of a blanket "dispatched" banner that hides skips/failures.
        $sent = [];
        $problems = [];
        foreach ($outcomes as $channel => $outcome) {
            $label = ucfirst($channel);
            if (($outcome['status'] ?? null) === 'sent') {
                $sent[] = $label;
            } else {
                $reason = $outcome['error'] ?? $outcome['status'] ?? 'not sent';
                $problems[] = "{$label}: {$reason}";
            }
        }

        if (empty($outcomes)) {
            return back()->with('error', 'No customer contact (phone/email) on file, or no channels enabled — nothing was sent.');
        }

        if ($sent && ! $problems) {
            return back()->with('success', 'Customer itinerary sent via ' . implode(' & ', $sent) . '.');
        }

        if ($sent && $problems) {
            return back()->with('success', 'Customer itinerary sent via ' . implode(' & ', $sent) . '. Issues: ' . implode('; ', $problems) . '.');
        }

        return back()->with('error', 'Customer itinerary could not be sent. ' . implode('; ', $problems) . '.');
    }

    /**
     * Manually send the driver a pickup reminder now (force), for every active
     * assignment on the trip. Complements the automatic offset-band reminders
     * fired by the trip-ops:driver-reminders schedule.
     */
    public function sendDriverReminder(Trip $trip)
    {
        $assignments = $trip->activeAssignments()->with('driver')->get();

        if ($assignments->isEmpty()) {
            return back()->with('error', 'No active driver assignment on this trip — assign a driver first.');
        }

        $sent = [];
        $problems = [];
        foreach ($assignments as $assignment) {
            // Distinct "manual" event key + force so it always re-sends on click.
            $outcomes = $this->comms->sendDriverReminder($assignment, 'manual:' . now()->format('YmdHis'), true);
            $driverName = $assignment->driver?->name ?? 'Driver';
            if (empty($outcomes)) {
                $problems[] = "{$driverName}: no phone on file";
                continue;
            }
            foreach ($outcomes as $channel => $outcome) {
                if (($outcome['status'] ?? null) === 'sent') {
                    $sent[] = "{$driverName} (" . ucfirst($channel) . ')';
                } else {
                    $problems[] = "{$driverName} " . ucfirst($channel) . ': ' . ($outcome['error'] ?? $outcome['status'] ?? 'not sent');
                }
            }
        }

        if ($sent && ! $problems) {
            return back()->with('success', 'Driver reminder sent: ' . implode(', ', $sent) . '.');
        }
        if ($sent && $problems) {
            return back()->with('success', 'Driver reminder sent: ' . implode(', ', $sent) . '. Issues: ' . implode('; ', $problems) . '.');
        }

        return back()->with('error', 'Driver reminder could not be sent. ' . implode('; ', $problems) . '.');
    }

    /**
     * Manually send the customer their "tomorrow's plan" now (force). Complements
     * the nightly trip-ops:tomorrow-plan schedule.
     */
    public function sendTomorrowPlan(Trip $trip)
    {
        $dateKey = Carbon::tomorrow()->format('Y-m-d');
        // Manual click → force so it re-sends even if the nightly job already ran.
        $outcomes = $this->comms->sendTomorrowPlan($trip, 'manual:' . $dateKey, true);

        if (empty($outcomes)) {
            return back()->with('error', 'No customer phone on file — tomorrow\'s plan needs a WhatsApp number.');
        }

        $wa = $outcomes['whatsapp'] ?? [];
        if (($wa['status'] ?? null) === 'sent') {
            return back()->with('success', "Tomorrow's plan sent to the customer on WhatsApp.");
        }

        return back()->with('error', "Tomorrow's plan could not be sent. WhatsApp: " . ($wa['error'] ?? $wa['status'] ?? 'not sent') . '.');
    }

    /**
     * Manually send the Driver Sheet PDF to the assigned driver(s) over WhatsApp
     * using the predefined trip_driver_sheet template (force re-send).
     */
    public function sendDriverSheet(Trip $trip)
    {
        $assignments = $trip->activeAssignments()->with('driver')->get();

        if ($assignments->isEmpty()) {
            return back()->with('error', 'No active driver assignment on this trip — assign a driver first.');
        }

        $sent = [];
        $problems = [];
        foreach ($assignments as $assignment) {
            $driverName = $assignment->driver?->name ?? 'Driver';
            $outcomes = $this->comms->sendDriverSheet($assignment, true);
            if (empty($outcomes)) {
                $problems[] = "{$driverName}: no WhatsApp number on file";
                continue;
            }
            $wa = $outcomes['whatsapp'] ?? [];
            if (($wa['status'] ?? null) === 'sent') {
                $sent[] = $driverName;
            } else {
                $problems[] = "{$driverName}: " . ($wa['error'] ?? $wa['status'] ?? 'not sent');
            }
        }

        if ($sent && ! $problems) {
            return back()->with('success', 'Driver sheet sent to: ' . implode(', ', $sent) . '.');
        }
        if ($sent && $problems) {
            return back()->with('success', 'Driver sheet sent to: ' . implode(', ', $sent) . '. Issues: ' . implode('; ', $problems) . '.');
        }

        return back()->with('error', 'Driver sheet could not be sent. ' . implode('; ', $problems) . '.');
    }

    /* ============================ export ============================ */

    public function export(Request $request)
    {
        $trips = Trip::query()->with('activeAssignments.driver')
            ->where('status', '!=', Trip::STATUS_CANCELLED)
            ->orderBy('arrival_date')->get();

        $headers = ['Reference', 'Customer', 'Phone', 'Destination', 'Arrival', 'Departure', 'Days', 'Status', 'Driver'];
        $callback = function () use ($trips, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($trips as $t) {
                fputcsv($out, [
                    $t->trip_reference, $t->customerName(), $t->customerPhone(), $t->destination_label,
                    optional($t->arrival_date)->format('Y-m-d'), optional($t->departure_date)->format('Y-m-d'),
                    $t->total_days, $t->status, optional($t->activeAssignments->first()?->driver)->name,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="trips-' . Carbon::now()->format('Ymd_His') . '.csv"',
        ]);
    }

    /* ============================ settings ============================ */

    public function settings()
    {
        return view('admin.trip-ops.settings', ['settings' => TripOperationSetting::allSettings()]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'auto_generate_itinerary' => 'nullable|boolean',
            'send_customer_itinerary_on_generate' => 'nullable|boolean',
            'send_tomorrow_plan' => 'nullable|boolean',
            'tomorrow_plan_time' => 'nullable|date_format:H:i',
            'driver_reminder_offsets' => 'nullable|array',
            'driver_reminder_offsets.*' => 'in:1_day,12_hours,2_hours',
            'notify_driver_channels' => 'nullable|array',
            'notify_driver_channels.*' => 'in:whatsapp,sms',
            'notify_customer_channels' => 'nullable|array',
            'notify_customer_channels.*' => 'in:whatsapp,email,sms',
        ]);

        TripOperationSetting::put('auto_generate_itinerary', (bool) ($data['auto_generate_itinerary'] ?? false));
        TripOperationSetting::put('send_customer_itinerary_on_generate', (bool) ($data['send_customer_itinerary_on_generate'] ?? false));
        TripOperationSetting::put('send_tomorrow_plan', (bool) ($data['send_tomorrow_plan'] ?? false));
        TripOperationSetting::put('tomorrow_plan_time', $data['tomorrow_plan_time'] ?? '19:00');
        TripOperationSetting::put('driver_reminder_offsets', array_values($data['driver_reminder_offsets'] ?? []));
        TripOperationSetting::put('notify_driver_channels', array_values($data['notify_driver_channels'] ?? ['whatsapp']));
        TripOperationSetting::put('notify_customer_channels', array_values($data['notify_customer_channels'] ?? ['whatsapp', 'email']));

        return back()->with('success', 'Trip Operations settings saved.');
    }
}
