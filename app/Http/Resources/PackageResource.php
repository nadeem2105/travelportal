<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'destination' => $this->destination?->name,
            'duration' => ['days' => $this->duration_days, 'nights' => $this->duration_nights],
            'short_description' => $this->short_description,
            'highlights' => $this->highlights,
            'inclusions' => $this->inclusions,
            'exclusions' => $this->exclusions,
            'pricing' => [
                'base_price' => ['amount' => $this->base_price, 'currency' => 'INR'],
                'discount_percent' => (float) $this->discount_percent,
                'effective_price' => ['amount' => $this->effectivePrice($request->query('date')), 'currency' => 'INR'],
            ],
            'itinerary' => $this->whenLoaded('itineraries', fn () => $this->itineraries->map(fn ($d) => [
                'day' => $d->day_number,
                'title' => $d->title,
                'description' => $d->description,
            ])),
            'cover_image_url' => $this->cover_image ? asset(img($this->cover_image)) : null,
            'featured' => (bool) $this->is_featured,
        ];
    }
}
