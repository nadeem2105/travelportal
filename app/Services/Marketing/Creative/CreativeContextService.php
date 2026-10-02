<?php

namespace App\Services\Marketing\Creative;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Package;

/**
 * Resolves the AUTHORITATIVE facts for a creative source (package/hotel/
 * destination/custom) from the portal database + settings(). This is the single
 * source of truth for the studio — the AI is only ever given these facts and is
 * forbidden from inventing others. Prices/discounts are read live, so a creative
 * regenerated after a price change automatically uses the new price.
 *
 * @return array flat map used for {{tokens}}, prompts, fact-checking and overlays
 */
class CreativeContextService
{
    public function resolve(string $productType, ?int $productId): array
    {
        $base = $this->brandBase();

        return match ($productType) {
            'package' => array_merge($base, $this->package($productId)),
            'hotel' => array_merge($base, $this->hotel($productId)),
            'destination' => array_merge($base, $this->destination($productId)),
            default => $base,
        };
    }

    private function brandBase(): array
    {
        return [
            'phone' => (string) settings('company_phone', ''),
            'whatsapp' => (string) settings('company_whatsapp', settings('company_phone', '')),
            'email' => (string) settings('company_email', ''),
            'website' => (string) settings('company_website', settings('website', config('app.url'))),
            'company' => (string) settings('company_name', 'Leemroz Travels'),
            'cta' => 'Book Now',
        ];
    }

    private function package(?int $id): array
    {
        $p = $id ? Package::with(['destination', 'itineraries', 'hotels'])->find($id) : null;
        if (! $p) {
            return ['product_type' => 'package'];
        }

        $price = method_exists($p, 'discountedPrice') ? $p->discountedPrice() : $p->base_price;
        $old = $p->base_price;
        $discountPct = (int) ($p->discount_percent ?? 0);
        $duration = trim(sprintf('%s Days %s Nights', $p->duration_days, $p->duration_nights));

        return [
            'product_type' => 'package',
            'product_id' => $p->id,
            'package_name' => $p->name,
            'name' => $p->name,
            'slug' => $p->slug,
            'destination' => optional($p->destination)->name,
            'duration' => $duration,
            'duration_days' => $p->duration_days,
            'duration_nights' => $p->duration_nights,
            'price' => $price,
            'price_formatted' => money($price),
            'old_price' => $discountPct > 0 ? $old : null,
            'old_price_formatted' => $discountPct > 0 ? money($old) : null,
            'discount' => $discountPct > 0 ? $discountPct . '% OFF' : null,
            'discount_percent' => $discountPct,
            'inclusions' => (array) ($p->inclusions ?? []),
            'exclusions' => (array) ($p->exclusions ?? []),
            'highlights' => (array) ($p->highlights ?? []),
            'hotel_name' => optional($p->hotels->first())->name,
            'itinerary' => $p->itineraries->map(fn ($i) => trim(($i->title ?? '') ?: ('Day ' . $i->day_number)))->filter()->values()->all(),
            'images' => $this->packageImages($p),
            'cover_image' => $p->cover_image,
            'booking_url' => route('packages.show', $p->slug),
            'short_description' => $p->short_description,
        ];
    }

    private function packageImages(Package $p): array
    {
        $imgs = [];
        if ($p->cover_image) {
            $imgs[] = $p->cover_image;
        }
        foreach ((array) ($p->gallery ?? []) as $g) {
            $imgs[] = $g;
        }

        return array_values(array_unique(array_filter($imgs)));
    }

    private function hotel(?int $id): array
    {
        $h = $id ? Hotel::with('destination')->find($id) : null;
        if (! $h) {
            return ['product_type' => 'hotel'];
        }

        return [
            'product_type' => 'hotel',
            'product_id' => $h->id,
            'hotel_name' => $h->name,
            'name' => $h->name,
            'slug' => $h->slug,
            'destination' => $h->city ?: optional($h->destination)->name,
            'price' => $h->starting_price,
            'price_formatted' => $h->starting_price ? money($h->starting_price) : null,
            'star_rating' => $h->star_rating,
            'highlights' => (array) ($h->amenities ?? []),
            'images' => array_values(array_filter(array_merge([$h->cover_image], (array) ($h->photos ?? [])))),
            'cover_image' => $h->cover_image,
            'booking_url' => route('hotels.show', $h->slug ?? $h->id),
            'short_description' => $h->short_description,
        ];
    }

    private function destination(?int $id): array
    {
        $d = $id ? Destination::find($id) : null;
        if (! $d) {
            return ['product_type' => 'destination'];
        }

        return [
            'product_type' => 'destination',
            'product_id' => $d->id,
            'destination' => $d->name,
            'name' => $d->name,
            'slug' => $d->slug,
            'highlights' => (array) ($d->places_to_visit ?? $d->things_to_do ?? []),
            'images' => array_values(array_filter(array_merge([$d->cover_image], (array) ($d->gallery ?? [])))),
            'cover_image' => $d->cover_image,
            'booking_url' => route('destinations.show', $d->slug ?? $d->id),
            'short_description' => $d->short_description,
            'best_time' => $d->best_time,
        ];
    }
}
