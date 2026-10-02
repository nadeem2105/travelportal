<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::with(['destination', 'seasonalPrices'])->where('status', 'active');

        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('short_description', 'like', "%{$q}%")
                ->orWhereHas('destination', fn ($d) => $d->where('name', 'like', "%{$q}%")));
        }

        if ($destination = $request->query('destination')) {
            $query->whereHas('destination', fn ($d) => $d->where('slug', $destination));
        }

        if ($duration = $request->query('duration')) {
            $query->where(fn ($w) => $w
                ->where('duration_days', $duration)
                ->orWhereBetween('duration_days', [(int) $duration, (int) $duration + 2]));
        }

        match ($request->query('sort')) {
            'price_low' => $query->orderBy('base_price'),
            'price_high' => $query->orderByDesc('base_price'),
            'duration' => $query->orderBy('duration_days'),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        return view('packages.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('packages'),
            'packages' => $query->paginate(9)->withQueryString(),
            'destinations' => Destination::where('status', 'active')->orderBy('sort_order')->get(),
        ]);
    }

    public function show(Request $request, Package $package)
    {
        if ($package->status !== 'active') {
            abort(404);
        }

        $package->load(['itineraries', 'hotels', 'destination', 'departures' => fn ($q) => $q->where('status', 'open')->whereDate('departure_date', '>=', now())]);

        \App\Services\AnalyticsService::track('view_package', [
            'product_type' => 'package',
            'product_id' => $package->id,
            'package_name' => $package->name,
        ]);

        $travelDate = $request->query('date', now()->addDays(14)->format('Y-m-d'));

        return view('packages.show', [
            'seo' => app(\App\Services\SeoService::class)->forPage('packages', $package, [
                'title' => $package->name . ' | ' . $package->duration_days . ' Days Tour',
                'description' => $package->short_description,
            ]),
            'package' => $package,
            'price' => $package->effectivePrice($travelDate),
            'travelDate' => $travelDate,
            'reviews' => $package->reviews()->approved()->with('user')->latest()->limit(10)->get(),
            'similar' => Package::where('status', 'active')
                ->where('id', '!=', $package->id)
                ->when($package->destination_id, fn ($q) => $q->where('destination_id', $package->destination_id))
                ->limit(3)->get(),
        ]);
    }

    /**
     * Multi-step booking form: travel details → traveller details → billing information.
     */
    public function bookingForm(Request $request, Package $package)
    {
        if ($package->status !== 'active') {
            abort(404);
        }

        $package->load([
            'destination', 'itineraries',
            'departures' => fn ($q) => $q->where('status', 'open')->whereDate('departure_date', '>=', now()),
            'hotelSegments.options' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order'),
            'hotelSegments.options.hotel',
            'hotelOptions' => fn ($q) => $q->where('status', 'active')->whereNull('segment_id')->orderBy('sort_order'),
            'hotelOptions.hotel',
            'flightOptions' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order'),
        ]);

        $validated = $request->validate([
            'departure_date' => 'nullable|date|after_or_equal:today',
            'adults' => 'nullable|integer|min:1|max:' . $package->max_travellers,
            'children' => 'nullable|integer|min:0|max:10',
            'rooms' => 'nullable|integer|min:1|max:6',
        ]);

        $adults = (int) ($validated['adults'] ?? 2);
        $children = (int) ($validated['children'] ?? 0);
        $rooms = (int) ($validated['rooms'] ?? max(1, (int) ceil($adults / 2)));
        $departureDate = $validated['departure_date'] ?? now()->addDays(14)->format('Y-m-d');

        // Preselect the included (default) hotel of each segment / whole trip.
        $defaultOptionIds = $this->defaultOptionIds($package);

        // Preselect the recommended flight when the package requires flights.
        $defaultFlightId = $this->defaultFlightId($package);

        // Server-side price preview (the authoritative price is recomputed on submit)
        $quote = $this->computePricing($package, $departureDate, $adults, $children, $rooms, $defaultOptionIds, $defaultFlightId);

        return view('packages.book', [
            'seo' => ['title' => 'Book: ' . $package->name, 'description' => ''],
            'package' => $package,
            'adults' => $adults,
            'children' => $children,
            'rooms' => $rooms,
            'departureDate' => $departureDate,
            'adultPrice' => $quote['adultPrice'],
            'childPrice' => $quote['childPrice'],
            'pricing' => $quote['pricing'],
            'defaultOptionIds' => $defaultOptionIds,
            'defaultFlightId' => $defaultFlightId,
            'hotelUpgradeTotal' => $quote['hotel']['upgrade_total'],
            'flightTotal' => $quote['flight']['total'],
            'savedTravellers' => auth('web')->check() ? auth('web')->user()->savedTravellers : collect(),
        ]);
    }

    /**
     * AJAX endpoint — recomputes the authoritative price for the current
     * selection (date, occupancy, chosen hotels). Never trusts frontend totals.
     */
    public function quote(Request $request, Package $package)
    {
        if ($package->status !== 'active') {
            abort(404);
        }

        $validated = $request->validate([
            'departure_date' => 'required|date|after_or_equal:today',
            'adults' => 'required|integer|min:1|max:' . $package->max_travellers,
            'children' => 'nullable|integer|min:0|max:10',
            'rooms' => 'nullable|integer|min:1|max:6',
            'hotel_option_ids' => 'nullable|array|max:12',
            'hotel_option_ids.*' => 'integer',
            'flight_option_id' => 'nullable|integer',
        ]);

        $adults = (int) $validated['adults'];
        $children = (int) ($validated['children'] ?? 0);
        $rooms = (int) ($validated['rooms'] ?? 1);

        $quote = $this->computePricing(
            $package,
            $validated['departure_date'],
            $adults,
            $children,
            $rooms,
            $validated['hotel_option_ids'] ?? [],
            $validated['flight_option_id'] ?? null
        );

        if (! $quote['hotel']['valid']) {
            return response()->json(['ok' => false, 'error' => $quote['hotel']['error']], 422);
        }
        if (! $quote['flight']['valid']) {
            return response()->json(['ok' => false, 'error' => $quote['flight']['error']], 422);
        }

        return response()->json([
            'ok' => true,
            'currency' => $quote['pricing']['currency'],
            'adult_price' => $quote['adultPrice'],
            'child_price' => $quote['childPrice'],
            'adults' => $adults,
            'children' => $children,
            'rooms' => $rooms,
            'base_package' => $quote['base'],
            'hotel_upgrade' => $quote['hotel']['upgrade_total'],
            'flight_total' => $quote['flight']['total'],
            'subtotal' => $quote['pricing']['subtotal'],
            'tax_amount' => $quote['pricing']['tax_amount'],
            'service_fee' => $quote['pricing']['service_fee'] + $quote['pricing']['convenience_fee'],
            'total' => $quote['pricing']['total'],
            'hotel_lines' => array_map(fn ($l) => [
                'option_id' => $l['package_hotel_option_id'],
                'name' => $l['hotel_name_snapshot'],
                'segment' => $l['segment_label'],
                'total' => $l['total'],
                'included' => $l['is_included'],
            ], $quote['hotel']['lines']),
        ]);
    }

    /** Shared server-side pricing used by the form preview, AJAX quote and (indirectly) submit. */
    protected function computePricing(Package $package, string $date, int $adults, int $children, int $rooms, array $optionIds, ?int $flightOptionId = null): array
    {
        $adultPrice = $package->effectivePrice($date);
        $childPrice = (float) ($package->child_price ?? $adultPrice * 0.6);
        $base = round($adultPrice * $adults + $childPrice * $children, 2);

        $hotel = ['valid' => true, 'error' => null, 'upgrade_total' => 0.0, 'lines' => []];
        if (! empty($optionIds) && $package->offersHotelSelection()) {
            $hotel = app(\App\Services\PackageHotelPricingService::class)
                ->resolveSelection($package, $optionIds, $adults, $children, 0, $rooms, $date);
        }

        $flight = ['valid' => true, 'error' => null, 'total' => 0.0, 'line' => null];
        if ($flightOptionId && $package->offersFlightSelection()) {
            $flight = app(\App\Services\PackageFlightPricingService::class)
                ->resolveSelection($package, $flightOptionId, $adults, $children, $date);
        }

        $addOns = ($hotel['valid'] ? $hotel['upgrade_total'] : 0) + ($flight['valid'] ? $flight['total'] : 0);
        $supplierCost = round($base + $addOns, 2);
        $pricing = app(\App\Services\PricingService::class)->calculate($supplierCost, 'package');

        return compact('adultPrice', 'childPrice', 'base', 'hotel', 'flight', 'pricing');
    }

    /** IDs of the default/included option for each segment (and whole-trip). */
    protected function defaultOptionIds(Package $package): array
    {
        $ids = [];

        foreach ($package->hotelSegments as $segment) {
            $default = $segment->options->firstWhere('is_default', true) ?? $segment->options->first();
            if ($default) {
                $ids[] = $default->id;
            }
        }

        $wholeTrip = $package->hotelOptions->firstWhere('is_default', true)
            ?? $package->hotelOptions->first();
        if ($wholeTrip) {
            $ids[] = $wholeTrip->id;
        }

        return $ids;
    }

    /** Recommended flight to preselect — only when flights are mandatory. */
    protected function defaultFlightId(Package $package): ?int
    {
        if (! $package->requiresFlightSelection() || $package->flightOptions->isEmpty()) {
            return null;
        }

        $default = $package->flightOptions->firstWhere('is_default', true) ?? $package->flightOptions->first();

        return $default?->id;
    }

    public function book(Request $request, Package $package)
    {
        if ($package->status !== 'active') {
            abort(404);
        }

        $validated = $request->validate([
            'departure_date' => 'required|date|after_or_equal:today',
            'adults' => 'required|integer|min:1|max:15',
            'children' => 'nullable|integer|min:0|max:10',
            'rooms' => 'nullable|integer|min:1|max:6',
            'special_requests' => 'nullable|string|max:1000',

            // Hotel selection (optional depending on package hotel_mode)
            'hotel_option_ids' => 'nullable|array|max:12',
            'hotel_option_ids.*' => 'integer|exists:package_hotel_options,id',

            // Flight selection (optional depending on package flight_mode)
            'flight_option_id' => 'nullable|integer|exists:package_flight_options,id',

            // Billing / contact information
            'billing_name' => 'required|string|max:80',
            'billing_email' => 'required|email',
            'billing_phone' => 'required|string|max:15',
            'billing_address' => 'required|string|min:5|max:255',
            'billing_city' => 'required|string|max:60',
            'billing_state' => 'nullable|string|max:60',
            'billing_pincode' => 'nullable|string|max:12',
            'billing_country' => 'nullable|string|max:60',
            'gstin' => 'nullable|string|max:20',
        ]);

        $adults = (int) $validated['adults'];
        $children = (int) ($validated['children'] ?? 0);

        if ($adults + $children > $package->max_travellers) {
            return back()->with('error', "This package allows a maximum of {$package->max_travellers} travellers per booking.");
        }

        // Every traveller (adults + children) must be filled in — name, DOB, gender
        $travellerRules = [];
        $count = $adults + $children;
        for ($i = 0; $i < $count; $i++) {
            $travellerRules["travellers.{$i}.type"] = 'required|in:adult,child';
            $travellerRules["travellers.{$i}.first_name"] = 'required|string|max:60';
            $travellerRules["travellers.{$i}.last_name"] = 'nullable|string|max:60';
            $travellerRules["travellers.{$i}.dob"] = 'required|date|before:today';
            $travellerRules["travellers.{$i}.gender"] = 'required|in:male,female,other';
            $travellerRules["travellers.{$i}.id_type"] = 'nullable|in:passport,aadhaar,driving_license,voter_id';
            $travellerRules["travellers.{$i}.id_number"] = 'nullable|string|max:60';
        }

        $travellerData = $request->validate($travellerRules)['travellers'] ?? [];

        if (count($travellerData) !== $count) {
            return back()->with('error', 'Please fill in details for all travellers.');
        }

        $departure = $package->departures()->whereDate('departure_date', $validated['departure_date'])->first();

        if ($departure && $departure->seatsLeft() < ($adults + $children)) {
            return back()->with('error', 'Not enough seats available on the selected departure date. Please choose another date.');
        }

        // Server-side price recalculation — never trust the frontend
        $adultPrice = $package->effectivePrice($validated['departure_date']);
        $childPrice = (float) ($package->child_price ?? $adultPrice * 0.6);
        $basePackageCost = round($adultPrice * $adults + $childPrice * $children, 2);

        // Hotel selection: resolve + price server-side.
        $rooms = (int) ($validated['rooms'] ?? 1);
        $hotelLines = [];
        $hotelUpgradeTotal = 0.0;
        $selectedOptionIds = $validated['hotel_option_ids'] ?? [];

        if ($package->requiresHotelSelection() && empty($selectedOptionIds)) {
            return back()->withInput()->with('error', 'Please select a hotel to continue.');
        }

        if (! empty($selectedOptionIds) && $package->offersHotelSelection()) {
            $resolved = app(\App\Services\PackageHotelPricingService::class)->resolveSelection(
                $package,
                $selectedOptionIds,
                $adults,
                $children,
                0,
                $rooms,
                $validated['departure_date']
            );

            if (! $resolved['valid']) {
                return back()->withInput()->with('error', $resolved['error']);
            }

            $hotelLines = $resolved['lines'];
            $hotelUpgradeTotal = $resolved['upgrade_total'];
        }

        // Flight selection: resolve + price server-side.
        $flightLine = null;
        $flightTotal = 0.0;
        $selectedFlightId = $validated['flight_option_id'] ?? null;

        if ($package->requiresFlightSelection() && empty($selectedFlightId)) {
            return back()->withInput()->with('error', 'Please select a flight to continue.');
        }

        if ($selectedFlightId && $package->offersFlightSelection()) {
            $resolvedFlight = app(\App\Services\PackageFlightPricingService::class)
                ->resolveSelection($package, (int) $selectedFlightId, $adults, $children, $validated['departure_date']);

            if (! $resolvedFlight['valid']) {
                return back()->withInput()->with('error', $resolvedFlight['error']);
            }

            $flightLine = $resolvedFlight['line'];
            $flightTotal = $resolvedFlight['total'];
        }

        $supplierCost = round($basePackageCost + $hotelUpgradeTotal + $flightTotal, 2);

        $pricing = app(\App\Services\PricingService::class)->calculate($supplierCost, 'package');

        $billingName = $validated['billing_name'];

        $travellers = array_map(fn ($t) => [
            'traveller_type' => $t['type'],
            'first_name' => $t['first_name'],
            'last_name' => $t['last_name'] ?? '',
            'dob' => $t['dob'],
            'gender' => $t['gender'],
            'id_type' => $t['id_type'] ?? null,
            'id_number' => $t['id_number'] ?? null,
        ], $travellerData);

        // Line items: base package + one line per chosen hotel upgrade.
        $items = [
            [
                'item_type' => 'package_adult',
                'name' => $package->name . ' · Adult',
                'quantity' => $adults,
                'unit_price' => $adultPrice,
                'total_price' => round($adultPrice * $adults, 2),
            ],
            [
                'item_type' => 'package_child',
                'name' => $package->name . ' · Child (with bed)',
                'quantity' => $children,
                'unit_price' => $childPrice,
                'total_price' => round($childPrice * $children, 2),
            ],
        ];

        foreach ($hotelLines as $line) {
            if ($line['total'] <= 0) {
                continue; // included hotels add no line-item cost
            }
            $label = $line['hotel_name_snapshot']
                . ($line['segment_label'] ? ' (' . $line['segment_label'] . ')' : '')
                . ' · Hotel upgrade';
            $items[] = [
                'item_type' => 'package_hotel',
                'item_id' => $line['package_hotel_option_id'],
                'name' => $label,
                'quantity' => 1,
                'unit_price' => $line['total'],
                'total_price' => $line['total'],
                'details' => [
                    'room_type' => $line['room_name_snapshot'],
                    'meal_plan' => $line['meal_plan'],
                    'rooms' => $line['rooms'],
                ],
            ];
        }

        if ($flightLine && $flightTotal > 0) {
            $items[] = [
                'item_type' => 'package_flight',
                'item_id' => $flightLine['package_flight_option_id'],
                'name' => $flightLine['label_snapshot'] . ' · Flights',
                'quantity' => 1,
                'unit_price' => $flightTotal,
                'total_price' => $flightTotal,
                'details' => [
                    'origin' => $flightLine['origin_airport_code'] ?? $flightLine['origin_city'],
                    'cabin_class' => $flightLine['cabin_class'],
                    'travellers' => $flightLine['travellers'],
                ],
            ];
        }

        try {
            $booking = app(\App\Services\BookingService::class)->create([
                'product_type' => 'package',
                'product_id' => $package->id,
                'pricing' => $pricing,
                'items' => $items,
                'travellers' => $travellers,
                'contact' => [
                    'first_name' => strtok($billingName, ' ') ?: $billingName,
                    'last_name' => str_contains($billingName, ' ') ? trim(substr($billingName, strpos($billingName, ' '))) : '',
                    'full_name' => $billingName,
                    'email' => $validated['billing_email'],
                    'phone' => $validated['billing_phone'],
                    'address' => $validated['billing_address'],
                    'city' => $validated['billing_city'],
                    'state' => $validated['billing_state'] ?? null,
                    'pincode' => $validated['billing_pincode'] ?? null,
                    'country' => $validated['billing_country'] ?? 'India',
                    'gstin' => $validated['gstin'] ?? null,
                ],
                'notes' => $validated['special_requests'] ?? null,
                'package' => [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'departure_date' => $validated['departure_date'],
                    'departure_id' => $departure?->id,
                    'adults' => $adults,
                    'children' => $children,
                    'rooms' => $rooms,
                    'hotels' => $hotelLines,
                    'flights' => $flightLine ? [$flightLine] : [],
                    'price_breakdown' => [
                        'adult_price' => $adultPrice,
                        'child_price' => $childPrice,
                        'adults' => $adults,
                        'children' => $children,
                        'base_package' => $basePackageCost,
                        'hotel_upgrade' => $hotelUpgradeTotal,
                        'flight_total' => $flightTotal,
                    ],
                ],
            ]);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Services\AnalyticsService::track('book_package', [
            'product_type' => 'package',
            'product_id' => $package->id,
            'booking_reference' => $booking->booking_reference,
            'amount' => (float) $booking->total_amount,
        ]);

        return redirect()->route('checkout.show', $booking);
    }
}
