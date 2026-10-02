{{--
    Cookie consent banner. Nothing non-essential (GA4/GTM/Meta Pixel/Google Ads)
    loads until the visitor chooses here — see resources/js/analytics.js, which
    reads the same lz_consent store and only loads tags after consent. Reopen
    anytime via window.lzOpenConsent() (wired to the footer "Cookie settings" link).
--}}
<div
    x-data="lzConsent()"
    x-show="open"
    x-cloak
    x-transition.opacity
    class="fixed inset-x-0 bottom-0 z-[60] p-3 sm:p-4"
    role="dialog"
    aria-label="Cookie consent"
>
    <div class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl sm:p-5">
        <template x-if="!showPrefs">
            <div class="sm:flex sm:items-center sm:gap-4">
                <div class="text-sm text-slate-600">
                    <p class="font-semibold text-slate-900">We value your privacy</p>
                    <p class="mt-1">
                        We use cookies to run the site, understand traffic, and improve your experience.
                        You can accept all, reject non-essential, or choose what to allow.
                        See our
                        <a href="{{ route('cookie-policy') }}" class="text-brand-600 underline">Cookie Policy</a>
                        and
                        <a href="{{ route('privacy-policy') }}" class="text-brand-600 underline">Privacy Policy</a>.
                    </p>
                </div>
                <div class="mt-3 flex shrink-0 flex-wrap gap-2 sm:mt-0">
                    <button type="button" @click="reject()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reject non-essential</button>
                    <button type="button" @click="showPrefs = true" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Customize</button>
                    <button type="button" @click="acceptAll()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Accept all</button>
                </div>
            </div>
        </template>

        <template x-if="showPrefs">
            <div>
                <p class="text-sm font-semibold text-slate-900">Cookie preferences</p>
                <div class="mt-3 space-y-2.5 text-sm">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" checked disabled class="mt-0.5 rounded">
                        <span><span class="font-medium text-slate-800">Necessary</span><br><span class="text-slate-500">Required for the site to function. Always on.</span></span>
                    </label>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" x-model="prefs.functional" class="mt-0.5 rounded">
                        <span><span class="font-medium text-slate-800">Functional</span><br><span class="text-slate-500">Remembers preferences to personalize your experience.</span></span>
                    </label>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" x-model="prefs.analytics" class="mt-0.5 rounded">
                        <span><span class="font-medium text-slate-800">Analytics</span><br><span class="text-slate-500">Helps us understand traffic and improve the site (GA4).</span></span>
                    </label>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" x-model="prefs.marketing" class="mt-0.5 rounded">
                        <span><span class="font-medium text-slate-800">Marketing</span><br><span class="text-slate-500">Measures ad performance (Meta Pixel, Google Ads).</span></span>
                    </label>
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <button type="button" @click="showPrefs = false" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</button>
                    <button type="button" @click="save()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save preferences</button>
                </div>
            </div>
        </template>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function lzConsent() {
            return {
                open: false,
                showPrefs: false,
                prefs: { functional: false, analytics: false, marketing: false },
                init() {
                    const c = (window.analytics && window.analytics.getConsent) ? window.analytics.getConsent() : { set: false };
                    this.open = !c.set;
                    this.prefs = { functional: !!c.functional, analytics: !!c.analytics, marketing: !!c.marketing };
                    window.lzOpenConsent = () => { this.showPrefs = true; this.open = true; };
                },
                acceptAll() { this.apply({ functional: true, analytics: true, marketing: true }); },
                reject() { this.apply({ functional: false, analytics: false, marketing: false }); },
                save() { this.apply(this.prefs); },
                apply(p) {
                    if (window.analytics && window.analytics.setConsent) window.analytics.setConsent(p);
                    this.open = false; this.showPrefs = false;
                },
            };
        }
    </script>
    @endpush
@endonce
