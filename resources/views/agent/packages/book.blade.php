@extends('layouts.site')

@section('page')
<x-agent.shell agentNav="packages">
    <div class="mb-6">
        <a href="{{ route('agent.packages') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Back to packages</a>
        <h1 class="mt-2 font-display text-2xl font-bold">Book: {{ $package->name }}</h1>
        <p class="mt-1 text-sm text-ink-500">{{ $package->destination->name ?? '' }} · {{ $package->duration_days }} Days</p>
    </div>

    <form action="{{ route('agent.packages.store', $package) }}" method="POST"
          x-data="agentBook({
              adultPrice: {{ $quote['adult_price'] }},
              childPrice: {{ $quote['child_price'] }},
              commissionRate: {{ (float) $agent->commission_rate }},
              serviceTaxFactor: {{ $quote['pricing']['supplier_cost'] > 0 ? round($quote['pricing']['total'] / $quote['pricing']['supplier_cost'], 6) : 1 }},
          })">
        @csrf
        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="space-y-6">
                <div class="card p-6">
                    <h2 class="mb-4 font-display text-lg font-bold">Travel details</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Departure date</label>
                            <input type="date" name="departure_date" class="input" value="{{ old('departure_date', $departureDate) }}" min="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div>
                            <label class="label">Rooms</label>
                            <input type="number" name="rooms" class="input" value="{{ old('rooms', 1) }}" min="1" max="6">
                        </div>
                        <div>
                            <label class="label">Adults</label>
                            <input type="number" name="adults" class="input" x-model.number="adults" min="1" max="{{ $package->max_travellers }}" required>
                        </div>
                        <div>
                            <label class="label">Children</label>
                            <input type="number" name="children" class="input" x-model.number="children" min="0" max="10">
                        </div>
                    </div>
                </div>

                <div class="card p-6">
                    <h2 class="mb-4 font-display text-lg font-bold">Lead traveller / customer</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="lead_name" class="input" value="{{ old('lead_name') }}" required>
                        </div>
                        <div>
                            <label class="label">Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="lead_phone" class="input" value="{{ old('lead_phone') }}" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Email</label>
                            <input type="email" name="lead_email" class="input" value="{{ old('lead_email') }}">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Special requests</label>
                            <textarea name="special_requests" rows="2" class="input">{{ old('special_requests') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Price summary --}}
            <div class="h-fit lg:sticky lg:top-24">
                <div class="card p-6">
                    <h2 class="mb-4 font-display text-lg font-bold">Price summary</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between text-ink-600">
                            <dt>Adults × <span x-text="adults"></span></dt>
                            <dd x-text="fmt(adultPrice * adults)"></dd>
                        </div>
                        <div class="flex justify-between text-ink-600" x-show="children > 0">
                            <dt>Children × <span x-text="children"></span></dt>
                            <dd x-text="fmt(childPrice * children)"></dd>
                        </div>
                        <div class="flex justify-between border-t border-ink-100 pt-2 text-ink-600">
                            <dt>Public price (incl. taxes)</dt>
                            <dd x-text="fmt(publicTotal())"></dd>
                        </div>
                        <div class="flex justify-between text-emerald-600">
                            <dt>Your commission ({{ rtrim(rtrim(number_format($agent->commission_rate, 2), '0'), '.') }}%)</dt>
                            <dd>− <span x-text="fmt(commission())"></span></dd>
                        </div>
                        <div class="flex justify-between border-t border-ink-100 pt-3 text-base font-bold text-ink-900">
                            <dt>You pay (net)</dt>
                            <dd x-text="fmt(netPayable())"></dd>
                        </div>
                    </dl>

                    <div class="mt-4 rounded-lg bg-ink-50 p-3 text-xs text-ink-500">
                        Wallet: {{ money($agent->wallet_balance, true) }} · Available credit: {{ money($agent->availableCredit(), true) }}
                    </div>

                    <button class="btn-primary btn-lg mt-4 w-full">Confirm &amp; Pay from Wallet</button>
                    <p class="mt-2 text-center text-xs text-ink-400">Net amount will be debited from your wallet / credit line.</p>
                </div>
            </div>
        </div>
    </form>
</x-agent.shell>

<script>
    function agentBook(cfg) {
        return {
            adults: {{ old('adults', 2) }},
            children: {{ old('children', 0) }},
            adultPrice: cfg.adultPrice,
            childPrice: cfg.childPrice,
            commissionRate: cfg.commissionRate,
            serviceTaxFactor: cfg.serviceTaxFactor,
            supplierCost() {
                return (this.adultPrice * (this.adults || 0)) + (this.childPrice * (this.children || 0));
            },
            publicTotal() {
                // Approximate taxes/fees using the ratio from the initial server quote.
                return Math.round(this.supplierCost() * this.serviceTaxFactor * 100) / 100;
            },
            commission() {
                return Math.round(this.publicTotal() * (this.commissionRate / 100) * 100) / 100;
            },
            netPayable() {
                return Math.round((this.publicTotal() - this.commission()) * 100) / 100;
            },
            fmt(v) {
                return '₹' + Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
            },
        };
    }
</script>
@endsection
