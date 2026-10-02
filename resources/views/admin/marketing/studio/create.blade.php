@extends('layouts.admin')
@section('pageTitle', 'Create Creative')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-display text-xl font-bold">Create Creative</h1>
            <p class="text-xs text-ink-500">Pick a source and we'll pull the real price, dates &amp; details automatically.</p>
        </div>
        <a href="{{ route('admin.studio.index') }}" class="btn-ghost btn-sm">Cancel</a>
    </div>

    @if ($errors->any())<div class="alert-error mt-3">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.studio.store') }}" class="mt-4"
          x-data="studioWizard()" x-init="init()">
        @csrf

        {{-- Step indicator --}}
        <div class="mb-4 flex items-center gap-2 text-xs font-semibold">
            <template x-for="(label, i) in steps" :key="i">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full"
                          :class="step >= i ? 'bg-brand-600 text-white' : 'bg-slate-100 text-ink-400'"
                          x-text="i + 1"></span>
                    <span class="hidden sm:inline" :class="step === i ? 'text-ink-900' : 'text-ink-400'" x-text="label"></span>
                    <span class="mx-1 hidden h-px w-6 bg-slate-200 sm:inline-block" x-show="i < steps.length - 1"></span>
                </div>
            </template>
        </div>

        {{-- Step 1: source --}}
        <div x-show="step === 0" class="admin-card space-y-4">
            <div>
                <label class="label">Source</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (['package' => 'Package', 'hotel' => 'Hotel', 'destination' => 'Destination', 'custom' => 'Custom'] as $val => $lbl)
                        <label class="cursor-pointer rounded-xl border-2 p-3 text-center text-sm font-semibold"
                               :class="product_type === '{{ $val }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-ink-600'">
                            <input type="radio" name="product_type" value="{{ $val }}" x-model="product_type" class="hidden">{{ $lbl }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div x-show="product_type === 'package'">
                <label class="label">Package</label>
                <select name="product_id" x-model="product_id" class="input">
                    <option value="">Select a package…</option>
                    @foreach ($packages as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div x-show="product_type === 'hotel'">
                <label class="label">Hotel</label>
                <select name="product_id" x-model="product_id" class="input">
                    <option value="">Select a hotel…</option>
                    @foreach ($hotels as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                </select>
            </div>
            <div x-show="product_type === 'destination'">
                <label class="label">Destination</label>
                <select name="product_id" x-model="product_id" class="input">
                    <option value="">Select a destination…</option>
                    @foreach ($destinations as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <p class="text-xs text-ink-400">Price, discount, duration, inclusions and images are pulled live from the portal — no manual entry.</p>
        </div>

        {{-- Step 2: objective + audience --}}
        <div x-show="step === 1" class="admin-card space-y-4" x-cloak>
            <div>
                <label class="label">Campaign objective</label>
                <select name="objective" x-model="objective" class="input">
                    @foreach ($objectives as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Audience</label>
                <select name="audience" x-model="audience" class="input">
                    <option value="">Auto (infer sensible audience)</option>
                    @foreach ($audiences as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Audience detail <span class="text-ink-400">(optional)</span></label>
                <input type="text" name="audience_detail" x-model="audience_detail" class="input" placeholder="e.g. Young couples from Delhi seeking a premium Kashmir honeymoon">
            </div>
        </div>

        {{-- Step 3: format + style + image source --}}
        <div x-show="step === 2" class="admin-card space-y-4" x-cloak>
            <div>
                <label class="label">Platform &amp; format</label>
                <select name="format" x-model="format" class="input">
                    @foreach ($formats as $val => $f)<option value="{{ $val }}">{{ $f[0] }}</option>@endforeach
                </select>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Visual style</label>
                    <select name="style" x-model="style" class="input">
                        @foreach ($styles as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Language</label>
                    <select name="language" x-model="language" class="input">
                        @foreach ($languages as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="label">Image source</label>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['portal' => 'Portal images', 'ai' => 'AI generated', 'combination' => 'Combination'] as $val => $lbl)
                        <label class="cursor-pointer rounded-xl border-2 p-2 text-center text-xs font-semibold"
                               :class="image_source === '{{ $val }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-ink-600'">
                            <input type="radio" name="image_source" value="{{ $val }}" x-model="image_source" class="hidden">{{ $lbl }}
                        </label>
                    @endforeach
                </div>
                @unless ($imageReady)
                    <p class="mt-1 text-xs text-amber-600">AI images not connected — "AI generated" will fall back to a branded background until you add an image provider.</p>
                @endunless
            </div>
        </div>

        {{-- Step 4: brand + template + review --}}
        <div x-show="step === 3" class="admin-card space-y-4" x-cloak>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Brand kit</label>
                    <select name="brand_kit_id" x-model="brand_kit_id" class="input">
                        @foreach ($brandKits as $kit)<option value="{{ $kit->id }}" @selected($kit->is_default)>{{ $kit->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Template <span class="text-ink-400">(optional)</span></label>
                    <select name="template_id" x-model="template_id" class="input">
                        <option value="">AI copy (no template)</option>
                        @foreach ($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="label">Creative name <span class="text-ink-400">(optional)</span></label>
                <input type="text" name="name" x-model="name" class="input" placeholder="Auto-generated from the source if left blank">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="generate_all_formats" value="1" x-model="generate_all_formats" class="rounded border-slate-300">
                Also generate the main social formats (1:1, story, feed, WhatsApp)
            </label>
            @unless ($copyReady)
                <p class="text-xs text-amber-600">AI copy provider not connected — copy will use the template/facts. Connect one on the AI Providers page for AI-written copy.</p>
            @endunless
        </div>

        {{-- Nav --}}
        <div class="mt-4 flex items-center justify-between">
            <button type="button" class="btn-ghost btn-md" x-show="step > 0" @click="step--">Back</button>
            <span></span>
            <button type="button" class="btn-primary btn-md" x-show="step < steps.length - 1" @click="next()">Continue</button>
            <button type="submit" class="btn-primary btn-md" x-show="step === steps.length - 1">Generate Creative</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('studioWizard', () => ({
        steps: ['Source', 'Objective', 'Format', 'Review'],
        step: 0,
        product_type: @json($prefill['product_type'] ?? 'package'),
        product_id: @json($prefill['product_id'] ?? ''),
        objective: 'lead_generation',
        audience: '',
        audience_detail: '',
        format: 'ig_4x5',
        style: 'premium',
        language: 'en',
        image_source: @json($imageReady ? 'combination' : 'portal'),
        brand_kit_id: '',
        template_id: '',
        name: '',
        generate_all_formats: false,
        init() {
            if (this.product_id) this.step = 1;
        },
        next() {
            if (this.step === 0 && this.product_type !== 'custom' && !this.product_id) {
                alert('Please select a ' + this.product_type + '.');
                return;
            }
            if (this.step < this.steps.length - 1) this.step++;
        },
    }));
});
</script>
@endpush
@endsection
