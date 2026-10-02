<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Blog;
use App\Models\CabLocation;
use App\Models\CabVendor;
use App\Models\Coupon;
use App\Models\Destination;
use App\Models\Faq;
use App\Models\Guide;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\Package;
use App\Models\PackageDeparture;
use App\Models\PackageItinerary;
use App\Models\PackagePrice;
use App\Models\Page;
use App\Models\Supplier;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotent demo content: Kashmir destinations, packages, hotels, cabs,
 * airports, airlines, coupons, offers, CMS pages and menus.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedAirports();
        $this->seedAirlines();
        $this->seedSuppliers();
        $this->seedTaxes();
        $this->seedDestinations();
        $this->seedHotels();
        $this->seedCabs();
        $this->seedPackages();
        $this->seedCouponsOffers();
        $this->seedTestimonials();
        $this->seedFaqs();
        $this->seedPages();
        $this->seedBlogsGuides();
        $this->seedMenus();
    }

    protected function seedTaxes(): void
    {
        \App\Models\Tax::updateOrCreate(
            ['name' => 'GST', 'product_type' => 'all'],
            ['calculation' => 'percentage', 'value' => 5, 'is_inclusive' => false, 'status' => 'active', 'description' => 'GST applied to all bookings']
        );

        \App\Models\Tax::updateOrCreate(
            ['name' => 'Convenience Fee', 'product_type' => 'flight'],
            ['calculation' => 'fixed', 'value' => 149, 'is_inclusive' => false, 'status' => 'active', 'description' => 'Per-booking convenience fee']
        );
    }

    protected function seedUsers(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@leemroztravels.com'],
            ['name' => 'Aarav Sharma', 'phone' => '+91 98765 43210', 'password' => 'Demo@12345', 'city' => 'New Delhi']
        );
    }

    protected function seedAirports(): void
    {
        $airports = [
            ['DEL', 'Indira Gandhi International Airport', 'New Delhi', 1],
            ['SXR', 'Sheikh ul-Alam International Airport', 'Srinagar', 1],
            ['IXJ', 'Jammu Airport', 'Jammu', 1],
            ['BOM', 'Chhatrapati Shivaji Maharaj International Airport', 'Mumbai', 1],
            ['BLR', 'Kempegowda International Airport', 'Bengaluru', 1],
            ['HYD', 'Rajiv Gandhi International Airport', 'Hyderabad', 0],
            ['CCU', 'Netaji Subhas Chandra Bose International Airport', 'Kolkata', 0],
            ['MAA', 'Chennai International Airport', 'Chennai', 0],
            ['ATQ', 'Sri Guru Ram Dass Jee International Airport', 'Amritsar', 0],
            ['IXC', 'Chandigarh International Airport', 'Chandigarh', 0],
            ['JAI', 'Jaipur International Airport', 'Jaipur', 0],
            ['GOI', 'Dabolim Airport', 'Goa', 0],
        ];

        foreach ($airports as $i => [$code, $name, $city, $popular]) {
            Airport::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'city' => $city, 'is_popular' => (bool) $popular, 'sort_order' => $i]
            );
        }
    }

    protected function seedAirlines(): void
    {
        foreach ([['UK', 'Vistara', 20], ['6E', 'IndiGo', 15], ['AI', 'Air India', 25], ['QP', 'Akasa Air', 15], ['SG', 'SpiceJet', 15], ['IX', 'Air India Express', 20]] as $i => [$code, $name, $baggage]) {
            Airline::updateOrCreate(['code' => $code], ['name' => $name, 'default_baggage_kg' => $baggage, 'is_lcc' => in_array($code, ['6E', 'SG', 'QP', 'IX'])]);
        }
    }

    protected function seedSuppliers(): void
    {
        Supplier::updateOrCreate(
            ['slug' => 'demo-flights'],
            ['name' => 'Leemroz Travels Demo Inventory', 'type' => 'flight', 'adapter' => 'demo', 'priority' => 1, 'status' => 'active', 'description' => 'Built-in demo flight inventory for development & demos.']
        );

        Supplier::updateOrCreate(
            ['slug' => 'manual-hotels'],
            ['name' => 'Direct Hotel Contracts', 'type' => 'hotel', 'adapter' => 'manual', 'priority' => 1, 'status' => 'active', 'description' => 'Hotels managed manually from the admin panel.']
        );

        foreach (['Amadeus' => 'amadeus', 'TBO Holidays' => 'tbo', 'Akbar Travels' => 'akbar'] as $name => $adapter) {
            Supplier::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'type' => 'flight', 'adapter' => $adapter, 'priority' => 10, 'status' => 'inactive', 'description' => 'Skeleton adapter — add API credentials to enable.']
            );
        }
    }

    protected function seedDestinations(): void
    {
        $destinations = [
            ['Srinagar', 'Lakes, Houseboats & More', 1.585, 'April – October', 'Dal Lake & Shikaras', ['Dal Lake', 'Mughal Gardens', 'Shankaracharya Temple', 'Old City'], ['Shikara ride at sunset', 'Stay in a houseboat', 'Visit Mughal Gardens']],
            ['Gulmarg', 'Snow, Adventure & Beauty', 2.65, 'December – March (snow), June – September (meadows)', 'Gulmarg Gondola', ['Gulmarg Gondola', 'Apharwat Peak', 'Strawberry Valley'], ['Skiing & snowboarding', 'Ride the Gondola', 'Snowshoeing']],
            ['Pahalgam', 'Valleys, Rivers & Tranquility', 2.2, 'April – November', 'Lidder River', ['Betaab Valley', 'Aru Valley', 'Baisaran Hills'], ['River rafting', 'Trout fishing', 'Pony treks']],
            ['Sonamarg', 'Meadows of Gold', 2.73, 'May – September', 'Thajiwas Glacier', ['Thajiwas Glacier', 'Zero Point', 'Krishnasar Lake'], ['Glacier trek', 'Zoji La drive', 'Angling']],
            ['Doodhpathri', 'Unexplored Paradise', 2.7, 'May – September', 'Milky Meadows', ['Milky Meadows', 'Tangnar', 'Diskal'], ['Meadow picnics', 'River walks', 'Photography']],
            ['Yusmarg', 'Nature at Its Best', 2.4, 'April – October', 'Pine Forests', ['Nilnag Lake', 'Sang-e-Safed', 'Charar-e-Sharief'], ['Forest walks', 'Horse riding', 'Lake picnics']],
        ];

        foreach ($destinations as $i => [$name, $famous, $altitudeKm, $bestTime, $region, $places, $things]) {
            $destination = Destination::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'region' => 'Kashmir Valley',
                    'cover_image' => 'images/destinations/' . Str::slug($name) . '.svg',
                    'short_description' => "Experience {$famous} — one of Kashmir's most loved destinations.",
                    'description' => "{$name}, set at an altitude of about {$altitudeKm} km above sea level, is the soul of the Kashmir Valley. " .
                        "Cradled by the snow-clad Pir Panjal and Zanskar ranges, it offers postcard scenery in every season — " .
                        "apple orchards in autumn, powdery snow in winter, and wildflower meadows in summer.\n\n" .
                        "Our local travel experts have spent years curating the best of {$name}: where to stay, what to eat, and which hidden corners most visitors never find.",
                    'best_time' => $bestTime,
                    'altitude' => $altitudeKm . ' km',
                    'famous_for' => $famous,
                    'places_to_visit' => $places,
                    'things_to_do' => $things,
                    'is_featured' => true,
                    'sort_order' => $i,
                ]
            );

            // attach region used by guides/blogs below
            $this->destinations[$name] = $destination;
        }
    }

    protected array $destinations = [];

    protected function seedHotels(): void
    {
        $hotels = [
            ['Dal Grand Houseboat & Resort', 'srinagar', 4, 4500, ['Lake View', 'Shikara Pickup', 'Free WiFi', 'Restaurant', 'Room Service'], 'Premium houseboat-style resort on the shores of Dal Lake.', 3500, 6500, 9500],
            ['The Gulmarg Alpine Retreat', 'gulmarg', 5, 7200, ['Ski-in Ski-out', 'Heated Rooms', 'Spa', 'Fine Dining', 'Gondola Shuttle'], 'Slopeside luxury at the foot of Apharwat Peak.', 5500, 8500, 12000],
            ['Pahalgam Riverside Cottages', 'pahalgam', 3, 3200, ['Riverside', 'Bonfire', 'Free Parking', 'Garden'], 'Cosy cottages along the singing Lidder river.', 2500, 4000, 5500],
            ['Sonamarg Glacier Camps', 'sonamarg', 4, 5400, ['Glacier View', 'Bonfire', 'Swiss Tents', 'Restaurant'], 'Luxury glamping with Thajiwas glacier views.', 4200, 6500, 8000],
            ['Srinagar Heritage Haveli', 'srinagar', 3, 2800, ['Old City', 'Rooftop Cafe', 'Kashmiri Breakfast'], 'A restored 19th-century haveli in the heart of the old city.', 2200, 3500, 4900],
            ['Doodhpathri Meadow Resort', 'doodhpathri', 3, 3600, ['Meadow View', 'Dining Tent', 'Treks'], 'Wake up to the rolling Milky Meadows.', 3000, 4500, 6000],
        ];

        foreach ($hotels as $i => [$name, $destSlug, $stars, $startPrice, $amenities, $short, $budget, $deluxe, $suite]) {
            $destination = $this->destinations[ucfirst($destSlug)] ?? Destination::where('slug', $destSlug)->first();

            $hotel = Hotel::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'destination_id' => $destination?->id,
                    'city' => $destination?->name,
                    'address' => $destination?->name . ', Jammu & Kashmir',
                    'star_rating' => $stars,
                    'short_description' => $short,
                    'description' => $short . "\n\nEach stay includes warm Kashmiri hospitality, handpicked interiors with walnut wood and chinar motifs, and an in-house travel desk that can arrange shikara rides, gondola tickets and guided treks.",
                    'amenities' => $amenities,
                    'policies' => ['Check-in: 2 PM · Check-out: 12 PM', 'Free cancellation up to 48 hours before check-in', 'Valid government ID required at check-in', 'Children under 5 stay free'],
                    'cover_image' => 'images/destinations/' . $destSlug . '.svg',
                    'photos' => ['images/destinations/' . $destSlug . '.svg'],
                    'starting_price' => $startPrice,
                    'is_featured' => $i < 3,
                ]
            );

            foreach ([
                ['Budget Room', $budget, 2, 'room_only'],
                ['Deluxe Room', $deluxe, 2, 'breakfast'],
                ['Family Suite', $suite, 4, 'half_board'],
            ] as [$type, $price, $adults, $meal]) {
                HotelRoom::updateOrCreate(
                    ['hotel_id' => $hotel->id, 'room_type' => $type],
                    [
                        'description' => $type . ' with modern amenities and ' . ($meal === 'room_only' ? 'room only' : $meal) . '.',
                        'max_adults' => $adults,
                        'max_children' => $adults >= 4 ? 2 : 1,
                        'base_price' => $price,
                        'meal_plan' => $meal,
                        'amenities' => ['Free WiFi', 'Heating', 'Hot Water', 'TV'],
                        'total_rooms' => 6,
                    ]
                );
            }
        }
    }

    protected function seedCabs(): void
    {
        $vendor = CabVendor::updateOrCreate(
            ['name' => 'Valley Cabs (Srinagar)'],
            ['contact_person' => 'Imran Bhat', 'phone' => '+91 99065 00001', 'commission_percent' => 12]
        );

        foreach (['Sedan', 'SUV', 'Tempo Traveller', 'Luxury'] as $i => $type) {
            VehicleType::updateOrCreate(['name' => $type], ['sort_order' => $i]);
        }

        $vehicles = [
            ['Dzire / Swift (Sedan)', 'sedan', 4, 2, 2200, 14, 220],
            ['Innova Crysta (SUV)', 'suv', 6, 4, 3500, 20, 350],
            ['Tempo Traveller 12-Seat', 'tempo traveller', 12, 10, 5200, 26, 480],
            ['Mercedes E-Class (Luxury)', 'luxury', 4, 3, 8000, 38, 700],
        ];

        foreach ($vehicles as $i => [$name, $type, $seats, $bags, $base, $perKm, $perHour]) {
            Vehicle::updateOrCreate(
                ['name' => $name],
                [
                    'vendor_id' => $vendor->id,
                    'vehicle_type_id' => VehicleType::where('name', $type === 'sedan' ? 'Sedan' : ($type === 'suv' ? 'SUV' : ($type === 'tempo traveller' ? 'Tempo Traveller' : 'Luxury')))->value('id'),
                    'image' => null,
                    'passenger_capacity' => $seats,
                    'luggage_capacity' => $bags,
                    'base_price' => $base,
                    'per_km_rate' => $perKm,
                    'per_hour_rate' => $perHour,
                    'cancellation_policy' => 'Free cancellation up to 12 hours before pickup. 50% charge within 12 hours.',
                    'is_featured' => $i < 2,
                ]
            );
        }

        foreach ([['Srinagar Airport (SXR)', 'airport'], ['Srinagar — Dal Gate', 'local'], ['Srinagar — Lal Chowk', 'local'], ['Gulmarg', 'outstation'], ['Pahalgam', 'outstation'], ['Sonamarg', 'outstation'], ['Doodhpathri', 'outstation'], ['Yusmarg', 'outstation'], ['Jammu', 'outstation']] as $i => [$name, $type]) {
            CabLocation::updateOrCreate(['name' => $name], ['city' => 'Srinagar', 'type' => $type, 'sort_order' => $i]);
        }
    }

    protected function seedPackages(): void
    {
        $packages = [
            [
                'Kashmir Delight', 'kashmir-delight', 'srinagar', 5, 4, 24999, 9,
                ['Srinagar', 'Gulmarg', 'Pahalgam'],
                'Houseboats, gondolas and valley meadows — the perfect first taste of Kashmir.',
                ['Shikara ride on Dal Lake at sunset', 'Gulmarg Gondola ride to Apharwat Peak', 'Betaab Valley excursion', 'Stay in a premium houseboat'],
                ['Hotel', 'Sightseeing', 'Cab', 'Breakfast', 'Airport Transfers'],
                ['Airfare', 'Lunch & Dinner', 'Entry tickets', 'Personal expenses'],
                [
                    [1, 'Arrive in Srinagar — Dal Lake Welcome', 'Land at Srinagar airport where our representative welcomes you. Check in to your houseboat and unwind with an evening Shikara ride across Dal Lake as the sun sets behind the Zabarwan hills.', 'Srinagar'],
                    [2, 'Srinagar — Mughal Gardens & Old City', 'Visit Nishat Bagh, Shalimar Bagh and Chashme Shahi. Walk through the old city and Shankaracharya Temple for panoramic valley views.', 'Srinagar'],
                    [3, 'Day Trip to Gulmarg', 'Drive through the saffron fields of Pampore to Gulmarg. Ride the world-famous Gondola to Apharwat Peak (subject to weather).', 'Srinagar'],
                    [4, 'Pahalgam — Valley of Shepherds', 'Full-day excursion to Pahalgam along the Lidder river. Visit Betaab Valley and Aru, with time for river-side lunch.', 'Srinagar'],
                    [5, 'Departure', 'Transfer to Srinagar airport with a basket of Kashmiri almonds and memories for life.', null],
                ],
                'Best Seller', true,
            ],
            [
                'Kashmir Honeymoon Escape', 'kashmir-honeymoon', 'srinagar', 6, 5, 32999, 12,
                ['Srinagar', 'Gulmarg', 'Pahalgam', 'Sonamarg'],
                'Romantic shikara evenings, private dinners and snow-kissed escapades for two.',
                ['Candle-light dinner on a private shikara', 'Photoshoot in traditional Kashmiri attire', 'Sonamarg glacier day', 'Couple spa session'],
                ['Hotel', 'Sightseeing', 'Cab', 'Meals', 'Special Decorations'],
                ['Airfare', 'Entry tickets', 'Personal expenses'],
                [
                    [1, 'Arrival & Romantic Shikara Evening', 'Private airport pickup, houseboat check-in, and a candle-lit shikara dinner under the stars.', 'Srinagar'],
                    [2, 'Gardens & Photoshoot', 'Mughal gardens stroll and a traditional Kashmiri attire photoshoot.', 'Srinagar'],
                    [3, 'Gulmarg — Snow Time', 'Gondola ride, snow play and cosy cafe time in Gulmarg.', 'Gulmarg'],
                    [4, 'Sonamarg — Meadow of Gold', 'Drive to Sonamarg, visit Thajiwas Glacier viewpoint, return by evening.', 'Srinagar'],
                    [5, 'Pahalgam Leisure Day', 'Slow day in Pahalgam — riverside walks, horse riding and couple spa.', 'Pahalgam'],
                    [6, 'Departure', 'Transfer to airport with handmade saffron gift.', null],
                ],
                'Honeymoon Special', true,
            ],
            [
                'Kashmir Family Package', 'kashmir-family', 'srinagar', 6, 5, 29999, 8,
                ['Srinagar', 'Gulmarg', 'Pahalgam', 'Doodhpathri'],
                'Kid-friendly stays, gentle adventures and fun-filled meadow days for the whole family.',
                ['Family photo session', 'Doodhpathri meadow day', 'Snow activities in Gulmarg', 'Connected family rooms'],
                ['Hotel', 'Sightseeing', 'Cab', 'Breakfast'],
                ['Airfare', 'Lunch & Dinner', 'Entry tickets'],
                [
                    [1, 'Arrive & Relax', 'Airport pickup, family room check-in, evening lake boulevard walk.', 'Srinagar'],
                    [2, 'Gardens & Shikara', 'Mughal gardens tour and family shikara ride.', 'Srinagar'],
                    [3, 'Gulmarg Snow Day', 'Gondola and snow fun for all ages.', 'Srinagar'],
                    [4, 'Doodhpathri Meadows', 'Picnic in the Milky Meadows with games and pony rides.', 'Srinagar'],
                    [5, 'Pahalgam Riverside', 'Betaab Valley visit and riverside family time.', 'Srinagar'],
                    [6, 'Departure', 'Airport transfer.', null],
                ],
                'Family Favourite', true,
            ],
            [
                'Gulmarg Winter Special', 'gulmarg-winter', 'gulmarg', 4, 3, 27999, 10,
                ['Gulmarg', 'Srinagar'],
                'Ski lessons, gondola rides and cosy fireside evenings in India\'s winter capital.',
                ['2-day beginner ski course', 'Gondola phase 1 & 2 tickets', 'Ski gear included', 'Bonfire evenings'],
                ['Hotel', 'Ski Equipment', 'Activities', 'Breakfast'],
                ['Airfare', 'Meals other than breakfast', 'Gondola express pass'],
                [
                    [1, 'Arrive in Gulmarg', 'Scenic drive from Srinagar. Gear fitting and orientation at the ski school.', 'Gulmarg'],
                    [2, 'Ski Day 1', 'Beginner slopes training with certified instructors.', 'Gulmarg'],
                    [3, 'Gondola & Apharwat', 'Gondola to Apharwat, advanced practice and snow play.', 'Gulmarg'],
                    [4, 'Departure via Srinagar', 'Transfer to Srinagar airport.', null],
                ],
                'Adventure', false,
            ],
        ];

        foreach ($packages as $i => [$name, $slug, $destSlug, $days, $nights, $price, $discount, $cities, $short, $highlights, $inclusions, $exclusions, $itinerary, $badge, $featured]) {
            $package = Package::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'destination_id' => Destination::where('slug', $destSlug)->value('id'),
                    'duration_days' => $days,
                    'duration_nights' => $nights,
                    'cover_image' => 'images/packages/' . $slug . '.svg',
                    'gallery' => array_map(fn ($c) => 'images/destinations/' . Str::slug($c) . '.svg', $cities),
                    'short_description' => $short,
                    'description' => $short . "\n\nThis " . $days . "-day journey has been crafted by our Srinagar-based travel designers and includes handpicked stays, private transfers, and 24/7 on-trip support. Group sizes stay small so you travel like family, not a crowd.\n\nEvery booking supports local guides, drivers and artisans across the valley.",
                    'highlights' => $highlights,
                    'inclusions' => $inclusions,
                    'exclusions' => $exclusions,
                    'cancellation_policy' => 'Free cancellation up to 7 days before departure. 25% charge within 7 days. No refund within 72 hours of departure.',
                    'base_price' => $price,
                    'child_price' => round($price * 0.6),
                    'discount_percent' => $discount,
                    'package_type' => 'group',
                    'is_featured' => $featured,
                    'max_travellers' => 20,
                    'status' => 'active',
                ]
            );

            foreach ($itinerary as $j => [$dayNumber, $title, $description, $overnight]) {
                PackageItinerary::updateOrCreate(
                    ['package_id' => $package->id, 'day_number' => $dayNumber],
                    [
                        'title' => $title,
                        'description' => $description,
                        'meals' => $j === 0 ? ['Dinner'] : ['Breakfast', 'Dinner'],
                        'overnight_stay' => $overnight,
                    ]
                );
            }

            PackagePrice::updateOrCreate(
                ['package_id' => $package->id, 'season' => 'peak'],
                ['label' => 'Peak Season (Apr–Jun, Dec–Jan)', 'price_per_person' => round($price * 1.15), 'is_default' => false]
            );
            PackagePrice::updateOrCreate(
                ['package_id' => $package->id, 'season' => 'off'],
                ['label' => 'Off Season (Jul–Nov)', 'price_per_person' => round($price * 0.9), 'is_default' => false]
            );

            foreach ([now()->addDays(20), now()->addDays(35), now()->addDays(50)] as $k => $date) {
                PackageDeparture::updateOrCreate(
                    ['package_id' => $package->id, 'departure_date' => $date->toDateString()],
                    ['inventory' => 16 + $k * 4]
                );
            }
        }
    }

    protected function seedCouponsOffers(): void
    {
        $firstTrip = Coupon::updateOrCreate(
            ['code' => 'FIRSTTRIP'],
            [
                'description' => 'Flat ₹1,500 off your first booking',
                'discount_type' => 'fixed',
                'discount_value' => 1500,
                'min_booking_amount' => 10000,
                'per_user_limit' => 1,
                'first_booking_only' => true,
            ]
        );

        $kashmir10 = Coupon::updateOrCreate(
            ['code' => 'KASHMIR10'],
            [
                'description' => '10% off on all Kashmir packages (max ₹3,000)',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_discount' => 3000,
                'min_booking_amount' => 15000,
                'product_types' => ['package'],
                'per_user_limit' => 3,
            ]
        );

        Offer::updateOrCreate(
            ['slug' => 'first-trip-1500-off'],
            [
                'title' => 'Flat ₹1,500 Off Your First Trip',
                'description' => 'New to Leemroz Travels? Use code FIRSTTRIP on your first booking above ₹10,000.',
                'discount_text' => '₹1500 OFF',
                'badge' => 'New User',
                'coupon_id' => $firstTrip->id,
                'button_text' => 'Grab Deal',
            ]
        );

        Offer::updateOrCreate(
            ['slug' => 'kashmir-packages-10-off'],
            [
                'title' => '10% Off Kashmir Packages',
                'description' => 'Apply KASHMIR10 on any tour package and save up to ₹3,000. Limited period.',
                'discount_text' => '10% OFF',
                'badge' => 'Season Sale',
                'coupon_id' => $kashmir10->id,
                'button_text' => 'View Packages',
                'link_url' => '/packages',
            ]
        );
    }

    protected function seedTestimonials(): void
    {
        $testimonials = [
            ['Ananya Sharma', 'a1.svg', 'New Delhi', 'Kashmir Delight', 5, 'The houseboat stay on Dal Lake was pure magic. Our driver Imran knew every shortcut and viewpoint. Flawless planning by Leemroz Travels!'],
            ['Rohit Khanna', 'a2.svg', 'Mumbai', 'Gulmarg Winter Special', 5, 'Ski school for beginners was brilliant, and the bonfire evenings were the highlight. Already planning our summer trip back.'],
            ['Priya & Manav', 'a3.svg', 'Bengaluru', 'Kashmir Honeymoon Escape', 5, 'The private shikara dinner under the stars — we will remember it forever. Thank you for the seamless experience!'],
            ['Nisha Jadhav', 'a4.svg', 'Pune', 'Kashmir Family Package', 4, 'Travelled with kids and elderly parents — connected rooms, gentle pace, and the team was just a call away throughout.'],
            ['Zaid Mir', 'a5.svg', 'Hyderabad', 'Kashmir Delight', 5, 'Best price among all portals I checked, plus genuinely local expertise. The Doodhpathri detour was the cherry on top.'],
            ['Kavita Rao', 'a1.svg', 'Chennai', 'Pahalgam Riverside', 5, 'Cottage by the Lidder river was even better than the photos. Booking to refund — everything was transparent.'],
        ];

        foreach ($testimonials as $i => [$name, $avatar, $city, $destination, $rating, $content]) {
            Testimonial::updateOrCreate(
                ['customer_name' => $name, 'city' => $city],
                [
                    'customer_photo' => 'images/avatars/' . $avatar,
                    'destination' => $destination,
                    'rating' => $rating,
                    'content' => $content,
                    'is_featured' => $i < 3,
                ]
            );
        }
    }

    protected function seedFaqs(): void
    {
        $faqs = [
            ['general', 'How do I book a tour package?', 'Choose a package, pick your departure date, enter traveller details and pay securely online. You will receive instant confirmation on email and WhatsApp.'],
            ['general', 'Do you offer customized itineraries?', 'Yes! Every package can be customized — stays, pace, and experiences. Contact our team and we will build a plan around your dates and budget.'],
            ['payments', 'Which payment methods do you accept?', 'We accept UPI, credit/debit cards, net banking and wallets via our secure payment gateway. Your payment details are never stored by us.'],
            ['payments', 'Is my payment secure?', 'Absolutely. All transactions run through PCI-DSS compliant gateways with bank-grade encryption. We never see or store your card details.'],
            ['cancellations', 'What is your cancellation policy?', 'Package cancellations are free up to 7 days before departure (25% within 7 days, none within 72 hours). Hotel and flight cancellations follow the supplier policy shown on each booking.'],
            ['cancellations', 'How long do refunds take?', 'Approved refunds are initiated immediately and typically reach your account in 5–7 working days, depending on your bank.'],
            ['travel', 'Do I need any permits for Kashmir?', 'Indian nationals need only a valid photo ID. Some areas near the LoC require permits which our team arranges for you at no extra cost.'],
            ['travel', 'What should I pack for Gulmarg in winter?', 'Layered woollens, waterproof boots, sunglasses and sunscreen. Our pre-departure mailer includes a complete packing checklist.'],
        ];

        foreach ($faqs as $i => [$category, $question, $answer]) {
            Faq::updateOrCreate(['question' => $question], ['answer' => $answer, 'category' => $category, 'sort_order' => $i]);
        }
    }

    protected function seedPages(): void
    {
        $pages = [
            ['About Us', 'about-us', 'Leemroz Travels is a Srinagar-based travel company run by locals who grew up between these mountains. We combine deep local knowledge with modern booking technology to make Kashmir accessible, safe and unforgettable for every traveller.\n\nFrom houseboat stays on Dal Lake to heli-skiing on Apharwat, our team has hosted thousands of happy travellers — and we would love to host you next.'],
            ['Cancellation & Refund Policy', 'cancellation-refund', '## General Policy\n\n- Free cancellation up to 7 days before departure for tour packages\n- 25% charge for cancellations within 7 days\n- No refund within 72 hours of departure\n\n## Hotels\n\n- Free cancellation up to 48 hours before check-in (unless stated otherwise)\n- One night charge within 48 hours\n- No-show: full booking amount\n\n## Flights\n\n- Airline fares follow the fare rules shown at the time of booking\n- Refunds are credited within 5–7 working days after airline confirmation\n\n## Refund Timeline\n\nApproved refunds are initiated immediately and typically reflect in 5–7 working days depending on your bank.'],
            ['Terms & Conditions', 'terms', 'By booking with Leemroz Travels you agree to:\n\n- Provide accurate traveller information as per government ID\n- Carry valid identification at all times during the trip\n- Respect local customs and environmental guidelines\n- Understand that itineraries may adjust for weather or safety, especially in mountain areas\n\nAll bookings are subject to availability and confirmation by the respective supplier.'],
            ['Privacy Policy', 'privacy', 'We collect only the information required to process your bookings: name, contact details, traveller documents and payment confirmations.\n\n- We never store card numbers or payment credentials\n- Data is shared only with the suppliers required to fulfil your booking\n- You may request deletion of your account data at any time at privacy@leemroztravels.com'],
            ['Cookie Policy', 'cookie-policy', 'We use essential cookies to keep you signed in and remember your preferences, plus anonymous analytics cookies to improve the site. You can disable non-essential cookies in your browser settings at any time.'],
        ];

        foreach ($pages as $i => [$title, $slug, $content]) {
            Page::updateOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'content' => $content, 'status' => 'published', 'published_at' => now(), 'sort_order' => $i]
            );
        }
    }

    protected function seedBlogsGuides(): void
    {
        $guides = [
            ['best-places-to-visit-in-kashmir', 'Best Places to Visit in Kashmir', 'From Dal Lake to Doodhpathri — the definitive list of Kashmir must-visits for 2026.', '## The Classics\n\n**Srinagar** — Dal Lake, Mughal Gardens and the old city\n**Gulmarg** — The Gondola and winter sports\n**Pahalgam** — Rivers, valleys and pony treks\n\n## The Hidden Gems\n\n**Doodhpathri** — The Milky Meadows, barely an hour from Srinagar\n**Yusmarg** — Meadows and pine forests without the crowds\n\n## When to Go\n\nApril to June for flowers, December to February for snow. Autumn (Oct–Nov) turns the chinars golden — a photographer\'s dream.'],
            ['kashmir-tulip-garden-guide', 'Indira Gandhi Tulip Garden: Complete Guide', 'Asia\'s largest tulip garden blooms for just a few weeks — here\'s how to catch it.', 'Every spring (late March to mid-April), over 1.5 million tulips light up the foothills of Zabarwan range overlooking Dal Lake.\n\n- Best time: first two weeks of April\n- Entry: nominal ticket, open 7 AM–7:30 PM\n- Pro tip: go on a weekday morning for soft light and thin crowds'],
            ['kashmir-snowfall-when-where', 'When Does It Snow in Kashmir?', 'Planning a snow trip? Here is exactly when and where to find snow in the valley.', '## December–February\n\nGulmarg (best), Sonamarg (road dependent), Srinagar (occasional city snowfall)\n\n## Key tips\n\n- Gulmarg gondola runs weather permitting; book the first slot\n- Carry waterproof boots — regular shoes will not survive\n- Chain-fitted vehicles are mandatory on the Gulmarg road during fresh snowfall'],
        ];

        foreach ($guides as $i => [$slug, $title, $excerpt, $content]) {
            Guide::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'cover_image' => 'images/guides/' . $slug . '.svg',
                    'sort_order' => $i,
                    'status' => 'active',
                ]
            );
        }

        $blogs = [
            ['houseboat-stay-srinagar', 'What It\'s Really Like to Stay on a Houseboat', 'Stories', 'Cedar panels, kahwa on the deck, and the gentle lap of Dal Lake all night — a houseboat stay is Kashmir in miniature.', 'Our first evening on the houseboat began with saffron kahwa served on the deck as shikaras glided past trailing lantern light...\n\nBy day two, the boat\'s owner had taught us to paddle, his wife had fed us nadru yakhni, and we had completely forgotten the concept of "checking our phones".\n\n**What to know before you book:**\n\n- Choose heritage boats over floating hotels for character\n- Winter stays are heated but pack warm sleepwear\n- Ask for the upper-deck rooms for morning light'],
            ['autumn-kashmir-why-you-should-go', 'Autumn in Kashmir: The Golden Season Nobody Talks About', 'Travel Tips', 'October in Kashmir means golden chinars, apple orchards heavy with fruit, and hotel prices at their lowest.', 'Everyone chases spring tulips and winter snow. But ask any Srinagar local and they will tell you the valley is at its most beautiful in late October...\n\nThe chinar avenues of Naseem Bagh turn crimson, saffron harvest begins in Pampore, and the light over Dal Lake at 5 PM feels like the whole valley switched on a filter.\n\n**Bonus:** hotel rates drop 30–40% after Dussehra.'],
        ];

        foreach ($blogs as $i => [$slug, $title, $category, $excerpt, $content]) {
            Blog::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'category' => $category,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'cover_image' => 'images/blogs/' . $slug . '.svg',
                    'tags' => ['kashmir', 'travel'],
                    'status' => 'published',
                    'published_at' => now()->subDays(7 - $i),
                ]
            );
        }
    }

    protected function seedMenus(): void
    {
        $header = Menu::updateOrCreate(['location' => 'header', 'is_default' => true], ['name' => 'Main Menu']);

        $items = [
            ['Home', '/'], ['Flights', '/flights'], ['Hotels', '/hotels'], ['Cabs', '/cabs'],
            ['Packages', '/packages'], ['Offers', '/offers'], ['Kashmir Guide', '/guide'],
        ];
        foreach ($items as $i => [$label, $url]) {
            MenuItem::updateOrCreate(
                ['menu_id' => $header->id, 'label' => $label, 'url' => $url],
                ['target' => '_self', 'sort_order' => $i, 'status' => 'active']
            );
        }

        $quick = Menu::updateOrCreate(['location' => 'footer_quick', 'is_default' => true], ['name' => 'Footer — Quick Links']);
        foreach ([['Popular Packages', '/packages'], ['Destinations', '/destinations'], ['Offers & Coupons', '/offers'], ['Kashmir Guide', '/guide'], ['Blog', '/blog']] as $i => [$label, $url]) {
            MenuItem::updateOrCreate(['menu_id' => $quick->id, 'label' => $label, 'url' => $url], ['sort_order' => $i, 'status' => 'active']);
        }

        $support = Menu::updateOrCreate(['location' => 'footer_support', 'is_default' => true], ['name' => 'Footer — Support']);
        foreach ([['Contact Us', '/contact'], ['FAQs', '/faq'], ['Support / Tickets', '/support'], ['Cancellation & Refund', '/p/cancellation-refund'], ['Terms & Conditions', '/p/terms']] as $i => [$label, $url]) {
            MenuItem::updateOrCreate(['menu_id' => $support->id, 'label' => $label, 'url' => $url], ['sort_order' => $i, 'status' => 'active']);
        }

        $legal = Menu::updateOrCreate(['location' => 'footer_legal', 'is_default' => true], ['name' => 'Footer — Legal']);
        foreach ([['Privacy Policy', '/p/privacy'], ['Terms', '/p/terms'], ['Cookie Policy', '/p/cookie-policy']] as $i => [$label, $url]) {
            MenuItem::updateOrCreate(['menu_id' => $legal->id, 'label' => $label, 'url' => $url], ['sort_order' => $i, 'status' => 'active']);
        }
    }
}
