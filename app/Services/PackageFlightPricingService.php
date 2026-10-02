<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageFlightOption;
use Illuminate\Support\Carbon;

/**
 * Resolves and prices the flight a customer adds to a package (MakeMyTrip-style
 * with/without flights). Computes the add-on fare server-side and returns a
 * snapshot-ready line the caller folds into the supplier cost. The frontend
 * only supplies which option was chosen.
 */
class PackageFlightPricingService
{
    /**
     * @return array{valid: bool, error: ?string, total: float, line: ?array, option: ?PackageFlightOption}
     */
    public function resolveSelection(
        Package $package,
        ?int $optionId,
        int $adults,
        int $children = 0,
        ?string $travelDate = null
    ): array {
        if (! $optionId) {
            return ['valid' => true, 'error' => null, 'total' => 0.0, 'line' => null, 'option' => null];
        }

        $option = PackageFlightOption::query()
            ->where('package_id', $package->id)
            ->whereKey($optionId)
            ->first();

        if (! $option || $option->status !== 'active') {
            return $this->fail('The selected flight is no longer available.');
        }

        if (! $this->availableOn($option, $travelDate)) {
            return $this->fail('“' . $option->displayName() . '” is not available for the selected travel date.');
        }

        $line = $this->lineFor($option, $package, $adults, $children);

        return ['valid' => true, 'error' => null, 'total' => $line['price'], 'line' => $line, 'option' => $option];
    }

    /** Build the price + snapshot payload for a chosen flight option. */
    public function lineFor(PackageFlightOption $option, Package $package, int $adults, int $children): array
    {
        $seats = max(1, $adults + $children);
        $price = $option->price_basis === 'per_person'
            ? (float) $option->price * $seats
            : (float) $option->price;
        $price = round($price, 2);

        return [
            'package_flight_option_id' => $option->id,
            'package_id' => $package->id,
            'supplier_id' => $option->supplier_id,
            'supplier_fare_id' => $option->supplier_fare_code,
            'label_snapshot' => $option->displayName(),
            'airline_snapshot' => $option->airline,
            'origin_city' => $option->origin_city,
            'origin_airport_code' => $option->origin_airport_code,
            'destination_airport_code' => $option->destination_airport_code,
            'trip_type' => $option->trip_type,
            'cabin_class' => $option->cabin_class,
            'baggage_snapshot' => $option->baggage,
            'travellers' => $seats,
            'price_basis' => $option->price_basis,
            'price_per_person' => (float) $option->price,
            'price' => $price,
            'refundable' => (bool) $option->refundable,
            'cancellation_policy_snapshot' => $option->cancellation_policy,
            'status' => 'pending',
        ];
    }

    protected function availableOn(PackageFlightOption $option, ?string $travelDate): bool
    {
        if (! $travelDate) {
            return true;
        }
        $date = Carbon::parse($travelDate);

        if ($option->available_from && $date->lt(Carbon::parse($option->available_from))) {
            return false;
        }
        if ($option->available_to && $date->gt(Carbon::parse($option->available_to))) {
            return false;
        }

        return true;
    }

    protected function fail(string $message): array
    {
        return ['valid' => false, 'error' => $message, 'total' => 0.0, 'line' => null, 'option' => null];
    }
}
