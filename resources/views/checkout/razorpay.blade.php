@extends('layouts.site')
@push('scripts')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endpush

@section('page')
<section class="shell max-w-lg py-20 text-center">
    <div class="card p-10">
        <h1 class="font-display text-xl font-bold">Redirecting to Secure Payment…</h1>
        <p class="mt-2 text-sm text-ink-500">Order: {{ $order['order_id'] }} · Amount: ₹{{ number_format($order['amount'] / 100, 2) }}</p>
        <div class="mt-6 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full w-1/3 animate-pulse rounded-full bg-brand-600"></div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    const rzp = new Razorpay({
        key: @json($order['key_id']),
        amount: @json($order['amount']),
        currency: @json($order['currency']),
        name: @json($order['name']),
        description: @json($order['description']),
        order_id: @json($order['order_id']),
        prefill: @json($order['prefill'] ?? []),
        theme: @json($order['theme'] ?? ['color' => '#2563eb']),
        handler: function (response) {
            if (window.__paymentSubmitted) return; // guard against double submission
            window.__paymentSubmitted = true;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @json(route('payments.callback'));
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="razorpay_order_id" value="${response.razorpay_order_id}">
                <input type="hidden" name="razorpay_payment_id" value="${response.razorpay_payment_id}">
                <input type="hidden" name="razorpay_signature" value="${response.razorpay_signature}">
            `;
            document.body.appendChild(form);
            form.submit();
        },
        modal: {
            ondismiss: function () {
                window.location = @json(route('checkout.show', ['booking' => $booking->booking_reference]));
            },
        },
    });
    rzp.open();
</script>
@endpush
