<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\CabService;
use Illuminate\Http\Request;

class CabController extends Controller
{
    public function __construct(protected CabService $cabService)
    {
    }

    public function index(Request $request)
    {
        return view('cabs.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('cabs'),
            'initialTab' => 'cabs',
            'locations' => $this->cabService->popularLocations(),
        ]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'pickup' => 'required|string|max:100',
            'drop' => 'required|string|max:100',
            'pickup_date' => 'required|date|after_or_equal:today',
            'pickup_time' => 'required|date_format:H:i',
            'trip_type' => 'required|in:one_way,round_trip,local_rental,airport_transfer',
            'passengers' => 'nullable|integer|min:1|max:20',
        ]);

        $params = [
            'pickup' => $validated['pickup'],
            'drop' => $validated['drop'],
            'pickup_date' => $validated['pickup_date'],
            'pickup_time' => $validated['pickup_time'],
            'pickup_datetime' => $validated['pickup_date'] . ' ' . $validated['pickup_time'],
            'trip_type' => $validated['trip_type'],
            'passengers' => $validated['passengers'] ?? null,
        ];

        $search = $this->cabService->search($params);

        session(['cab_search_params' => $params]);

        \App\Services\AnalyticsService::track('search_cab', [
            'product_type' => 'cab',
            'origin' => $params['pickup'],
            'destination' => $params['drop'],
            'pickup_date' => $params['pickup_date'],
            'trip_type' => $params['trip_type'],
            'passengers' => $params['passengers'],
            'result_count' => is_countable($search['results'] ?? null) ? count($search['results']) : 0,
        ]);

        return view('cabs.results', [
            'seo' => ['title' => 'Cabs from ' . $validated['pickup'] . ' to ' . $validated['drop'], 'description' => ''],
            'params' => $params,
            'vehicles' => collect($search['results']),
            'distance' => $search['distance_km'],
        ]);
    }

    public function details(Request $request)
    {
        $params = session('cab_search_params');

        if (! $params) {
            return redirect()->route('cabs.index')->with('error', 'Please search for a cab first.');
        }

        $validated = $request->validate(['vehicle_id' => 'required|integer|exists:vehicles,id']);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        session(['cab_selection' => $vehicle->id]);

        return view('cabs.details', [
            'seo' => ['title' => 'Traveller Details', 'description' => ''],
            'vehicle' => $vehicle,
            'params' => $params,
            'distance' => $this->cabService->estimateDistance($params['pickup'], $params['drop'], $params['trip_type']),
        ]);
    }

    public function book(Request $request)
    {
        $params = session('cab_search_params');

        if (! $params) {
            return redirect()->route('cabs.index')->with('error', 'Please search for a cab first.');
        }

        // Step 2: create the booking
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email',
            'phone' => 'required|string|max:15',
        ]);

        $vehicleId = session('cab_selection');
        abort_if(! $vehicleId, 404);

        $vehicle = Vehicle::findOrFail($vehicleId);

        $supplierCost = $this->cabService->computeFare($vehicle, $params, $this->cabService->estimateDistance($params['pickup'], $params['drop'], $params['trip_type']));
        $pricing = app(\App\Services\PricingService::class)->calculate($supplierCost, 'cab');

        $booking = app(\App\Services\BookingService::class)->create([
            'product_type' => 'cab',
            'product_id' => $vehicle->id,
            'pricing' => $pricing,
            'items' => [[
                'item_type' => 'cab',
                'item_id' => $vehicle->id,
                'name' => $vehicle->name . ' · ' . $params['pickup'] . ' to ' . $params['drop'],
                'quantity' => 1,
                'unit_price' => $pricing['total'],
                'total_price' => $pricing['total'],
                'details' => ['trip_type' => $params['trip_type']],
            ]],
            'travellers' => [[
                'traveller_type' => 'adult',
                'first_name' => strtok($validated['name'], ' ') ?: $validated['name'],
                'last_name' => strstr($validated['name'], ' ') ? substr(strstr($validated['name'], ' '), 1) : '',
                'is_primary' => true,
            ]],
            'contact' => ['email' => $validated['email'], 'phone' => $validated['phone'], 'first_name' => strtok($validated['name'], ' ')],
            'cab' => [
                'vehicle_id' => $vehicle->id,
                'vehicle_name' => $vehicle->name,
                'pickup_location' => $params['pickup'],
                'drop_location' => $params['drop'],
                'pickup_datetime' => $params['pickup_datetime'],
                'trip_type' => $params['trip_type'],
                'distance_km' => $this->cabService->estimateDistance($params['pickup'], $params['drop'], $params['trip_type']),
                'fare_breakdown' => null,
            ],
        ]);

        session()->forget('cab_selection');

        \App\Services\AnalyticsService::track('booking_start', [
            'product_type' => 'cab',
            'product_id' => $vehicle->id,
            'booking_reference' => $booking->booking_reference,
            'value' => (float) $booking->total_amount,
            'currency' => $booking->currency ?: 'INR',
            'origin' => $params['pickup'],
            'destination' => $params['drop'],
        ]);

        return redirect()->route('checkout.show', $booking);
    }
}
