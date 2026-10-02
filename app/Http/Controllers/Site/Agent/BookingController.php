<?php

namespace App\Http\Controllers\Site\Agent;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Package;
use App\Services\AgentBookingService;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        protected AgentBookingService $agentBooking,
        protected BookingService $bookings,
        protected PricingService $pricing,
    ) {
    }

    /** Browse packages with agent net pricing shown. */
    public function packages(Request $request)
    {
        $agent = auth('agent')->user();

        $query = Package::with('destination')->where('status', 'active');

        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhereHas('destination', fn ($d) => $d->where('name', 'like', "%{$q}%")));
        }

        if ($destination = $request->query('destination')) {
            $query->whereHas('destination', fn ($d) => $d->where('slug', $destination));
        }

        match ($request->query('sort')) {
            'price_low' => $query->orderBy('base_price'),
            'price_high' => $query->orderByDesc('base_price'),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        return view('agent.packages.index', [
            'seo' => ['title' => 'Book Packages'],
            'agent' => $agent,
            'packages' => $query->paginate(9)->withQueryString(),
            'destinations' => Destination::where('status', 'active')->orderBy('sort_order')->get(),
        ]);
    }

    /** Booking form for a single package (agent net price preview). */
    public function create(Request $request, Package $package)
    {
        if ($package->status !== 'active') {
            abort(404);
        }

        $agent = auth('agent')->user();
        $package->load(['destination', 'itineraries']);

        $departureDate = $request->query('date', now()->addDays(14)->format('Y-m-d'));
        $quote = $this->quoteFor($agent, $package, $departureDate, 2, 0);

        return view('agent.packages.book', [
            'seo' => ['title' => 'Book: '.$package->name],
            'agent' => $agent,
            'package' => $package,
            'departureDate' => $departureDate,
            'quote' => $quote,
        ]);
    }

    /** Compute the public + agent pricing for a package selection. */
    protected function quoteFor($agent, Package $package, string $date, int $adults, int $children): array
    {
        $adultPrice = $package->effectivePrice($date);
        $childPrice = (float) ($package->child_price ?? $adultPrice * 0.6);
        $supplierCost = round($adultPrice * $adults + $childPrice * $children, 2);

        $pricing = $this->pricing->calculate($supplierCost, 'package');
        $agentPricing = $this->agentBooking->computeAgentPricing($agent, (float) $pricing['total']);

        return [
            'adult_price' => $adultPrice,
            'child_price' => $childPrice,
            'adults' => $adults,
            'children' => $children,
            'pricing' => $pricing,
            'agent' => $agentPricing,
        ];
    }

    public function store(Request $request, Package $package)
    {
        if ($package->status !== 'active') {
            abort(404);
        }

        $agent = auth('agent')->user();

        $validated = $request->validate([
            'departure_date' => 'required|date|after_or_equal:today',
            'adults' => 'required|integer|min:1|max:'.$package->max_travellers,
            'children' => 'nullable|integer|min:0|max:10',
            'rooms' => 'nullable|integer|min:1|max:6',
            'lead_name' => 'required|string|max:120',
            'lead_phone' => 'required|string|max:25',
            'lead_email' => 'nullable|email|max:150',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        $adults = (int) $validated['adults'];
        $children = (int) ($validated['children'] ?? 0);
        $rooms = (int) ($validated['rooms'] ?? max(1, (int) ceil($adults / 2)));

        if ($adults + $children > $package->max_travellers) {
            return back()->withInput()->with('error', "This package allows a maximum of {$package->max_travellers} travellers per booking.");
        }

        $quote = $this->quoteFor($agent, $package, $validated['departure_date'], $adults, $children);
        $netPayable = $quote['agent']['net_payable'];
        $commission = $quote['agent']['commission'];

        if (! $this->agentBooking->canAfford($agent, $netPayable)) {
            return back()->withInput()->with('error', 'Insufficient wallet balance and credit to complete this booking. Available: '.money($agent->totalPurchasingPower(), true).', required: '.money($netPayable, true).'.');
        }

        try {
            $booking = $this->bookings->create([
                'user_id' => null,
                'agent_id' => $agent->id,
                'agent_commission' => $commission,
                'product_type' => 'package',
                'product_id' => $package->id,
                'pricing' => $quote['pricing'],
                'items' => [
                    [
                        'item_type' => 'package_adult',
                        'name' => $package->name.' · Adult',
                        'quantity' => $adults,
                        'unit_price' => $quote['adult_price'],
                        'total_price' => round($quote['adult_price'] * $adults, 2),
                    ],
                    [
                        'item_type' => 'package_child',
                        'name' => $package->name.' · Child',
                        'quantity' => $children,
                        'unit_price' => $quote['child_price'],
                        'total_price' => round($quote['child_price'] * $children, 2),
                    ],
                ],
                'contact' => [
                    'full_name' => $validated['lead_name'],
                    'first_name' => strtok($validated['lead_name'], ' ') ?: $validated['lead_name'],
                    'last_name' => str_contains($validated['lead_name'], ' ') ? trim(substr($validated['lead_name'], strpos($validated['lead_name'], ' '))) : '',
                    'email' => $validated['lead_email'] ?? $agent->email,
                    'phone' => $validated['lead_phone'],
                    'booked_by_agent' => $agent->agency_code,
                ],
                'notes' => $validated['special_requests'] ?? null,
                'package' => [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'departure_date' => $validated['departure_date'],
                    'adults' => $adults,
                    'children' => $children,
                    'rooms' => $rooms,
                    'price_breakdown' => [
                        'adult_price' => $quote['adult_price'],
                        'child_price' => $quote['child_price'],
                        'adults' => $adults,
                        'children' => $children,
                        'public_total' => $quote['agent']['public_total'],
                        'agent_commission' => $commission,
                        'agent_net_payable' => $netPayable,
                    ],
                ],
            ]);

            // Debit the agent's wallet/credit and confirm the booking.
            $this->agentBooking->debitForBooking($agent, $booking, $netPayable, $commission);

            $booking->update(['status' => 'confirmed', 'booked_at' => now()]);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('agent.bookings.show', $booking)->with('success', 'Booking confirmed! Reference '.$booking->booking_reference);
    }

    public function index(Request $request)
    {
        $agent = auth('agent')->user();

        $query = $agent->bookings()->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return view('agent.bookings.index', [
            'seo' => ['title' => 'My Bookings'],
            'agent' => $agent,
            'bookings' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function show(\App\Models\Booking $booking)
    {
        $agent = auth('agent')->user();

        abort_unless($booking->agent_id === $agent->id, 403);

        $booking->load(['items', 'packageBooking']);

        return view('agent.bookings.show', [
            'seo' => ['title' => 'Booking '.$booking->booking_reference],
            'agent' => $agent,
            'booking' => $booking,
        ]);
    }

    /** Commission report grouped by month. */
    public function commission(Request $request)
    {
        $agent = auth('agent')->user();

        $bookings = $agent->bookings()
            ->where('status', 'confirmed')
            ->latest()
            ->get();

        $totalCommission = (float) $bookings->sum('agent_commission');
        $totalBusiness = (float) $bookings->sum('total_amount');

        $byMonth = $bookings->groupBy(fn ($b) => $b->created_at->format('Y-m'))
            ->map(fn ($group) => [
                'month' => $group->first()->created_at->format('M Y'),
                'count' => $group->count(),
                'business' => (float) $group->sum('total_amount'),
                'commission' => (float) $group->sum('agent_commission'),
            ])
            ->values();

        return view('agent.commission', [
            'seo' => ['title' => 'Commission Report'],
            'agent' => $agent,
            'bookings' => $bookings,
            'totalCommission' => $totalCommission,
            'totalBusiness' => $totalBusiness,
            'byMonth' => $byMonth,
        ]);
    }
}
