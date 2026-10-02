{{--
  Global lead-capture modal. Non-intrusive: never opens on page load — only when
  something dispatches the `open-lead-modal` window event, optionally with detail:
    $dispatch('open-lead-modal', { source: 'package_detail', destination: 'Kashmir', service: 'Honeymoon' })
  Submits via AJAX to leads.capture (LeadService handles dedup/attribution/assignment/scoring).
  Attribution (UTM/gclid/fbclid/landing) is captured server-side in the session, so no hidden fields are needed here.
--}}
<div
    x-data="leadModal()"
    x-show="open"
    x-cloak
    @open-lead-modal.window="show($event.detail || {})"
    @keydown.escape.window="open = false"
    class="fixed inset-0 z-[80] flex items-end justify-center sm:items-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="lead-modal-title"
>
    <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm" @click="open = false"></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        class="relative z-10 w-full max-w-lg rounded-t-3xl bg-white shadow-float sm:rounded-3xl"
    >
        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-5">
            <div>
                <h2 id="lead-modal-title" class="font-display text-lg font-bold text-ink-900" x-text="title"></h2>
                <p class="text-xs text-ink-500">Share a few details and our travel expert will call you back.</p>
            </div>
            <button type="button" @click="open = false" class="rounded-full p-1 text-ink-400 hover:bg-slate-100 hover:text-ink-700" aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Success state --}}
        <div x-show="done" class="p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            </div>
            <h3 class="mt-4 font-display text-lg font-bold text-ink-900">Thank you!</h3>
            <p class="mt-1 text-sm text-ink-500" x-text="message"></p>
            <button type="button" @click="open = false" class="btn-primary btn-md mt-5">Done</button>
        </div>

        {{-- Form --}}
        <form x-show="!done" @submit.prevent="submit" class="space-y-3 p-5">
            {{-- Honeypot: keep empty; bots fill it --}}
            <input type="text" name="company_website" x-model="form.company_website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="lead-name">Full Name</label>
                    <input id="lead-name" type="text" x-model="form.name" class="input" required>
                </div>
                <div>
                    <label class="label" for="lead-phone">WhatsApp / Mobile</label>
                    <input id="lead-phone" type="tel" x-model="form.phone" class="input" required>
                </div>
                <div>
                    <label class="label" for="lead-destination">Destination</label>
                    <input id="lead-destination" type="text" x-model="form.destination" class="input" placeholder="e.g. Kashmir">
                </div>
                <div>
                    <label class="label" for="lead-date">Travel Date</label>
                    <input id="lead-date" type="date" x-model="form.travel_date" class="input">
                </div>
                <div>
                    <label class="label" for="lead-adults">Travellers</label>
                    <select id="lead-adults" x-model="form.adults" class="input">
                        <template x-for="n in 10" :key="n"><option :value="n" x-text="n + (n === 1 ? ' traveller' : ' travellers')"></option></template>
                    </select>
                </div>
                <div>
                    <label class="label" for="lead-service">Trip Type</label>
                    <select id="lead-service" x-model="form.service_type" class="input">
                        <option value="">Any</option>
                        <option>Honeymoon</option>
                        <option>Family</option>
                        <option>Group</option>
                        <option>Adventure</option>
                        <option>Solo</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="label" for="lead-message">Message <span class="text-ink-400">(optional)</span></label>
                <textarea id="lead-message" x-model="form.message" rows="2" class="input" placeholder="Budget, preferences, anything else…"></textarea>
            </div>

            <label class="flex items-center gap-2 text-xs text-ink-500">
                <input type="checkbox" x-model="form.whatsapp_opt_in" class="rounded border-slate-300">
                Contact me on WhatsApp
            </label>

            <p x-show="error" x-text="error" class="text-sm text-rose-600" x-cloak></p>

            <button type="submit" class="btn-primary btn-lg w-full" :disabled="loading">
                <span x-show="!loading">Get Free Quote</span>
                <span x-show="loading" x-cloak>Sending…</span>
            </button>
            <p class="text-center text-[11px] text-ink-400">No spam. We only use your details to plan your trip.</p>
        </form>
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('leadModal', () => ({
        open: false,
        loading: false,
        done: false,
        error: '',
        title: 'Plan My Trip',
        message: '',
        form: {
            name: '', phone: '', email: '', destination: '', travel_date: '',
            adults: 2, service_type: '', message: '', whatsapp_opt_in: true,
            source_slug: 'website', company_website: '',
        },
        show(detail) {
            this.done = false; this.error = '';
            this.title = detail.title || 'Plan My Trip';
            if (detail.destination) this.form.destination = detail.destination;
            if (detail.service) this.form.service_type = detail.service;
            if (detail.source) this.form.source_slug = detail.source;
            if (detail.product_type) this.form.product_type = detail.product_type;
            this.open = true;
        },
        async submit() {
            this.loading = true; this.error = '';
            try {
                const { data } = await window.axios.post(@json(route('leads.capture')), this.form, {
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                });
                if (data && data.ok) {
                    this.done = true;
                    this.message = data.message || 'Our travel expert will contact you shortly.';
                    if (window.dataLayer) window.dataLayer.push({ event: 'Lead', lead_number: data.lead_number, source: this.form.source_slug });
                    if (window.fbq) window.fbq('track', 'Lead');
                } else {
                    this.error = (data && data.message) || 'Could not submit. Please try again.';
                }
            } catch (e) {
                this.error = e?.response?.data?.message || 'Could not submit. Please try again.';
            } finally {
                this.loading = false;
            }
        },
    }));
});
</script>
@endpush
@endonce
