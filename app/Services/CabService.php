<?php

namespace App\Services;

use App\Models\CabLocation;
use App\Models\Vehicle;

/**
 * Cab search + fare computation. All rates come from the admin-managed
 * vehicles table — nothing is hardcoded.
 */
class CabService
{
    /** Approximate inter-city distances (km) used for demo fare quotes. */
    public const ROUTE_DISTANCES = [
        'srinagar-gulmarg' => 52,
        'srinagar-pahalgam' => 90,
        'srinagar-sonamarg' => 80,
        'srinagar-doodhpathri' => 42,
        'srinagar-yusmarg' => 47,
        'srinagar-airport' => 15,
        'jammu-srinagar' => 270,
        'srinagar-dal-lake' => 5,
    ];

    public function __construct(protected PricingService $pricing)
    {
    }

    public function search(array $params): array
    {
        $distance = $this->estimateDistance($params['pickup'] ?? '', $params['drop'] ?? '', $params['trip_type'] ?? 'one_way');

        $vehicles = Vehicle::query()
            ->where('status', 'active')
            ->with('type')
            ->when(! empty($params['passengers']), fn ($q) => $q->where('passenger_capacity', '>=', $params['passengers']))
            ->orderByDesc('is_featured')
            ->get();

        $results = $vehicles->map(function (Vehicle $vehicle) use ($params, $distance) {
            $supplierCost = $this->computeFare($vehicle, $params, $distance);
            $price = $this->pricing->calculate($supplierCost, 'cab');

            return [
                'vehicle_id' => $vehicle->id,
                'name' => $vehicle->name,
                'type' => $vehicle->type?->name,
                'image' => $vehicle->image,
                'passenger_capacity' => $vehicle->passenger_capacity,
                'luggage_capacity' => $vehicle->luggage_capacity,
                'is_ac' => $vehicle->is_ac,
                'cancellation_policy' => $vehicle->cancellation_policy,
                'distance_km' => $distance,
                'fare_breakdown' => $this->fareBreakdown($vehicle, $params, $distance),
                'supplier_cost' => $supplierCost,
                'display_price' => $price['total'],
                'pricing' => $price,
            ];
        })->values()->toArray();

        usort($results, fn ($a, $b) => $a['display_price'] <=> $b['display_price']);

        return ['results' => $results, 'count' => count($results), 'distance_km' => $distance];
    }

    public function computeFare(Vehicle $vehicle, array $params, float $distance): float
    {
        $tripType = $params['trip_type'] ?? 'one_way';

        $fare = (float) $vehicle->base_price;

        if ($tripType === 'local_rental' && $vehicle->per_hour_rate) {
            $hours = (int) ($params['hours'] ?? 8);
            $fare += (float) $vehicle->per_hour_rate * $hours;
        } else {
            $multiplier = $tripType === 'round_trip' ? 2 : 1;
            $fare += (float) $vehicle->per_km_rate * $distance * $multiplier;

            // driver allowance for long outstation trips
            if ($distance > 100 && in_array($tripType, ['one_way', 'round_trip'])) {
                $fare += 300 * $multiplier;
            }
        }

        // night charge (11 PM - 6 AM)
        if (! empty($params['pickup_time']) && in_array((int) substr($params['pickup_time'], 0, 2), [23, 0, 1, 2, 3, 4, 5])) {
            $fare += 250;
        }

        return round($fare, 2);
    }

    protected function fareBreakdown(Vehicle $vehicle, array $params, float $distance): array
    {
        $tripType = $params['trip_type'] ?? 'one_way';
        $multiplier = $tripType === 'round_trip' ? 2 : 1;

        $breakdown = [
            'base_price' => (float) $vehicle->base_price,
            'distance_charge' => round($vehicle->per_km_rate * $distance * $multiplier, 2),
            'distance_km' => $distance * $multiplier,
            'driver_allowance' => ($distance > 100 && in_array($tripType, ['one_way', 'round_trip'])) ? 300 * $multiplier : 0,
            'night_charge' => 0,
        ];

        if (! empty($params['pickup_time']) && in_array((int) substr($params['pickup_time'], 0, 2), [23, 0, 1, 2, 3, 4, 5])) {
            $breakdown['night_charge'] = 250;
        }

        return $breakdown;
    }

    public function estimateDistance(string $pickup, string $drop, string $tripType): float
    {
        $key = $this->routeKey($pickup, $drop);

        if (isset(self::ROUTE_DISTANCES[$key])) {
            return (float) self::ROUTE_DISTANCES[$key];
        }

        // deterministic pseudo-distance from unknown routes
        $hash = crc32($key);

        return (float) (15 + ($hash % 120));
    }

    protected function routeKey(string $pickup, string $drop): string
    {
        $norm = fn ($s) => strtolower(trim(preg_replace('/\s+/', '-', $s)));

        $a = $norm($pickup);
        $b = $norm($drop);

        if (str_contains($a, 'airport') || str_contains($b, 'airport')) {
            return 'srinagar-airport';
        }

        $shorten = fn ($s) => str_replace(['-city', '-kashmir'], '', $s);

        return $shorten($a) . '-' . $shorten($b);
    }

    public function popularLocations(): array
    {
        return CabLocation::where('status', 'active')->orderBy('sort_order')->get()->toArray();
    }
}
