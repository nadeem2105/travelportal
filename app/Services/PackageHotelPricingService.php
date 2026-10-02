<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageHotelOption;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves and prices the hotel(s) a customer selects for a package.
 *
 * This does NOT replace PricingService — it computes the hotel *upgrade delta*
 * (base package price already covers the included option) plus occupancy
 * charges, which the caller then folds into the supplier cost handed to
 * PricingService. Every amount here is derived server-side from the
 * PackageHotelOption master; the frontend only supplies which options were
 * chosen and the occupancy.
 */
class PackageHotelPricingService
{
    /**
     * Resolve selected options for a package and compute their price impact.
     *
     * @param  int[]  $optionIds  chosen PackageHotelOption ids (max one per segment)
     * @return array{
     *     valid: bool,
     *     error: string|null,
     *     upgrade_total: float,
     *     lines: array<int, array>,
     *     options: Collection<int, PackageHotelOption>
     * }
     */
    public function resolveSelection(
        Package $package,
        array $optionIds,
        int $adults,
        int $children = 0,
        int $infants = 0,
        int $rooms = 1,
        ?string $travelDate = null
    ): array {
        $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds))));

        $options = PackageHotelOption::query()
            ->with(['hotel', 'room', 'segment'])
            ->where('package_id', $package->id)
            ->whereIn('id', $optionIds)
            ->get();

        // Every submitted id must be a real, active option of this package.
        if ($options->count() !== count($optionIds)) {
            return $this->fail('One or more selected hotels are no longer available.');
        }

        foreach ($options as $option) {
            if ($option->status !== 'active') {
                return $this->fail('“' . $option->displayName() . '” is not available.');
            }
            if (! $this->availableOn($option, $travelDate)) {
                return $this->fail('“' . $option->displayName() . '” is not available for the selected travel date.');
            }
            if ($adults > $option->max_adults * $rooms) {
                return $this->fail('“' . $option->displayName() . '” cannot accommodate ' . $adults . ' adults in ' . $rooms . ' room(s).');
            }
            if ($children > $option->max_children * $rooms) {
                return $this->fail('“' . $option->displayName() . '” cannot accommodate ' . $children . ' children in ' . $rooms . ' room(s).');
            }
        }

        // At most one option per segment (and one whole-trip option).
        $bySegment = $options->groupBy(fn ($o) => $o->segment_id ?? 0);
        foreach ($bySegment as $group) {
            if ($group->count() > 1) {
                return $this->fail('Please pick only one hotel per stay.');
            }
        }

        $lines = [];
        $upgradeTotal = 0.0;

        foreach ($options as $option) {
            $line = $this->lineFor($option, $package, $adults, $children, $infants, $rooms, $travelDate);
            $upgradeTotal += $line['total'];
            $lines[] = $line;
        }

        return [
            'valid' => true,
            'error' => null,
            'upgrade_total' => round($upgradeTotal, 2),
            'lines' => $lines,
            'options' => $options,
        ];
    }

    /**
     * Compute the price + snapshot payload for a single selected option.
     *
     * @return array full snapshot-ready line (keys map to booking_hotels columns)
     */
    public function lineFor(
        PackageHotelOption $option,
        Package $package,
        int $adults,
        int $children,
        int $infants,
        int $rooms,
        ?string $travelDate
    ): array {
        $upgrade = (float) $option->upgrade_price;

        $base = match ($option->price_basis) {
            'per_person' => $upgrade * max(1, $adults + $children),
            'per_room' => $upgrade * max(1, $rooms),
            default => $upgrade, // per_booking
        };

        // Occupancy beyond the base occupancy costs extra (applies to included too).
        $capacityAdults = max(1, $option->base_adults * $rooms);
        $extraAdults = max(0, $adults - $capacityAdults);
        $extraAdultCost = $extraAdults * (float) ($option->extra_adult_price ?? 0);
        $extraChildCost = $children * (float) ($option->extra_child_price ?? 0);
        $extraGuest = round($extraAdultCost + $extraChildCost, 2);

        $total = round($base + $extraGuest, 2);

        [$checkIn, $checkOut, $nights] = $this->stayDates($option, $package, $travelDate);

        return [
            // references
            'package_hotel_option_id' => $option->id,
            'package_id' => $package->id,
            'hotel_id' => $option->hotel_id,
            'supplier_id' => $option->supplier_id,
            'supplier_hotel_id' => $option->supplier_hotel_code,
            'supplier_room_id' => $option->supplier_room_code,
            // snapshot
            'segment_label' => $option->segment?->label,
            'hotel_name_snapshot' => $option->displayName(),
            'address_snapshot' => $option->hotel?->address,
            'star_rating_snapshot' => $option->star_rating ?? $option->hotel?->star_rating,
            'room_name_snapshot' => $option->room_type ?? $option->room?->room_type,
            'meal_plan' => $option->meal_plan,
            'check_in' => $checkIn?->toDateString(),
            'check_out' => $checkOut?->toDateString(),
            'nights' => $nights,
            'rooms' => $rooms,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'occupancy_snapshot' => [
                'base_adults' => $option->base_adults,
                'extra_adults' => $extraAdults,
                'price_basis' => $option->price_basis,
            ],
            // pricing
            'is_included' => (bool) $option->is_default,
            'base_price' => round($base, 2),
            'upgrade_price' => $option->is_default ? 0.0 : round($base, 2),
            'extra_guest_price' => $extraGuest,
            'total' => $total,
            // policy
            'refundable' => (bool) $option->refundable,
            'cancellation_policy_snapshot' => $option->cancellation_policy,
            'status' => 'pending',
        ];
    }

    /**
     * Derive stay dates for a segment relative to the package departure date.
     */
    protected function stayDates(PackageHotelOption $option, Package $package, ?string $travelDate): array
    {
        if (! $travelDate) {
            return [null, null, $option->segment?->nights ?? $package->duration_nights ?? 1];
        }

        $departure = Carbon::parse($travelDate);
        $segment = $option->segment;

        if ($segment) {
            $offset = max(0, (int) ($segment->day_from ?? 1) - 1);
            $checkIn = $departure->copy()->addDays($offset);
            $nights = (int) ($segment->nights ?: 1);
        } else {
            $checkIn = $departure->copy();
            $nights = (int) ($package->duration_nights ?: 1);
        }

        $checkOut = $checkIn->copy()->addDays($nights);

        return [$checkIn, $checkOut, $nights];
    }

    protected function availableOn(PackageHotelOption $option, ?string $travelDate): bool
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
        return [
            'valid' => false,
            'error' => $message,
            'upgrade_total' => 0.0,
            'lines' => [],
            'options' => collect(),
        ];
    }
}
