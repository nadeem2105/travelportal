@extends('layouts.admin')
@section('pageTitle', 'Booking ' . $booking->booking_reference)

@section('content')
    <a href="{{ route('admin.bookings.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Bookings</a>

    @if (session('success'))
        <div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('checkout_link'))
        <div class="mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm">
            <span class="font-semibold text-brand-800">Customer checkout link:</span>
            <input type="text" readonly value="{{ session('checkout_link') }}"
                   class="min-w-0 flex-1 rounded border bg-white px-2 py-1 font-mono text-xs text-ink-600" onclick="this.select()">
            <button type="button" class="btn-ghost btn-xs text-brand-700"
                    onclick="navigator.clipboard.writeText('{{ session('checkout_link') }}');this.textContent='✓ Copied';setTimeout(()=>this.textContent='🔗 Copy',1500)">🔗 Copy</button>
        </div>
    @endif

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-xl font-bold">{{ $booking->booking_reference }}</h1>
            <p class="text-sm capitalize text-ink-500">{{ $booking->product_type }} · Created {{ $booking->created_at->format('d M Y, h:i A') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (auth('admin')->user()?->can('edit_booking') || auth('admin')->user()?->is_super_admin)
                <form action="{{ route('admin.bookings.status', $booking) }}" method="POST" class="inline-flex items-center">
                    @csrf
                    <div class="relative inline-flex items-center">
                        <select name="status" class="status-pill {{ status_pill_class($booking->status) }} appearance-none pr-7 font-bold cursor-pointer border-0 ring-1 ring-black/10 focus:ring-2 focus:ring-brand-600" onchange="this.form.submit()" title="Click to change booking status immediately">
                            @foreach (\App\Models\Booking::STATUSES as $status)
                                <option value="{{ $status }}" @selected($booking->status === $status)>● {{ label_case($status) }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute right-2 text-current opacity-70">▾</span>
                    </div>
                </form>
            @else
                <span class="status-pill {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span>
            @endif
            <a href="{{ route('admin.bookings.invoice', $booking) }}" target="_blank" class="btn-ghost btn-sm inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Invoice
            </a>
            @if ($booking->contact['email'] ?? $booking->user?->email)
                <form action="{{ route('admin.bookings.resend-email', $booking) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm inline-flex items-center gap-1 text-ink-700 hover:text-brand-700" title="Resend confirmation email to {{ $booking->contact['email'] ?? $booking->user?->email }}">
                        <svg class="h-3.5 w-3.5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                        Resend Email
                    </button>
                </form>
            @endif
            @if ($booking->contact['phone'] ?? $booking->user?->phone)
                <form action="{{ route('admin.bookings.send-whatsapp-invoice', $booking) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm inline-flex items-center gap-1 text-emerald-700 hover:text-emerald-800" title="Send official tax invoice PDF to customer's WhatsApp ({{ $booking->contact['phone'] ?? $booking->user?->phone }})">
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.974-.94 5.952 5.952 0 0 1 1.45-2.227C4.697 16.32 4.05 14.28 4.05 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                        WhatsApp Invoice
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.bookings.itinerary', $booking) }}" target="_blank" class="btn-primary btn-sm inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
                @if ($booking->product_type === 'package') Tour Itinerary
                @elseif ($booking->product_type === 'flight') E-Ticket
                @elseif ($booking->product_type === 'hotel') Hotel Voucher
                @elseif ($booking->product_type === 'cab') Cab Voucher
                @else Itinerary
                @endif
            </a>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Items --}}
            <div class="admin-card">
                <h2 class="font-display text-base font-bold">Booking Items</h2>
                <table class="admin-table mt-2">
                    <thead><tr><th>Item</th><th>Qty</th><th class="text-right">Unit</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($booking->items as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td class="text-right">{{ money($item->unit_price) }}</td>
                                <td class="text-right font-bold">{{ money($item->total_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($booking->flight)
                    <div class="divider my-4"></div>
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="font-bold text-ink-900 mb-1">Flight Journey Details</p>
                        <p><strong>PNR:</strong> {{ $booking->flight->pnr ?? '—' }} · <strong>Ticket:</strong> {{ $booking->flight->ticket_number ?? '—' }} · <strong>Flight Number:</strong> {{ $booking->flight->flight_number ?? '—' }} · <strong>Supplier Ref:</strong> {{ $booking->supplier_booking_id ?? '—' }}</p>
                    </div>
                @elseif ($booking->hotelBooking)
                    <div class="divider my-4"></div>
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="font-bold text-ink-900 mb-1">Hotel Reservation Details</p>
                        <p><strong>Hotel:</strong> {{ $booking->hotelBooking->hotel_name }} · <strong>Room:</strong> {{ $booking->hotelBooking->room_type }} · <strong>Meal Plan:</strong> {{ ucfirst($booking->hotelBooking->meal_plan ?? 'EP') }}</p>
                        <p class="mt-1 text-xs text-ink-500"><strong>Check-in:</strong> {{ $booking->hotelBooking->check_in?->format('d M Y') }} · <strong>Check-out:</strong> {{ $booking->hotelBooking->check_out?->format('d M Y') }} ({{ $booking->hotelBooking->nights }} Nights, {{ $booking->hotelBooking->rooms }} Rooms)</p>
                    </div>
                @elseif ($booking->cab)
                    <div class="divider my-4"></div>
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="font-bold text-ink-900 mb-1">Cab Transfer Details</p>
                        <p><strong>Vehicle:</strong> {{ $booking->cab->vehicle_name }} · <strong>Trip Type:</strong> {{ label_case($booking->cab->trip_type) }} · <strong>Distance:</strong> {{ $booking->cab->distance_km }} km</p>
                        <p class="mt-1 text-xs text-ink-500"><strong>Pickup:</strong> {{ $booking->cab->pickup_location }} · <strong>Drop:</strong> {{ $booking->cab->drop_location }} · <strong>Date &amp; Time:</strong> {{ $booking->cab->pickup_datetime?->format('d M Y, h:i A') }}</p>
                    </div>
                @elseif ($booking->packageBooking)
                    <div class="divider my-4"></div>
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="font-bold text-ink-900 mb-1">Package Tour Details</p>
                        <p><strong>Package:</strong> {{ $booking->packageBooking->package_name }} · <strong>Departure:</strong> {{ $booking->packageBooking->departure_date?->format('d M Y') }}</p>
                        <p class="mt-1 text-xs text-ink-500"><strong>Guests:</strong> {{ $booking->packageBooking->adults }} Adults, {{ $booking->packageBooking->children }} Children</p>
                    </div>
                @endif
            </div>

            {{-- Hotel (package bookings) --}}
            @if ($booking->product_type === 'package' && $booking->bookingHotels->count())
                <div class="admin-card">
                    <h2 class="font-display text-base font-bold">Hotel</h2>
                    <table class="admin-table mt-2">
                        <thead><tr><th>Hotel</th><th>Room / Meal</th><th>Check-in / out</th><th>Guests</th><th>Supplier</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($booking->bookingHotels as $hotel)
                                <tr>
                                    <td>
                                        <strong>{{ $hotel->hotel_name_snapshot }}</strong>
                                        @if ($hotel->star_rating_snapshot) <span class="text-amber-500">{{ str_repeat('★', (int) $hotel->star_rating_snapshot) }}</span>@endif
                                        @if ($hotel->segment_label)<br><span class="text-xs text-ink-500">{{ $hotel->segment_label }}</span>@endif
                                    </td>
                                    <td>{{ $hotel->room_name_snapshot ?: '—' }}<br><span class="text-xs text-ink-500">{{ $hotel->mealPlanLabel() }}</span></td>
                                    <td class="text-xs">{{ optional($hotel->check_in)->format('d M Y') ?? '—' }}<br>{{ optional($hotel->check_out)->format('d M Y') ?? '—' }} ({{ $hotel->nights }}N)</td>
                                    <td class="text-xs">{{ $hotel->guestSummary() }}<br>{{ $hotel->rooms }} room(s)</td>
                                    <td class="text-xs">{{ $hotel->supplier_id ? 'Supplier #' . $hotel->supplier_id : 'Manual' }}<br>{{ $hotel->supplier_booking_id ?: '—' }}</td>
                                    <td class="text-right font-bold">{{ $hotel->is_included ? 'Included' : money($hotel->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Flights (package bookings) --}}
            @if ($booking->product_type === 'package' && $booking->packageFlights->count())
                <div class="admin-card">
                    <h2 class="font-display text-base font-bold">Flights</h2>
                    <table class="admin-table mt-2">
                        <thead><tr><th>Flight</th><th>Route</th><th>Cabin / Trip</th><th>Pax</th><th>Supplier</th><th class="text-right">Fare</th></tr></thead>
                        <tbody>
                            @foreach ($booking->packageFlights as $pf)
                                <tr>
                                    <td><strong>{{ $pf->label_snapshot }}</strong>@if ($pf->airline_snapshot)<br><span class="text-xs text-ink-500">{{ $pf->airline_snapshot }}</span>@endif</td>
                                    <td class="text-xs">{{ $pf->routeLabel() }}</td>
                                    <td class="text-xs">{{ $pf->cabinLabel() }}<br>{{ ucfirst(str_replace('_',' ',$pf->trip_type)) }}</td>
                                    <td class="text-xs">{{ $pf->travellers }}</td>
                                    <td class="text-xs">{{ $pf->supplier_id ? 'Supplier #' . $pf->supplier_id : 'Configured' }}<br>{{ $pf->supplier_booking_id ?: '—' }}</td>
                                    <td class="text-right font-bold">{{ money($pf->price) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Travellers --}}
            @if ($booking->travellers->count())
                <div class="admin-card">
                    <h2 class="font-display text-base font-bold">Travellers</h2>
                    <table class="admin-table mt-2">
                        <thead><tr><th>Name</th><th>Type</th><th>Contact</th></tr></thead>
                        <tbody>
                            @foreach ($booking->travellers as $traveller)
                                <tr>
                                    <td>{{ $traveller->full_name }}</td>
                                    <td class="capitalize">{{ $traveller->traveller_type }}</td>
                                    <td class="text-xs text-ink-500">{{ $booking->contact['email'] ?? '' }} {{ $booking->contact['phone'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Payments --}}
            <div class="admin-card">
                <h2 class="font-display text-base font-bold">Payments</h2>
                <table class="admin-table mt-2">
                    <thead><tr><th>Gateway</th><th>Order ID</th><th>Payment ID</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @forelse ($booking->payments as $payment)
                            <tr>
                                <td class="capitalize">{{ $payment->gateway }}</td>
                                <td class="text-xs">{{ $payment->gateway_order_id }}</td>
                                <td class="text-xs">{{ $payment->gateway_payment_id ?? '—' }}</td>
                                <td><span class="status-pill {{ status_pill_class($payment->status) }}">{{ ucfirst($payment->status) }}</span></td>
                                <td class="text-right font-bold">{{ money($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-ink-500">No payments</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Refunds --}}
            @if ($booking->refunds->count())
                <div class="admin-card">
                    <h2 class="font-display text-base font-bold">Refunds</h2>
                    <div class="mt-2 space-y-3">
                        @foreach ($booking->refunds as $refund)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 p-3">
                                <div>
                                    <p class="text-sm font-bold">{{ money($refund->amount) }} {{ $refund->penalty_amount ? '(penalty ' . money($refund->penalty_amount) . ')' : '' }}</p>
                                    <p class="text-xs text-ink-500">{{ $refund->reason }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="status-pill {{ status_pill_class($refund->status) }}">{{ ucfirst($refund->status) }}</span>
                                    @if ($refund->status === 'initiated' && auth('admin')->user()->can('refund_booking'))
                                        <form action="{{ route('admin.refunds.process', $refund) }}" method="POST">
                                            @csrf
                                            <button class="btn-primary btn-sm">Process Refund</button>
                                        </form>
                                        <form action="{{ route('admin.refunds.reject', $refund) }}" method="POST">
                                            @csrf
                                            <button class="btn-ghost btn-sm !text-rose-600">Reject</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Side actions --}}
        <div class="space-y-4">
            <div class="admin-card">
                <h2 class="font-display text-base font-bold">Price Summary</h2>
                <div class="mt-2 space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Supplier Cost</span><span>{{ money($booking->supplier_cost) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">Markup</span><span>{{ money($booking->markup_amount) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">Taxes</span><span>{{ money($booking->tax_amount) }}</span></div>
                    @if ($booking->discount_amount > 0)
                        <div class="flex justify-between text-emerald-600"><span>Discount</span><span>-{{ money($booking->discount_amount) }}</span></div>
                    @endif
                    <div class="divider"></div>
                    <div class="flex justify-between font-extrabold"><span>Total</span><span>{{ money($booking->total_amount) }}</span></div>
                    <div class="flex justify-between text-xs text-ink-500"><span>Commission earned</span><span>{{ money($booking->commission_amount) }}</span></div>
                </div>
            </div>

            @if (auth('admin')->user()?->can('edit_booking') || auth('admin')->user()?->is_super_admin)
                <div class="admin-card border-l-4 border-l-brand-600">
                    <h2 class="font-display text-base font-bold flex items-center justify-between">
                        <span>Booking Status</span>
                        <span class="status-pill {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span>
                    </h2>
                    <form action="{{ route('admin.bookings.status', $booking) }}" method="POST" class="mt-3 space-y-2.5">
                        @csrf
                        <div>
                            <label class="label">Change Status To</label>
                            <select name="status" class="input font-semibold">
                                @foreach (\App\Models\Booking::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($booking->status === $status)>
                                        {{ label_case($status) }} @if($booking->status === $status) (Current) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn-primary btn-md w-full">Update Status</button>
                    </form>
                </div>
            @endif

            @if ($booking->product_type === 'cab')
                <div class="admin-card border-l-4 border-l-amber-500">
                    <h2 class="font-display text-base font-bold flex items-center justify-between">
                        <span>Cab Driver Assignment</span>
                        @if (!empty($booking->cab?->driver_details['driver_name']))
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">Assigned</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">Pending Assignment</span>
                        @endif
                    </h2>

                    @if (!empty($booking->cab?->driver_details['driver_name']))
                        <div class="mt-3 rounded-lg bg-slate-50 p-2.5 text-xs space-y-1">
                            <p><strong>Driver:</strong> {{ $booking->cab->driver_details['driver_name'] }}</p>
                            <p><strong>Phone:</strong> <a href="tel:{{ $booking->cab->driver_details['driver_phone'] }}" class="text-brand-600 font-bold">{{ $booking->cab->driver_details['driver_phone'] }}</a></p>
                            <p><strong>Vehicle No:</strong> {{ $booking->cab->driver_details['vehicle_number'] }}</p>
                            @if (!empty($booking->cab->driver_details['vendor_name']))
                                <p><strong>Vendor / Fleet:</strong> {{ $booking->cab->driver_details['vendor_name'] }}</p>
                            @endif
                        </div>
                    @endif

                    @if (auth('admin')->user()?->can('edit_booking') || auth('admin')->user()?->is_super_admin)
                        <form action="{{ route('admin.bookings.assign-driver', $booking) }}" method="POST" class="mt-3 space-y-2">
                            @csrf
                            <div>
                                <label class="label text-xs">Driver Name</label>
                                <input type="text" name="driver_name" class="input input-sm" value="{{ $booking->cab?->driver_details['driver_name'] ?? '' }}" required placeholder="e.g. Bashir Ahmad">
                            </div>
                            <div>
                                <label class="label text-xs">Driver Phone</label>
                                <input type="text" name="driver_phone" class="input input-sm" value="{{ $booking->cab?->driver_details['driver_phone'] ?? '' }}" required placeholder="e.g. 9906000000">
                            </div>
                            <div>
                                <label class="label text-xs">Vehicle Plate Number</label>
                                <input type="text" name="vehicle_number" class="input input-sm" value="{{ $booking->cab?->driver_details['vehicle_number'] ?? '' }}" required placeholder="e.g. JK-01-AB-1234">
                            </div>
                            <div>
                                <label class="label text-xs">Vendor / Fleet Partner</label>
                                <input type="text" name="vendor_name" class="input input-sm" value="{{ $booking->cab?->driver_details['vendor_name'] ?? '' }}" placeholder="e.g. Kashmir Valley Cabs">
                            </div>
                            <button class="btn-primary btn-sm w-full">Save Driver Details</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ((auth('admin')->user()?->can('cancel_booking') || auth('admin')->user()?->is_super_admin) && $booking->isCancellable())
                    <div class="admin-card">
                        <h2 class="font-display text-base font-bold">Cancel Booking</h2>
                        <form action="{{ route('admin.bookings.cancel', $booking) }}" method="POST" class="mt-2 space-y-2">
                            @csrf
                            <div>
                                <label class="label">Penalty %</label>
                                <input type="number" name="penalty_percent" class="input" value="10" min="0" max="100" required>
                            </div>
                            <div>
                                <label class="label">Reason</label>
                                <textarea name="reason" rows="2" class="input"></textarea>
                            </div>
                            <button class="btn-danger btn-md w-full">Cancel & Initiate Refund</button>
                        </form>
                    </div>
                @endif

            <div class="admin-card">
                <h2 class="font-display text-base font-bold">Internal Notes</h2>
                <form action="{{ route('admin.bookings.notes', $booking) }}" method="POST" class="mt-2 space-y-2">
                    @csrf
                    <textarea name="admin_notes" rows="4" class="input" placeholder="Internal notes (not visible to customer)">{{ $booking->admin_notes }}</textarea>
                    <button class="btn-ghost btn-md w-full">Save Notes</button>
                </form>
            </div>
        </div>
    </div>
@endsection
