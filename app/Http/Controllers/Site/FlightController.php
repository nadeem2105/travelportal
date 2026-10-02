<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Services\FlightEngine;
use Illuminate\Http\Request;

class FlightController extends Controller
{
    public function __construct(protected FlightEngine $engine)
    {
    }

    public function index(Request $request)
    {
        return view('flights.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('flights'),
            'initialTab' => 'flights',
            'popularRoutes' => $this->popularRoutes(),
        ]);
    }

    public function results(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3|different:from',
            'departure' => 'required|date|after_or_equal:today',
            'return' => 'nullable|date|after:departure',
            'travellers' => 'nullable|string|max:12',
        ]);

        [$adults, $children, $infants, $cabinClass] = $this->parseTravellers($validated['travellers'] ?? '1');

        $params = [
            'trip_type' => filled($validated['return'] ?? null) ? 'round_trip' : 'one_way',
            'from' => strtoupper($validated['from']),
            'to' => strtoupper($validated['to']),
            'departure' => $validated['departure'],
            'return' => $validated['return'] ?? null,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'cabin_class' => $cabinClass,
        ];

        $search = $this->engine->search($params);

        session(['flight_search_params' => $params]);

        [$results, $filters] = $this->applyFilters($search['results'], $request);

        \App\Services\AnalyticsService::track('search_flight', [
            'product_type' => 'flight',
            'from' => $params['from'],
            'to' => $params['to'],
            'results_count' => count($results),
        ]);

        return view('flights.results', [
            'seo' => app(\App\Services\SeoService::class)->forPage('flights', null, [
                'title' => "{$params['from']} to {$params['to']} Flights | Book Online",
            ]),
            'params' => $params,
            'flights' => $results,
            'filters' => $filters,
            'count' => count($results),
            'hasSupplierErrors' => $search['has_supplier_errors'],
            'airports' => Airport::active()->get(),
        ]);
    }

    /**
     * Store selection in session and go to traveller details.
     */
    public function select(Request $request)
    {
        $validated = $request->validate([
            'result_id' => 'required|string|max:80',
            'seat' => 'nullable|string|max:8',
            'baggage' => 'nullable|string|max:20',
        ]);

        $params = session('flight_search_params');

        if (! $params) {
            return redirect()->route('flights.index')->with('error', 'Your search session expired. Please search again.');
        }

        $flight = $this->engine->getByResultId($validated['result_id'], $params);

        if (! $flight) {
            return redirect()->route('flights.results')
                ->withInput()
                ->with('error', 'The selected flight is no longer available. Please choose another flight.');
        }

        $seatMap = filled($validated['seat'] ?? null)
            ? $this->engine->adapter(\App\Models\Supplier::find($flight['supplier_id']))->getSeatMap($validated['result_id'], $params)
            : null;

        session([
            'flight_selection' => [
                'flight' => $flight,
                'seat' => $validated['seat'] ?? null,
                'baggage' => $validated['baggage'] ?? null,
            ],
        ]);

        return redirect()->route('flights.travellers');
    }

    public function travellers(Request $request)
    {
        $selection = session('flight_selection');

        if (! $selection) {
            return redirect()->route('flights.index')->with('error', 'Please select a flight first.');
        }

        $params = session('flight_search_params', []);

        return view('flights.travellers', [
            'seo' => ['title' => 'Traveller Details', 'description' => ''],
            'flight' => $selection['flight'],
            'seat' => $selection['seat'],
            'baggage' => $selection['baggage'],
            'adults' => (int) ($params['adults'] ?? 1),
            'children' => (int) ($params['children'] ?? 0),
            'infants' => (int) ($params['infants'] ?? 0),
            'savedTravellers' => auth('web')->check() ? auth('web')->user()->savedTravellers : collect(),
        ]);
    }

    public function book(Request $request)
    {
        $selection = session('flight_selection');

        if (! $selection) {
            return redirect()->route('flights.index')->with('error', 'Please select a flight first.');
        }

        $params = session('flight_search_params', []);

        $contact = $request->validate([
            'contact_email' => 'required|email',
            'contact_phone' => 'required|string|max:15',
        ]);

        $rules = [];
        $adults = (int) ($params['adults'] ?? 1);
        $children = (int) ($params['children'] ?? 0);
        $infants = (int) ($params['infants'] ?? 0);

        for ($i = 0; $i < $adults; $i++) {
            $rules["adult_$i.title"] = 'required|in:Mr,Mrs,Ms';
            $rules["adult_$i.first_name"] = 'required|string|max:60';
            $rules["adult_$i.last_name"] = 'required|string|max:60';
            $rules["adult_$i.dob"] = 'nullable|date|before:today';
        }
        for ($i = 0; $i < $children; $i++) {
            $rules["child_$i.title"] = 'required|in:Master,Miss';
            $rules["child_$i.first_name"] = 'required|string|max:60';
            $rules["child_$i.last_name"] = 'required|string|max:60';
        }
        for ($i = 0; $i < $infants; $i++) {
            $rules["infant_$i.first_name"] = 'required|string|max:60';
            $rules["infant_$i.last_name"] = 'required|string|max:60';
        }

        $data = $request->validate($rules);

        $flight = $selection['flight'];

        // re-validate price server-side
        $pricing = app(\App\Services\PricingService::class)->calculate(
            (float) $flight['supplier_cost'],
            'flight',
            $flight['supplier_id'] ?? null
        );

        $travellers = [];

        $build = function (string $type, string $prefix, int $n) use (&$travellers, $data) {
            for ($i = 0; $i < $n; $i++) {
                // Input arrives as adult_0 => [title, first_name, ...] (nested array)
                $person = $data[$prefix . '_' . $i] ?? [];
                $travellers[] = [
                    'traveller_type' => $type,
                    'title' => $person['title'] ?? null,
                    'first_name' => $person['first_name'],
                    'last_name' => $person['last_name'] ?? '',
                    'dob' => $person['dob'] ?? null,
                ];
            }
        };

        $build('adult', 'adult', $adults);
        $build('child', 'child', $children);
        $build('infant', 'infant', $infants);

        // items: base fare per traveller
        $items = [];
        $travellerFactor = max(1, $adults + $children + $infants);
        $items[] = [
            'item_type' => 'flight',
            'name' => ($flight['airline']['name'] ?? 'Flight') . ' · ' . $flight['flight_number'],
            'quantity' => $travellerFactor,
            'unit_price' => $pricing['total'],
            'total_price' => round($pricing['total'] * $travellerFactor, 2),
            'details' => ['route' => $flight['segments'][0]['from']['code'] . '-' . $flight['segments'][0]['to']['code']],
        ];

        $booking = app(\App\Services\BookingService::class)->create([
            'product_type' => 'flight',
            'product_id' => null,
            'supplier_id' => $flight['supplier_id'] ?? null,
            'pricing' => ['supplier_cost' => $pricing['supplier_cost'] * $travellerFactor,
                          'subtotal' => round($pricing['subtotal'] * $travellerFactor, 2),
                          'markup_amount' => round($pricing['markup_amount'] * $travellerFactor, 2),
                          'tax_amount' => round($pricing['tax_amount'] * $travellerFactor, 2),
                          'commission_amount' => round($pricing['commission_amount'] * $travellerFactor, 2),
                          'total' => round($pricing['total'] * $travellerFactor, 2),
                          'currency' => $pricing['currency']],
            'items' => $items,
            'travellers' => $travellers,
            'contact' => ['email' => $contact['contact_email'], 'phone' => $contact['contact_phone'], 'first_name' => $travellers[0]['first_name']],
            'flight' => $flight + ['trip_type' => $params['trip_type'] ?? 'one_way', 'search_params' => $params],
            'seat_selection' => $selection['seat'] ? ['seat' => $selection['seat']] : null,
            'baggage_selection' => $selection['baggage'] ? ['option' => $selection['baggage']] : null,
        ]);

        session()->forget('flight_selection');

        \App\Services\AnalyticsService::track('booking_start', [
            'product_type' => 'flight',
            'product_id' => $booking->product_id,
            'booking_reference' => $booking->booking_reference,
            'value' => (float) $booking->total_amount,
            'currency' => $booking->currency ?: 'INR',
        ]);

        return redirect()->route('checkout.show', $booking);
    }

    protected function parseTravellers(string $value): array
    {
        $adults = 1;
        $cabinClass = 'economy';

        if (str_contains($value, '-')) {
            [$adults, $cabinClass] = explode('-', $value, 2);
        } elseif (is_numeric($value)) {
            $adults = $value;
        }

        return [(int) $adults, 0, 0, $cabinClass];
    }

    protected function applyFilters(array $results, Request $request): array
    {
        $filters = [
            'airlines' => collect($results)->groupBy(fn ($f) => $f['airline']['name'])
                ->map(fn ($g) => ['code' => $g->first()['airline']['code'], 'count' => $g->count()]),
            'max_price' => collect($results)->max(fn ($f) => $f['fare']['total_for_all']),
            'min_price' => collect($results)->min(fn ($f) => $f['fare']['total_for_all']),
            'stops' => collect($results)->groupBy('stops')->keys()->sort()->values(),
        ];

        $filtered = collect($results);

        if ($request->filled('airline')) {
            $filtered = $filtered->filter(fn ($f) => in_array($f['airline']['code'], (array) $request->query('airline')));
        }
        if ($request->filled('max_price')) {
            $filtered = $filtered->filter(fn ($f) => $f['fare']['total_for_all'] <= (float) $request->query('max_price'));
        }
        if ($request->filled('stops') && $request->query('stops') !== '') {
            $filtered = $filtered->where('stops', (int) $request->query('stops'));
        }
        if ($request->filled('refundable') && $request->boolean('refundable')) {
            $filtered = $filtered->where('refundable', true);
        }

        $sort = $request->query('sort', 'recommended');
        $filtered = match ($sort) {
            'cheapest' => $filtered->sortBy(fn ($f) => $f['fare']['total_for_all']),
            'fastest' => $filtered->sortBy(fn ($f) => $f['segments'][0]['duration_minutes'] ?? 9999),
            'earliest' => $filtered->sortBy(fn ($f) => $f['segments'][0]['from']['time'] ?? ''),
            default => $filtered, // already recommended-ordered
        };

        return [$filtered->values()->all(), $filters];
    }

    protected function popularRoutes(): array
    {
        return [
            ['from' => 'DEL', 'to' => 'SXR', 'label' => 'New Delhi → Srinagar'],
            ['from' => 'BOM', 'to' => 'SXR', 'label' => 'Mumbai → Srinagar'],
            ['from' => 'BLR', 'to' => 'SXR', 'label' => 'Bengaluru → Srinagar'],
            ['from' => 'DEL', 'to' => 'IXJ', 'label' => 'New Delhi → Jammu'],
        ];
    }
}
