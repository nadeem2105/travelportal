<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\Package;
use App\Models\PackageFlightOption;
use App\Models\PackageHotelOption;
use App\Models\PackageHotelSegment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo hotel-selection config for the Kashmir Delight package so the new
 * "Select Hotel" booking step shows real choices per stay. Idempotent:
 * rebuilds this package's segments/options each run and never touches
 * other packages. It also ensures each city has a tiered set of hotels
 * (3★ included, 4★ + 5★ upgrades) so every leg offers a genuine choice.
 *
 * Run:  php artisan db:seed --class=PackageHotelDemoSeeder
 */
class PackageHotelDemoSeeder extends Seeder
{
    /** Per-city hotel tiers: [name, stars, roomType, mealPlan, roomPrice, upgradeDelta]. */
    protected array $tiers = [
        ['%s Comfort Inn',   3, 'Deluxe Room',       'breakfast',  3000, 0],     // included
        ['%s Grand Resort',  4, 'Premium Room',      'half_board', 5000, 4500],  // upgrade
        ['%s Luxury Palace', 5, 'Suite',             'full_board', 9000, 9000],  // premium
    ];

    public function run(): void
    {
        $package = Package::where('slug', 'kashmir-delight')->first();

        if (! $package) {
            $this->command?->warn('kashmir-delight package not found — run DemoContentSeeder first.');
            return;
        }

        $package->update(['hotel_mode' => 'optional']);

        // Clean slate (cascades options) so re-running doesn't duplicate.
        $package->hotelSegments()->delete();
        $package->hotelOptions()->whereNull('segment_id')->delete();

        // city => [dayFrom, dayTo, nights]
        $legs = [
            'Srinagar' => [1, 2, 2],
            'Gulmarg'  => [3, 3, 1],
            'Pahalgam' => [4, 4, 1],
        ];

        $sort = 0;
        foreach ($legs as $city => [$dayFrom, $dayTo, $nights]) {
            $segment = PackageHotelSegment::create([
                'package_id' => $package->id,
                'label' => "{$city} · Day {$dayFrom}" . ($dayTo > $dayFrom ? "-{$dayTo}" : ''),
                'city' => $city,
                'day_from' => $dayFrom,
                'day_to' => $dayTo,
                'nights' => $nights,
                'sort_order' => $sort++,
            ]);

            foreach ($this->tiers as $i => [$nameFmt, $stars, $roomType, $meal, $roomPrice, $delta]) {
                $hotel = $this->ensureHotel(sprintf($nameFmt, $city), $city, $stars, $roomType, $meal, $roomPrice);
                $this->makeOption($package->id, $segment->id, $hotel, $i === 0, (float) $delta);
            }
        }

        $this->seedFlights($package);

        $this->command?->info(
            'Kashmir Delight now offers hotel selection across '
            . $package->hotelSegments()->count() . ' segment(s), '
            . $package->hotelOptions()->count() . ' hotel options, and '
            . $package->flightOptions()->count() . ' flight options.'
        );
    }

    /** Demo MakeMyTrip-style flight options (with/without flights). */
    protected function seedFlights(Package $package): void
    {
        $package->update(['flight_mode' => 'optional']);
        $package->flightOptions()->delete();

        $dest = $package->destination?->name ?? 'Srinagar';
        $destCode = 'SXR';

        $fares = [
            ['Delhi', 'DEL', 'IndiGo', 8500, true],
            ['Mumbai', 'BOM', 'Air India', 11500, false],
            ['Bengaluru', 'BLR', 'Vistara', 13500, false],
        ];

        $sort = 0;
        foreach ($fares as [$city, $code, $airline, $price, $isDefault]) {
            PackageFlightOption::create([
                'package_id' => $package->id,
                'label' => "Ex-{$city} Round Trip",
                'origin_city' => $city,
                'origin_airport_code' => $code,
                'destination_airport_code' => $destCode,
                'airline' => $airline,
                'trip_type' => 'round_trip',
                'cabin_class' => 'economy',
                'baggage' => '15kg check-in + 7kg cabin',
                'price_basis' => 'per_person',
                'price' => $price,
                'is_default' => $isDefault,
                'refundable' => false,
                'cancellation_policy' => 'Airline cancellation rules apply. Non-refundable convenience fee.',
                'status' => 'active',
                'sort_order' => $sort++,
            ]);
        }
    }

    /** Create (or reuse) a demo hotel + one room for a city. */
    protected function ensureHotel(string $name, string $city, int $stars, string $roomType, string $meal, float $roomPrice): Hotel
    {
        $destination = Destination::whereRaw('LOWER(name) = ?', [strtolower($city)])->first();

        $hotel = Hotel::updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'name' => $name,
                'destination_id' => $destination?->id,
                'city' => $city,
                'address' => "{$city}, Jammu & Kashmir",
                'star_rating' => $stars,
                'short_description' => "{$stars}-star stay in {$city}.",
                'amenities' => ['Free WiFi', 'Heating', 'Restaurant', 'Room Service'],
                'policies' => ['Check-in: 2 PM · Check-out: 12 PM', 'Valid government ID required at check-in'],
                'starting_price' => $roomPrice,
                'status' => 'active',
            ]
        );

        HotelRoom::updateOrCreate(
            ['hotel_id' => $hotel->id, 'room_type' => $roomType],
            [
                'description' => "{$roomType} with {$meal} at {$hotel->name}.",
                'max_adults' => 3,
                'max_children' => 2,
                'base_price' => $roomPrice,
                'extra_bed_price' => round($roomPrice * 0.35),
                'meal_plan' => $meal,
                'total_rooms' => 10,
                'status' => 'active',
            ]
        );

        return $hotel->fresh('rooms');
    }

    protected function makeOption(int $packageId, int $segmentId, Hotel $hotel, bool $included, float $upgrade): void
    {
        $room = $hotel->rooms->first();

        PackageHotelOption::create([
            'package_id' => $packageId,
            'segment_id' => $segmentId,
            'hotel_id' => $hotel->id,
            'hotel_room_id' => $room?->id,
            'room_type' => $room?->room_type ?? 'Standard Room',
            'meal_plan' => $room?->meal_plan ?? 'breakfast',
            'star_rating' => $hotel->star_rating,
            'base_adults' => 2,
            'max_adults' => max(2, (int) ($room?->max_adults ?? 3)),
            'max_children' => max(1, (int) ($room?->max_children ?? 2)),
            'extra_bed_allowed' => true,
            'is_default' => $included,
            'price_basis' => 'per_booking',
            'upgrade_price' => $included ? 0 : $upgrade,
            'extra_adult_price' => $room ? round($room->base_price * 0.6) : 1500,
            'extra_child_price' => $room ? round($room->base_price * 0.3) : 800,
            'extra_bed_price' => (float) ($room?->extra_bed_price ?? 1000),
            'refundable' => $included,
            'cancellation_policy' => $included
                ? 'Free cancellation up to 7 days before check-in.'
                : 'Non-refundable once confirmed.',
            'status' => 'active',
            'sort_order' => $included ? 0 : ($hotel->star_rating),
        ]);
    }
}
